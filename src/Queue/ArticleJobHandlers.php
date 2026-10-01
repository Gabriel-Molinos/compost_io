<?php

declare(strict_types=1);

namespace App\Queue;

use App\Services\ArticleNoteService;
use App\Services\ArticleService;
use App\Services\NotificationService;
use App\Services\Pipeline\ArticlePipeline;
use App\Services\Pipeline\PipelineException;
use Throwable;

/**
 * Registra os handlers `article.generate`/`article.regenerate` numa `Queue`
 * (Fase 9.1b). Usado nos dois lados que despacham/consomem job de artigo:
 *
 * - `ProductionController`, antes de `dispatch()` — com `QUEUE_DRIVER=sync` o
 *   handler roda ali mesmo, na mesma requisição (`SyncQueueDriver::push()`
 *   chama `execute()` na hora); com `redis`, registrar não tem efeito nesta
 *   ponta (`push()` só enfileira), mas precisa existir por simetria.
 * - `bin/worker.php`, antes do laço `reserve()`/`execute()` — a ponta que
 *   importa quando `QUEUE_DRIVER=redis`.
 *
 * Cada lado passa seu próprio `ArticlePipeline` (retry inline no controller,
 * `RetryPolicy::default()` no worker — ver comentário em
 * `ArticlePipeline::__construct()`).
 *
 * Os handlers sempre relançam a exceção (necessário pro driver síncrono —
 * é assim que `ProductionController` sabe mostrar o erro na hora). Quem
 * decide o que fazer com isso no driver Redis é `bin/worker.php`: uma
 * `PipelineException` (falha definitiva do pipeline, já esgotou os retries
 * internos de step) vira `ack()` — o artigo já foi marcado `ERROR` aqui,
 * reenfileirar rodaria a IA de novo do zero (custo real) por algo já
 * tratado. Qualquer OUTRA exceção (bug, infra) vira `fail()` — candidata a
 * retry de verdade no nível de job (Fase 9).
 *
 * Notificação (pedido do responsável, 2026-09-08): `ERROR` é exatamente o
 * "precisa de atenção humana" que a geração automática diária (bin/worker.php)
 * pode produzir sem ninguém olhando. Geração/regeneração MANUAL carrega
 * `user_id` no payload (quem clicou "Gerar"/"Regenerar" — ver
 * `ProductionController`) e só ele é avisado; a automática diária nunca
 * manda esse campo (não tem humano por trás daquele clique), então cai no
 * fallback de avisar a equipe inteira do site.
 *
 * Sucesso avisa também (achado real, 2026-09-09: pediram uma regeneração,
 * ela terminou de verdade — ficou "Em revisão" — e ninguém foi avisado,
 * porque só o caminho de FALHA notificava; o usuário só descobriu
 * perguntando sobre o rascunho no dia seguinte). Mesma regra de
 * destinatário do `$onFailure`: `user_id` do payload quando existe, senão
 * a equipe do site (geração automática diária).
 */
final class ArticleJobHandlers
{
    public static function register(
        Queue $queue,
        ArticlePipeline $pipeline,
        ArticleService $articles,
        ?NotificationService $notifications = null,
    ): void {
        $notifications ??= new NotificationService();

        $onFailure = static function (Throwable $e, int $siteId, ?int $userId) use ($articles, $notifications): void {
            if (!$e instanceof PipelineException) {
                return;
            }
            $articles->setStatus($e->articleId, 'ERROR');

            $article = $articles->find($siteId, $e->articleId);
            $title = $article !== null && !empty($article['title']) ? (string) $article['title'] : 'Rascunho #' . $e->articleId;
            $notifTitle = 'Falha técnica na geração';
            $message = "\"{$title}\" precisa de atenção — {$e->getMessage()}";
            $link = '/sites/' . $siteId . '/production/' . $e->articleId;

            if ($userId !== null) {
                $notifications->notify($userId, NotificationService::TYPE_ATTENTION, $notifTitle, $message, $siteId, $link);
                return;
            }
            $notifications->notifySiteTeam($siteId, NotificationService::TYPE_ATTENTION, $notifTitle, $message, $link);
        };

        $onSuccess = static function (array $result, int $siteId, ?int $userId) use ($notifications): void {
            $title = !empty($result['title']) ? (string) $result['title'] : 'Rascunho #' . $result['article_id'];
            $notifTitle = 'Rascunho pronto para revisão';
            $message = "\"{$title}\" terminou de ser gerado e já está em revisão.";
            $link = '/sites/' . $siteId . '/production/' . $result['article_id'];

            if ($userId !== null) {
                $notifications->notify($userId, NotificationService::TYPE_ARTICLE_READY, $notifTitle, $message, $siteId, $link);
                return;
            }
            $notifications->notifySiteTeam($siteId, NotificationService::TYPE_ARTICLE_READY, $notifTitle, $message, $link);
        };

        $queue->register('article.generate', function (Job $job) use ($pipeline, $onFailure, $onSuccess): void {
            $userId = isset($job->payload['user_id']) && $job->payload['user_id'] !== null ? (int) $job->payload['user_id'] : null;
            try {
                $result = $pipeline->runGenerate(
                    (int) $job->payload['article_id'],
                    (int) $job->payload['site_id'],
                    $job->payload['goal_id'] !== null ? (int) $job->payload['goal_id'] : null,
                    $job->payload['category_id'] !== null ? (int) $job->payload['category_id'] : null,
                );
                $onSuccess($result, (int) $job->payload['site_id'], $userId);
            } catch (Throwable $e) {
                $onFailure($e, (int) $job->payload['site_id'], $userId);
                throw $e;
            }
        });

        $queue->register('article.regenerate', function (Job $job) use ($pipeline, $onFailure, $onSuccess): void {
            $userId = isset($job->payload['user_id']) && $job->payload['user_id'] !== null ? (int) $job->payload['user_id'] : null;
            try {
                $result = $pipeline->runRegenerate(
                    (int) $job->payload['article_id'],
                    (int) $job->payload['site_id'],
                    $job->payload['goal_id'] !== null ? (int) $job->payload['goal_id'] : null,
                    $job->payload['category_id'] !== null ? (int) $job->payload['category_id'] : null,
                    (int) $job->payload['lineage_id'],
                );
                $onSuccess($result, (int) $job->payload['site_id'], $userId);
            } catch (Throwable $e) {
                $onFailure($e, (int) $job->payload['site_id'], $userId);
                throw $e;
            }
        });

        // Reavaliação dos pareceres sobre o texto editado (pedido 2026-10-01).
        // Fila e não na requisição: 3 passos de IA levaram ~110s no teste real,
        // acima dos 100s em que o Cloudflare corta a resposta (erro 524). Falha
        // aqui NUNCA vira ERROR — o artigo continua bom, só o parecer não veio;
        // por isso não passa pelo $onFailure. O andamento fica em
        // `pipeline.reaudit` (a tela do artigo mostra "reavaliando…" a partir dela).
        $queue->register('article.reaudit', function (Job $job) use ($pipeline, $articles, $notifications): void {
            $articleId = (int) $job->payload['article_id'];
            $siteId = (int) $job->payload['site_id'];
            $userId = isset($job->payload['user_id']) && $job->payload['user_id'] !== null ? (int) $job->payload['user_id'] : null;
            $noteService = new ArticleNoteService();
            $article = $articles->find($siteId, $articleId);
            $title = $article !== null && !empty($article['title']) ? (string) $article['title'] : 'Rascunho #' . $articleId;
            $link = '/sites/' . $siteId . '/production/' . $articleId . '#qualidade';

            try {
                $result = $pipeline->reaudit(
                    $articleId,
                    $siteId,
                    $job->payload['goal_id'] !== null ? (int) $job->payload['goal_id'] : null,
                    $job->payload['category_id'] !== null ? (int) $job->payload['category_id'] : null,
                );
            } catch (Throwable $e) {
                $noteService->merge($articleId, 'pipeline', ['reaudit' => ['status' => 'failed', 'finished_at' => date('Y-m-d H:i:s'), 'error' => $e->getMessage()]]);
                if ($userId !== null) {
                    $notifications->notify($userId, NotificationService::TYPE_ATTENTION, 'Reavaliação não concluída',
                        "\"{$title}\" — a IA não conseguiu reavaliar: {$e->getMessage()}", $siteId, $link);
                }
                throw $e;
            }

            $noteService->merge($articleId, 'pipeline', ['reaudit' => ['status' => 'done', 'finished_at' => date('Y-m-d H:i:s')]]);
            if ($userId !== null) {
                $notifications->notify($userId, NotificationService::TYPE_ARTICLE_READY, 'Reavaliação concluída',
                    "\"{$title}\" — " . ($result['recommendation'] === 'ready_for_human'
                        ? 'a IA considera o texto atual pronto pra revisão.'
                        : 'a IA ainda aponta ajustes; veja os pareceres.'), $siteId, $link);
            }
        });
    }
}

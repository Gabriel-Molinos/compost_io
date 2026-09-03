<?php

declare(strict_types=1);

namespace App\Queue;

use App\Services\ArticleService;
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
 */
final class ArticleJobHandlers
{
    public static function register(Queue $queue, ArticlePipeline $pipeline, ArticleService $articles): void
    {
        $onFailure = static function (Throwable $e) use ($articles): void {
            if ($e instanceof PipelineException) {
                $articles->setStatus($e->articleId, 'ERROR');
            }
        };

        $queue->register('article.generate', function (Job $job) use ($pipeline, $onFailure): void {
            try {
                $pipeline->runGenerate(
                    (int) $job->payload['article_id'],
                    (int) $job->payload['site_id'],
                    $job->payload['goal_id'] !== null ? (int) $job->payload['goal_id'] : null,
                    $job->payload['category_id'] !== null ? (int) $job->payload['category_id'] : null,
                );
            } catch (Throwable $e) {
                $onFailure($e);
                throw $e;
            }
        });

        $queue->register('article.regenerate', function (Job $job) use ($pipeline, $onFailure): void {
            try {
                $pipeline->runRegenerate(
                    (int) $job->payload['article_id'],
                    (int) $job->payload['site_id'],
                    $job->payload['goal_id'] !== null ? (int) $job->payload['goal_id'] : null,
                    $job->payload['category_id'] !== null ? (int) $job->payload['category_id'] : null,
                    (int) $job->payload['lineage_id'],
                );
            } catch (Throwable $e) {
                $onFailure($e);
                throw $e;
            }
        });
    }
}

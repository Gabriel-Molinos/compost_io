<?php

declare(strict_types=1);

namespace App\Queue;

use App\Services\ArticleService;
use App\Services\NotificationService;
use App\Services\ScheduleService;
use App\Services\WordPressPublishService;
use Throwable;

/**
 * Registra o handler `schedule.publish` numa `Queue` (Fase 9 — publicação
 * agendada automática). Só faz sentido no worker (driver `redis`): a
 * varredura que despacha esses jobs (`ScheduleService::dueForPublish()`)
 * roda dentro do laço de `bin/worker.php`, então não há caminho síncrono
 * (diferente de `ArticleJobHandlers`, que o `ProductionController` também
 * usa com `QUEUE_DRIVER=sync`).
 *
 * Falha de `WordPressPublishService::publish()` (API fora, rate limit, etc.)
 * é uma falha "normal" de infra — deixa propagar pra `fail()` do driver
 * (retry automático até `Job::MAX_ATTEMPTS`). Só na última tentativa este
 * handler marca `schedules.FAILED` *antes* de relançar — visível ao
 * Redator-Chefe, nunca falha silenciosamente (regra de transparência, §59).
 *
 * Notificação (pedido do responsável, 2026-09-08): esta é a publicação
 * AUTOMÁTICA — ninguém clicou "Enviar ao WordPress" agora, então ninguém
 * está olhando a tela pra ver se deu certo. Sucesso avisa na hora; falha só
 * avisa quando esgota as tentativas (mesma guarda do markFailed acima —
 * senão notificaria 1 vez por tentativa de retry, virando spam).
 */
final class ScheduleJobHandlers
{
    public static function register(
        Queue $queue,
        WordPressPublishService $publisher,
        ScheduleService $schedules,
        ?ArticleService $articles = null,
        ?NotificationService $notifications = null,
    ): void {
        $articles ??= new ArticleService();
        $notifications ??= new NotificationService();

        $queue->register('schedule.publish', function (Job $job) use ($publisher, $schedules, $articles, $notifications): void {
            $siteId = (int) $job->payload['site_id'];
            $articleId = (int) $job->payload['article_id'];

            try {
                $result = $publisher->publish($articleId, $siteId);
                self::notify($articles, $notifications, $siteId, $articleId, true, 'Post #' . $result['post_id'] . ' no WordPress.');
            } catch (Throwable $e) {
                if ($job->attempts >= Job::MAX_ATTEMPTS - 1) {
                    // Esta falha vai esgotar as tentativas — o job cai no
                    // dead-letter logo em seguida (fail() do driver).
                    $schedules->markFailed((int) $job->payload['schedule_id']);
                    self::notify($articles, $notifications, $siteId, $articleId, false, $e->getMessage());
                }

                throw $e;
            }
        });
    }

    private static function notify(
        ArticleService $articles,
        NotificationService $notifications,
        int $siteId,
        int $articleId,
        bool $success,
        string $detail,
    ): void {
        $article = $articles->find($siteId, $articleId);
        $title = $article !== null && !empty($article['title']) ? (string) $article['title'] : 'Rascunho #' . $articleId;

        $notifications->notifySiteTeam(
            $siteId,
            $success ? NotificationService::TYPE_PUBLISH_SUCCESS : NotificationService::TYPE_PUBLISH_FAILED,
            $success ? 'Post publicado (automático)' : 'Falha ao publicar (automático)',
            "\"{$title}\" — {$detail}",
            '/sites/' . $siteId . '/production/' . $articleId . '#agendar',
        );
    }
}

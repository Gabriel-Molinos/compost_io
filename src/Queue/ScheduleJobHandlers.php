<?php

declare(strict_types=1);

namespace App\Queue;

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
 */
final class ScheduleJobHandlers
{
    public static function register(Queue $queue, WordPressPublishService $publisher, ScheduleService $schedules): void
    {
        $queue->register('schedule.publish', function (Job $job) use ($publisher, $schedules): void {
            try {
                $publisher->publish((int) $job->payload['article_id'], (int) $job->payload['site_id']);
            } catch (Throwable $e) {
                if ($job->attempts >= Job::MAX_ATTEMPTS - 1) {
                    // Esta falha vai esgotar as tentativas — o job cai no
                    // dead-letter logo em seguida (fail() do driver).
                    $schedules->markFailed((int) $job->payload['schedule_id']);
                }

                throw $e;
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Queue;

/**
 * Transporte da fila (ADR-006). Hoje só `SyncQueueDriver` (roda inline);
 * `RedisQueueDriver` + worker próprio entram quando houver instância Redis.
 */
interface QueueDriver
{
    /** Enfileira um job. No driver síncrono, isso o executa imediatamente. */
    public function push(Job $job): void;

    /** Nome curto do driver, para log. */
    public function name(): string;
}

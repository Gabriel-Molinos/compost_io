<?php

declare(strict_types=1);

namespace App\Queue;

/**
 * Transporte da fila (ADR-006): `SyncQueueDriver` (roda inline, padrão hoje)
 * ou `RedisQueueDriver` (fila de verdade, consumida por `bin/worker.php`).
 */
interface QueueDriver
{
    /** Enfileira um job. No driver síncrono, isso o executa imediatamente. */
    public function push(Job $job): void;

    /**
     * Retira o próximo job da fila, bloqueando até haver um ou até um timeout
     * curto (para o worker poder checar sinais de parada). `null` se o
     * timeout estourar sem job disponível. Só faz sentido para drivers com
     * fila de verdade — `SyncQueueDriver` não tem o que reservar.
     */
    public function reserve(): ?Job;

    /** Nome curto do driver, para log. */
    public function name(): string;
}

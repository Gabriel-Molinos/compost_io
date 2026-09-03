<?php

declare(strict_types=1);

namespace App\Queue;

/**
 * Driver de fila síncrono: executa o job na hora do `push`, na mesma requisição.
 * É o padrão em desenvolvimento e enquanto não há Redis (ADR-006). O restante
 * do código enfileira jobs sem saber que, hoje, eles rodam inline.
 */
final class SyncQueueDriver implements QueueDriver
{
    /** @var callable(Job): void */
    private $handler;

    /** @param callable(Job): void $handler executa um job (lança em caso de falha definitiva) */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function push(Job $job): void
    {
        ($this->handler)($job->id === '' ? $job->withId('sync-' . bin2hex(random_bytes(6))) : $job);
    }

    /** Não há fila para consumir no driver síncrono — `bin/worker.php` nem chega a chamar isto. */
    public function reserve(): ?Job
    {
        throw new \LogicException('SyncQueueDriver não tem fila para reservar — jobs rodam inline no push().');
    }

    /** Não se aplica: sem `reserve()`, não há o que confirmar. */
    public function ack(Job $job): void
    {
        throw new \LogicException('SyncQueueDriver não usa ack() — jobs rodam inline no push().');
    }

    /** Não se aplica: uma falha no driver síncrono já propaga na hora, no `push()`. */
    public function fail(Job $job, string $error): void
    {
        throw new \LogicException('SyncQueueDriver não usa fail() — a exceção já propaga no push().');
    }

    public function name(): string
    {
        return 'sync';
    }
}

<?php

declare(strict_types=1);

namespace App\Queue;

use Predis\Client;

/**
 * Driver de fila real via Redis (ADR-006), padrão "fila confiável" do
 * próprio Redis (retry + dead-letter, Fase 9): `push()` empilha na lista
 * `queueKey`; `reserve()` MOVE (não remove) o job pra `processingKey` via
 * `BRPOPLPUSH` — se o worker morrer no meio do processamento, o job continua
 * visível ali (recuperável com `bin/queue_requeue_stuck.php`), não some.
 * Sucesso chama `ack()` (tira de `processingKey`); falha chama `fail()`
 * (reenfileira até `MAX_ATTEMPTS`, depois manda pro `deadLetterKey`).
 * Consumido por `bin/worker.php`. Usa `predis/predis` — alternativa 100% PHP
 * à extensão nativa `ext-redis`, prevista no próprio ADR-006.
 */
final class RedisQueueDriver implements QueueDriver
{
    private Client $client;

    /** @param int $reserveTimeoutSeconds timeout do BRPOPLPUSH — worker usa isso pra checar sinais de parada entre tentativas */
    public function __construct(
        private readonly RedisConfig $config,
        private readonly int $reserveTimeoutSeconds = 5,
    ) {
        $this->client = new Client($this->config->url);
    }

    public function push(Job $job): void
    {
        $job = $job->id === '' ? $job->withId('redis-' . bin2hex(random_bytes(6))) : $job;
        $this->client->lpush($this->config->queueKey, [$job->toJson()]);
    }

    public function reserve(): ?Job
    {
        $raw = $this->client->brpoplpush($this->config->queueKey, $this->config->processingKey(), $this->reserveTimeoutSeconds);
        if ($raw === null || $raw === false) {
            return null;
        }

        return Job::fromJson($raw);
    }

    public function ack(Job $job): void
    {
        $this->client->lrem($this->config->processingKey(), 1, $job->toJson());
    }

    public function fail(Job $job, string $error): void
    {
        // Tira da lista "em processamento" — reenfileirar ou mandar pro
        // dead-letter, mas nunca deixar duplicado em processingKey.
        $this->client->lrem($this->config->processingKey(), 1, $job->toJson());

        $next = $job->withIncrementedAttempts();
        if ($next->attempts >= Job::MAX_ATTEMPTS) {
            $this->client->lpush($this->config->deadLetterKey(), [json_encode(
                ['job' => json_decode($next->toJson(), true), 'error' => $error, 'failed_at' => date('c')],
                JSON_THROW_ON_ERROR
            )]);

            return;
        }

        $this->client->lpush($this->config->queueKey, [$next->toJson()]);
    }

    public function name(): string
    {
        return 'redis';
    }
}

<?php

declare(strict_types=1);

namespace App\Queue;

use Predis\Client;

/**
 * Driver de fila real via Redis (ADR-006): `push()` empilha na lista
 * `queueKey`, `reserve()` bloqueia (BRPOP) até haver job ou até o timeout.
 * Consumido por `bin/worker.php`. Usa `predis/predis` — alternativa 100% PHP
 * à extensão nativa `ext-redis`, prevista no próprio ADR-006.
 */
final class RedisQueueDriver implements QueueDriver
{
    private Client $client;

    /** @param int $reserveTimeoutSeconds timeout do BRPOP — worker usa isso pra checar sinais de parada entre tentativas */
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
        $result = $this->client->brpop([$this->config->queueKey], $this->reserveTimeoutSeconds);
        if ($result === null) {
            return null;
        }

        // BRPOP retorna [chave, valor].
        return Job::fromJson($result[1]);
    }

    public function name(): string
    {
        return 'redis';
    }
}

<?php

declare(strict_types=1);

namespace App\Queue;

use App\Config\Env;
use RuntimeException;

/**
 * Ponto único para enfileirar trabalho da IA. Os handlers são registrados por
 * tipo de job; o driver (hoje `sync`) decide *quando* eles rodam.
 *
 * O pipeline editorial (Fase 4.4) registra os handlers e chama `dispatch()`.
 */
final class Queue
{
    /** @var array<string, callable(Job): void> */
    private array $handlers = [];

    private QueueDriver $driver;

    public function __construct(?QueueDriver $driver = null)
    {
        $this->driver = $driver ?? $this->makeDriver();
    }

    /** @param callable(Job): void $handler */
    public function register(string $type, callable $handler): void
    {
        $this->handlers[$type] = $handler;
    }

    public function dispatch(Job $job): void
    {
        $this->driver->push($job);
    }

    public function driverName(): string
    {
        return $this->driver->name();
    }

    /** Executa um job (chamado pelo driver síncrono agora, pelo worker no futuro). */
    public function execute(Job $job): void
    {
        $handler = $this->handlers[$job->type]
            ?? throw new RuntimeException("Nenhum handler registrado para o job '{$job->type}'.");

        $handler($job);
    }

    private function makeDriver(): QueueDriver
    {
        $name = Env::get('QUEUE_DRIVER', 'sync');

        return match ($name) {
            'sync', null => new SyncQueueDriver(fn (Job $job) => $this->execute($job)),
            default      => throw new RuntimeException(
                "QUEUE_DRIVER '{$name}' ainda não implementado — só 'sync' por ora (ADR-006, Fase 4.3)."
            ),
        };
    }
}

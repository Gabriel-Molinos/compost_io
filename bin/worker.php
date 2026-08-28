<?php

declare(strict_types=1);

/**
 * Worker da fila de IA (ADR-006).
 *
 *   php bin/worker.php
 *
 * Hoje o único driver é `sync`: os jobs rodam inline na própria requisição que
 * os enfileira (Fase 4.3), então NÃO há fila para este worker consumir — ele só
 * informa isso e sai.
 *
 * Quando entrar o `RedisQueueDriver`, este script vira o laço
 * `while (true) { $job = $driver->reserve(); $queue->execute($job); }`
 * rodando sob supervisor (produção) ou manualmente (dev).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Env;

Env::load(dirname(__DIR__) . '/.env');

$driver = Env::get('QUEUE_DRIVER', 'sync');

if ($driver === 'sync' || $driver === null) {
    fwrite(STDERR, "QUEUE_DRIVER=sync — os jobs rodam inline; não há fila para consumir.\n");
    fwrite(STDERR, "Configure QUEUE_DRIVER=redis (quando implementado) para usar este worker.\n");
    exit(0);
}

fwrite(STDERR, "Driver '{$driver}' ainda não implementado.\n");
exit(1);

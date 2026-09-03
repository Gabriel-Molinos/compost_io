<?php

declare(strict_types=1);

/**
 * Smoke test da fila (Fase 9.1): despacha UM job `smoke.echo` e sai.
 *
 *   php bin/queue_smoke.php "mensagem opcional"
 *
 * Com QUEUE_DRIVER=sync (padrão) o job roda na hora, inline, e você vê o eco
 * aqui mesmo. Com QUEUE_DRIVER=redis, o job vai pra fila — rode
 * `php bin/worker.php` em outro terminal (com o mesmo QUEUE_DRIVER=redis) pra
 * ver o eco por lá, confirmando o push→reserve→execute de ponta a ponta.
 *
 * Requer REDIS_URL configurada no .env quando QUEUE_DRIVER=redis (ver
 * docs/technical/setup-e-operacoes.md §77 para subir um Redis local).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Env;
use App\Queue\Job;
use App\Queue\Queue;

Env::load(dirname(__DIR__) . '/.env');

$message = $argv[1] ?? 'oi da fila';

$queue = new Queue();

// Mesmo handler de verificação do worker — só importa pro driver sync,
// que executa o job na hora (não passa pelo worker).
$queue->register('smoke.echo', function (Job $job): void {
    $msg = $job->payload['message'] ?? '(sem mensagem)';
    echo "[smoke.echo] job {$job->id}: {$msg}\n";
});

echo "Driver: {$queue->driverName()}\n";

try {
    $queue->dispatch(new Job('smoke.echo', ['message' => $message]));
} catch (\Throwable $e) {
    fwrite(STDERR, 'Falha ao despachar: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($queue->driverName() === 'redis') {
    echo "Job enfileirado. Rode 'php bin/worker.php' (com QUEUE_DRIVER=redis) pra consumir.\n";
}

exit(0);

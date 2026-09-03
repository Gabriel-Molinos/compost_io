<?php

declare(strict_types=1);

/**
 * Recuperação manual de jobs presos (Fase 9): se `bin/worker.php` morrer no
 * meio de um job, ele fica na lista "em processamento" (`RedisQueueDriver`
 * usa `BRPOPLPUSH` — reserve() move, não remove, exatamente pra isso não se
 * perder). Não há timeout automático — rode este script manualmente depois
 * de confirmar que o worker morrou de verdade (senão pode duplicar
 * processamento de um job que ainda está rodando).
 *
 *   php bin/queue_requeue_stuck.php            # move de volta pra fila
 *   php bin/queue_requeue_stuck.php --dry-run   # só mostra o que está preso
 *
 * Exige QUEUE_DRIVER=redis (só o driver Redis tem lista de processamento).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Env;
use App\Queue\RedisConfig;
use Predis\Client;

Env::load(dirname(__DIR__) . '/.env');

if (Env::get('QUEUE_DRIVER', 'sync') !== 'redis') {
    fwrite(STDERR, "QUEUE_DRIVER não é 'redis' — não há lista de processamento a recuperar.\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv, true);

$config = RedisConfig::fromEnv();
$client = new Client($config->url);

$stuck = $client->lrange($config->processingKey(), 0, -1);
if ($stuck === []) {
    echo "Nada preso em '{$config->processingKey()}'.\n";
    exit(0);
}

echo count($stuck) . " job(s) preso(s) em '{$config->processingKey()}':\n";
foreach ($stuck as $raw) {
    $data = json_decode($raw, true);
    $id = $data['id'] ?? '?';
    $type = $data['type'] ?? '?';
    echo "  - {$id} ({$type})\n";
}

if ($dryRun) {
    echo "\n--dry-run: nada movido.\n";
    exit(0);
}

$moved = 0;
for ($i = 0; $i < count($stuck); $i++) {
    $raw = $client->rpoplpush($config->processingKey(), $config->queueKey);
    if ($raw === null || $raw === false) {
        break;
    }
    $moved++;
}

echo "\n{$moved} job(s) devolvido(s) pra fila ('{$config->queueKey}').\n";

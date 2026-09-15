<?php

declare(strict_types=1);

/**
 * Smoke test do cache (Fase 9/escala): confere hit/miss, expiração por TTL,
 * cache de valor `null` e o fallback quando o Redis não responde.
 *
 *   php bin/cache_smoke.php
 *
 * Requer REDIS_URL configurada no .env pra testar contra o Memurai/Redis de
 * verdade (senão os testes 1-3 rodam com o cache desabilitado — passam
 * igual, já que `remember()` sempre recalcula nesse modo, mas não provam
 * nada sobre hit/miss real).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Cache\CacheConfig;
use App\Cache\CacheService;
use App\Config\Env;

Env::load(dirname(__DIR__) . '/.env');

$failures = 0;

function check(string $label, bool $ok): void
{
    global $failures;
    echo ($ok ? '[OK] ' : '[FALHOU] ') . $label . "\n";
    if (!$ok) {
        $failures++;
    }
}

$enabled = CacheConfig::fromEnv()->isEnabled();
echo 'Cache habilitado (REDIS_URL configurada): ' . ($enabled ? 'sim' : 'não') . "\n";

$cache = new CacheService();

// 1. Miss depois hit: segunda chamada não deve recalcular.
$first = $cache->remember('smoke:hit', 30, fn () => random_int(1, 1_000_000_000));
$second = $cache->remember('smoke:hit', 30, fn () => random_int(1, 1_000_000_000));
check('hit depois de miss devolve o mesmo valor', !$enabled || $first === $second);

// 2. Expiração por TTL: depois de expirar, recalcula.
$before = $cache->remember('smoke:ttl', 2, fn () => random_int(1, 1_000_000_000));
sleep(3);
$after = $cache->remember('smoke:ttl', 2, fn () => random_int(1, 1_000_000_000));
check('expira depois do TTL e recalcula', !$enabled || $before !== $after);

// 3. Valor null de verdade é cacheado (não fica "sempre miss").
$calls = 0;
$cache->remember('smoke:null', 30, function () use (&$calls) {
    $calls++;

    return null;
});
$cache->remember('smoke:null', 30, function () use (&$calls) {
    $calls++;

    return null;
});
check('resultado null é cacheado (calculado só 1x)', !$enabled || $calls === 1);

// 4. Redis fora do ar: remember() ainda devolve o valor calculado, sem lançar nada.
$downConfig = new CacheConfig('tcp://127.0.0.1:1'); // nada escutando nessa porta
$downCache = new CacheService($downConfig);
try {
    $result = $downCache->remember('smoke:down', 5, fn () => 'computed-live');
    check('Redis fora do ar cai pro cálculo real, sem exceção', $result === 'computed-live');
} catch (\Throwable $e) {
    check('Redis fora do ar cai pro cálculo real, sem exceção', false);
    fwrite(STDERR, 'Exceção inesperada: ' . $e->getMessage() . "\n");
}

// Limpeza das chaves de teste.
foreach (['smoke:hit', 'smoke:ttl', 'smoke:null'] as $key) {
    $cache->forget($key);
}

echo "\n" . ($failures === 0 ? 'Tudo certo.' : "{$failures} falha(s).") . "\n";
exit($failures === 0 ? 0 : 1);

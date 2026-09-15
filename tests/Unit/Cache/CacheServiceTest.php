<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use App\Cache\CacheConfig;
use App\Cache\CacheService;
use PHPUnit\Framework\TestCase;

/**
 * Cobre só o caminho "sem Redis" (config desabilitada) — não precisa de um
 * Redis/Memurai de verdade rodando, então roda em qualquer máquina/CI. O
 * caminho "com Redis de verdade" (hit/miss/TTL/null cacheado) já tem
 * bin/cache_smoke.php cobrindo manualmente contra o Memurai local.
 */
final class CacheServiceTest extends TestCase
{
    public function testRememberCallsComputeWhenDisabled(): void
    {
        $cache = new CacheService(new CacheConfig(null));
        $calls = 0;

        $value = $cache->remember('chave', 60, function () use (&$calls) {
            $calls++;

            return 'valor calculado';
        });

        $this->assertSame('valor calculado', $value);
        $this->assertSame(1, $calls);
    }

    public function testRememberRecomputesEveryTimeWhenDisabled(): void
    {
        // Sem Redis, não há "cache" de verdade — cada remember() chama compute() de novo.
        $cache = new CacheService(new CacheConfig(null));
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return $calls;
        };

        $first = $cache->remember('chave', 60, $compute);
        $second = $cache->remember('chave', 60, $compute);

        $this->assertSame(1, $first);
        $this->assertSame(2, $second);
    }

    public function testRememberPreservesNullValueWhenDisabled(): void
    {
        $cache = new CacheService(new CacheConfig(null));

        $value = $cache->remember('chave', 60, fn () => null);

        $this->assertNull($value);
    }

    public function testForgetNeverThrowsWhenDisabled(): void
    {
        $cache = new CacheService(new CacheConfig(null));

        $cache->forget('qualquer-chave');

        $this->addToAssertionCount(1); // chegou aqui sem lançar exceção
    }

    public function testMalformedRedisUrlDegradesInsteadOfThrowing(): void
    {
        // URL claramente inválida — construtor precisa cair pro modo
        // desabilitado (catch \Throwable), nunca propagar erro de conexão.
        $cache = new CacheService(new CacheConfig('isto-nao-e-uma-url-redis-valida'));

        $value = $cache->remember('chave', 60, fn () => 'calculado mesmo assim');

        $this->assertSame('calculado mesmo assim', $value);
    }
}

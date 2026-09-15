<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use App\Cache\CacheConfig;
use PHPUnit\Framework\TestCase;

final class CacheConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['REDIS_URL']);
        putenv('REDIS_URL');
    }

    public function testDisabledWhenRedisUrlIsEmpty(): void
    {
        $_ENV['REDIS_URL'] = '';

        $config = CacheConfig::fromEnv();

        $this->assertFalse($config->isEnabled());
        $this->assertNull($config->url);
    }

    public function testDisabledWhenRedisUrlIsUnset(): void
    {
        unset($_ENV['REDIS_URL']);
        putenv('REDIS_URL');

        $config = CacheConfig::fromEnv();

        $this->assertFalse($config->isEnabled());
    }

    public function testEnabledWhenRedisUrlIsSet(): void
    {
        $_ENV['REDIS_URL'] = 'redis://127.0.0.1:6379';

        $config = CacheConfig::fromEnv();

        $this->assertTrue($config->isEnabled());
        $this->assertSame('redis://127.0.0.1:6379', $config->url);
    }

    public function testUsesDefaultPrefixByDefault(): void
    {
        $config = new CacheConfig('redis://127.0.0.1:6379');

        $this->assertSame(CacheConfig::DEFAULT_PREFIX, $config->prefix);
    }

    public function testCustomPrefixIsRespected(): void
    {
        $config = new CacheConfig('redis://127.0.0.1:6379', 'outro:');

        $this->assertSame('outro:', $config->prefix);
    }
}

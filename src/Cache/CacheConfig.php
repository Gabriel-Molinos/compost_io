<?php

declare(strict_types=1);

namespace App\Cache;

use App\Config\Env;

/**
 * Configuração do cache-aside (Fase 9/escala, achado real 2026-09-14).
 * Reaproveita a mesma `REDIS_URL` da fila (`App\Queue\RedisConfig`) — sem
 * variável de ambiente nova — mas com postura DIFERENTE: cache é otimização,
 * nunca dependência (mesmo princípio já usado pra integrações opcionais, ex.
 * NotebookLM, `docs/technical/integracoes.md` §40.11). `RedisConfig::fromEnv()`
 * lança exceção se `REDIS_URL` estiver vazia (correto pra fila, que exige
 * Redis de verdade quando `QUEUE_DRIVER=redis`) — aqui, vazia só significa
 * "cache desabilitado", nunca erro.
 */
final class CacheConfig
{
    public const DEFAULT_PREFIX = 'cache:';

    public function __construct(
        public readonly ?string $url,
        public readonly string $prefix = self::DEFAULT_PREFIX,
    ) {
    }

    public static function fromEnv(): self
    {
        $url = trim((string) Env::get('REDIS_URL', ''));

        return new self($url === '' ? null : $url);
    }

    public function isEnabled(): bool
    {
        return $this->url !== null;
    }
}

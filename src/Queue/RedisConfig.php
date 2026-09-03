<?php

declare(strict_types=1);

namespace App\Queue;

use App\Config\Env;
use RuntimeException;

/**
 * Configuração do driver Redis da fila (ADR-006), lida do `.env` (`REDIS_URL`).
 * `queueKey` é fixo por ora — uma fila só, poucos tipos de job (mesmo motivo
 * do ADR-006 de não trazer uma lib de fila completa).
 */
final class RedisConfig
{
    public const DEFAULT_QUEUE_KEY = 'queue:jobs';

    public function __construct(
        public readonly string $url,
        public readonly string $queueKey = self::DEFAULT_QUEUE_KEY,
    ) {
    }

    public static function fromEnv(): self
    {
        $url = trim((string) Env::get('REDIS_URL', ''));
        if ($url === '') {
            throw new RuntimeException(
                'REDIS_URL não configurada no .env — necessária quando QUEUE_DRIVER=redis (ADR-006).'
            );
        }

        return new self($url);
    }
}

<?php

declare(strict_types=1);

namespace App\Queue;

use App\Config\Env;

/**
 * Política de retry para falhas técnicas da IA (testes-e-observabilidade §96):
 * 3 tentativas, backoff exponencial. Em produção o backoff é 30s → 2min → 10min;
 * fora de produção é curto para não travar o desenvolvimento.
 */
final class RetryPolicy
{
    /** @param list<int> $backoffSeconds atraso ANTES da tentativa n+1 (índice 0 = após a 1ª falha) */
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly array $backoffSeconds = [30, 120, 600],
    ) {
    }

    public static function default(): self
    {
        if (Env::get('APP_ENV') === 'production') {
            return new self();
        }

        return new self(backoffSeconds: [1, 2, 4]);
    }

    /** Sem espera — para testes. */
    public static function immediate(): self
    {
        return new self(backoffSeconds: [0, 0, 0]);
    }

    /**
     * Backoff curto, para quando o retry roda DENTRO da requisição HTTP
     * (pipeline síncrono) — não dá para bloquear o worker por 10 min.
     */
    public static function inline(): self
    {
        return new self(backoffSeconds: [2, 5, 15]);
    }

    /** Atraso antes da tentativa após `$failedAttempt` (1-based). 0 se não há mais retry. */
    public function delayAfter(int $failedAttempt): int
    {
        return $this->backoffSeconds[$failedAttempt - 1] ?? 0;
    }
}

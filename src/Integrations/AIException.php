<?php

declare(strict_types=1);

namespace App\Integrations;

use RuntimeException;

/**
 * Falha ao falar com um provedor de IA. Subclasses por fornecedor
 * (ex.: `Gemini\GeminiException`).
 *
 * `retryable` indica se faz sentido uma nova tentativa (timeout, 429, 5xx) —
 * usado pela política de retry da fila (fluxo-editorial §96, Fase 4.3).
 */
class AIException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }
}

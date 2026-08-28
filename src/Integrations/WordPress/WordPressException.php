<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use RuntimeException;

/**
 * Falha ao falar com o WordPress de um site (REST API, ADR-005).
 * `$retryable` marca falhas transitórias (rede, HTTP 5xx, 429).
 */
final class WordPressException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly int $httpStatus = 0,
    ) {
        parent::__construct($message);
    }
}

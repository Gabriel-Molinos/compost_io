<?php

declare(strict_types=1);

namespace App\Integrations\Image;

use App\Integrations\AIException;

/**
 * Falha ao gerar imagem. Espelha `Gemini\GeminiException`: `retryable`
 * (timeout / 429 / 5xx) alimenta a política de retry da fila (fluxo-editorial §96).
 */
final class ImageException extends AIException
{
}

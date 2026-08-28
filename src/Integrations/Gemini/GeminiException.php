<?php

declare(strict_types=1);

namespace App\Integrations\Gemini;

use App\Integrations\AIException;

/** Falha específica da API do Gemini (integracoes.md §35). */
final class GeminiException extends AIException
{
}

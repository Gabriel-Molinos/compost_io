<?php

declare(strict_types=1);

namespace App\Integrations\Gemini;

use App\Config\Env;

/**
 * Configuração da integração Gemini, lida do `.env`
 * (`GEMINI_API_KEY`, `GEMINI_MODEL`). Nunca logar `apiKey`.
 */
final class GeminiConfig
{
    public const DEFAULT_MODEL = 'gemini-2.5-pro';
    public const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(
        public readonly string $apiKey,
        public readonly string $model = self::DEFAULT_MODEL,
        public readonly int $timeoutSeconds = 120,
    ) {
    }

    public static function fromEnv(): self
    {
        $key = trim((string) Env::get('GEMINI_API_KEY', ''));
        if ($key === '') {
            throw new GeminiException('GEMINI_API_KEY não configurada no .env.');
        }

        $model = trim((string) Env::get('GEMINI_MODEL', self::DEFAULT_MODEL));

        return new self($key, $model !== '' ? $model : self::DEFAULT_MODEL);
    }

    /** URL do endpoint generateContent para o modelo configurado (sem a key). */
    public function generateContentUrl(): string
    {
        return self::BASE_URL . '/models/' . rawurlencode($this->model) . ':generateContent';
    }
}

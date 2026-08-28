<?php

declare(strict_types=1);

namespace App\Integrations\Image\NanoBanana;

use App\Config\Env;
use App\Integrations\Image\ImageException;

/**
 * Configuração da integração Nano Banana (família de imagem do Gemini),
 * lida do `.env`. A chave cai para `GEMINI_API_KEY` quando `IMAGE_API_KEY`
 * não é definida — hoje é a mesma conta Google. Nunca logar `apiKey`.
 */
final class NanoBananaConfig
{
    public const DEFAULT_MODEL    = 'gemini-3-pro-image-preview';
    public const DEFAULT_REVISION = '2026-05-20';
    public const BASE_URL         = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(
        public readonly string $apiKey,
        public readonly string $model = self::DEFAULT_MODEL,
        public readonly string $apiRevision = self::DEFAULT_REVISION,
        public readonly string $defaultSize = '2K',
        public readonly int $timeoutSeconds = 120,
    ) {
    }

    public static function fromEnv(): self
    {
        $key = trim((string) Env::get('IMAGE_API_KEY', ''));
        if ($key === '') {
            $key = trim((string) Env::get('GEMINI_API_KEY', ''));
        }
        if ($key === '') {
            throw new ImageException('IMAGE_API_KEY (ou GEMINI_API_KEY) não configurada no .env.');
        }

        $model = trim((string) Env::get('IMAGE_MODEL', self::DEFAULT_MODEL));
        $revision = trim((string) Env::get('IMAGE_API_REVISION', self::DEFAULT_REVISION));
        $size = trim((string) Env::get('IMAGE_DEFAULT_SIZE', '2K'));

        return new self(
            apiKey: $key,
            model: $model !== '' ? $model : self::DEFAULT_MODEL,
            apiRevision: $revision !== '' ? $revision : self::DEFAULT_REVISION,
            defaultSize: $size !== '' ? $size : '2K',
        );
    }

    /** URL do endpoint de criação de interação (sem a key). */
    public function interactionsUrl(): string
    {
        return self::BASE_URL . '/interactions';
    }
}

<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

/**
 * Credencial WordPress de um site (docs/technical/seguranca.md §46).
 * Vem sempre da tabela `site_wordpress_connections` + `sites.wordpress_url`,
 * nunca do `.env` global. Nunca logar `appPassword`.
 */
final class WordPressConfig
{
    public readonly string $baseUrl;

    public function __construct(
        string $baseUrl,
        public readonly string $username,
        public readonly string $appPassword,
        public readonly int $timeoutSeconds = 20,
    ) {
        $this->baseUrl = rtrim(trim($baseUrl), '/');
    }

    /** URL de um endpoint da REST API (`wp/v2/...`). */
    public function restUrl(string $path): string
    {
        return $this->baseUrl . '/wp-json/' . ltrim($path, '/');
    }

    /** Cabeçalho Authorization Basic (Application Password). */
    public function authorizationHeader(): string
    {
        return 'Authorization: Basic ' . base64_encode($this->username . ':' . $this->appPassword);
    }
}

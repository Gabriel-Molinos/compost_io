<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use App\Support\CaBundle;

/**
 * Cliente HTTP de baixo nível para a REST API do WordPress de um site
 * (ADR-005). Só sabe fazer requisições autenticadas e devolver o JSON
 * decodificado — a lógica de publicação/agendamento é das camadas de serviço.
 *
 * Sem dependência externa: extensão `curl` nativa (ADR-002).
 * A fatia 7.2 amplia esta classe (criar/atualizar posts, mídia, categorias).
 */
final class WordPressClient
{
    public function __construct(private readonly WordPressConfig $config)
    {
        if (!function_exists('curl_init')) {
            throw new WordPressException('Extensão PHP `curl` não está habilitada.');
        }
    }

    /**
     * Verifica a credencial: `GET wp/v2/users/me`. Retorna os dados do usuário
     * autenticado (id, name, slug, capabilities...) ou lança WordPressException.
     *
     * @return array<string, mixed>
     */
    public function verifyConnection(): array
    {
        return $this->get('wp/v2/users/me', ['context' => 'edit']);
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $url = $this->config->restUrl($path);
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => $this->config->timeoutSeconds,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                $this->config->authorizationHeader(),
            ],
        ]);
        $caBundle = CaBundle::path();
        if (is_file($caBundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new WordPressException("Falha de rede ao chamar o WordPress: {$error} (curl {$errno}).", retryable: true);
        }

        $decoded = json_decode((string) $raw, true);

        if ($status < 200 || $status >= 300) {
            $apiMessage = is_array($decoded) ? ($decoded['message'] ?? 'erro desconhecido') : 'resposta não-JSON';
            $hint = match ($status) {
                401     => ' — usuário ou Application Password incorretos.',
                403     => ' — o usuário não tem permissão (precisa poder editar posts).',
                404     => ' — a REST API não respondeu neste endereço (confira a URL do site).',
                default => '',
            };
            throw new WordPressException(
                "WordPress retornou HTTP {$status}: {$apiMessage}{$hint}",
                retryable: $status === 429 || $status >= 500,
                httpStatus: $status,
            );
        }

        if (!is_array($decoded)) {
            throw new WordPressException("Resposta do WordPress não é JSON (HTTP {$status}). O endereço aponta para um WordPress?", httpStatus: $status);
        }

        return $decoded;
    }
}

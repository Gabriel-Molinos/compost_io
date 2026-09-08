<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use App\Support\CaBundle;

/**
 * Cliente HTTP de baixo nível para a REST API do WordPress de um site
 * (ADR-005, integracoes.md §37). Só sabe fazer requisições autenticadas e
 * devolver o JSON decodificado — não conhece agendamento nem regras editoriais
 * (isso é das camadas de serviço da Fase 7).
 *
 * Sem dependência externa: extensão `curl` nativa (ADR-002).
 */
final class WordPressClient
{
    public function __construct(private readonly WordPressConfig $config)
    {
        if (!function_exists('curl_init')) {
            throw new WordPressException('Extensão PHP `curl` não está habilitada.');
        }
    }

    // --- Verificação -------------------------------------------------------

    /**
     * Verifica a credencial: `GET wp/v2/users/me`. Retorna os dados do usuário
     * autenticado ou lança WordPressException.
     *
     * @return array<string, mixed>
     */
    public function verifyConnection(): array
    {
        return $this->request('GET', 'wp/v2/users/me', query: ['context' => 'edit']);
    }

    // --- Posts ------------------------------------------------------------

    /**
     * Cria um post. `$data` aceita as chaves da REST API: title, content,
     * status (`draft`|`future`|`publish`|`pending`), date (ISO 8601, hora do
     * site), slug, excerpt, categories (list<int>), tags, featured_media (int).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed> o post criado (inclui `id`, `link`, `status`)
     */
    public function createPost(array $data): array
    {
        return $this->request('POST', 'wp/v2/posts', body: $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updatePost(int $postId, array $data): array
    {
        return $this->request('POST', 'wp/v2/posts/' . $postId, body: $data);
    }

    /** @return array<string, mixed> */
    public function getPost(int $postId): array
    {
        return $this->request('GET', 'wp/v2/posts/' . $postId, query: ['context' => 'edit']);
    }

    /**
     * Manda o post para a lixeira (`DELETE`). Com `$force` apaga de vez.
     *
     * @return array<string, mixed>
     */
    public function deletePost(int $postId, bool $force = false): array
    {
        return $this->request('DELETE', 'wp/v2/posts/' . $postId, query: $force ? ['force' => 'true'] : []);
    }

    // --- Mídia ----------------------------------------------------------

    /**
     * Faz upload de um arquivo para a media library. Retorna a mídia criada —
     * `id` serve como `featured_media` de um post.
     *
     * @return array<string, mixed>
     */
    public function uploadMedia(string $bytes, string $filename, string $mimeType): array
    {
        return $this->request('POST', 'wp/v2/media', rawBody: $bytes, rawHeaders: [
            'Content-Type: ' . $mimeType,
            'Content-Disposition: attachment; filename="' . self::sanitizeFilename($filename) . '"',
        ]);
    }

    /**
     * Remove um item de mídia. `force` é obrigatório no WordPress para mídia
     * (não vai para a lixeira).
     *
     * @return array<string, mixed>
     */
    public function deleteMedia(int $mediaId): array
    {
        return $this->request('DELETE', 'wp/v2/media/' . $mediaId, query: ['force' => 'true']);
    }

    // --- Taxonomias / autores (insumo da 7.3) ----------------------------

    /**
     * @param array<string, string|int> $query ex.: ['per_page' => 100]
     * @return list<array<string, mixed>>
     */
    public function listCategories(array $query = ['per_page' => 100]): array
    {
        return $this->requestList('GET', 'wp/v2/categories', $query + ['context' => 'edit']);
    }

    /**
     * Usuários com capacidade de autoria. `who=authors` só volta quem pode ter posts.
     *
     * @param array<string, string|int> $query
     * @return list<array<string, mixed>>
     */
    public function listAuthors(array $query = ['per_page' => 100, 'who' => 'authors']): array
    {
        return $this->requestList('GET', 'wp/v2/users', $query + ['context' => 'edit']);
    }

    /**
     * Primeiro post ou página com aquele slug (qualquer status), ou null.
     *
     * @return array<string, mixed>|null
     */
    public function findContentBySlug(string $slug): ?array
    {
        foreach (['wp/v2/posts', 'wp/v2/pages'] as $type) {
            $rows = $this->requestList('GET', $type, [
                'slug'     => $slug,
                'status'   => 'publish,future,draft,pending,private',
                '_fields'  => 'id,link,status,slug',
                'per_page' => 1,
            ]);
            if ($rows !== []) {
                return $rows[0];
            }
        }

        return null;
    }

    // --- Núcleo HTTP ----------------------------------------------------

    /**
     * @param array<string, string|int>  $query
     * @param array<string, mixed>|null   $body      corpo JSON
     * @param list<string>                $rawHeaders headers extras (upload de mídia)
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        ?string $rawBody = null,
        array $rawHeaders = [],
    ): array {
        $decoded = $this->send($method, $path, $query, $body, $rawBody, $rawHeaders);

        if (!is_array($decoded)) {
            throw new WordPressException('Resposta do WordPress não é um objeto JSON.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, string|int> $query
     * @return list<array<string, mixed>>
     */
    private function requestList(string $method, string $path, array $query): array
    {
        $decoded = $this->send($method, $path, $query, null, null, []);

        if (!is_array($decoded) || array_is_list($decoded) === false) {
            throw new WordPressException("Esperava uma lista de {$path}, veio outra coisa.");
        }

        /** @var list<array<string, mixed>> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, string|int> $query
     * @param array<string, mixed>|null  $body
     * @param list<string>               $rawHeaders
     * @return mixed JSON decodificado
     */
    private function send(string $method, string $path, array $query, ?array $body, ?string $rawBody, array $rawHeaders): mixed
    {
        $url = $this->config->restUrl($path);
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = ['Accept: application/json', $this->config->authorizationHeader()];
        $payload = $rawBody;

        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                throw new WordPressException('Não foi possível serializar o corpo da requisição.');
            }
            $headers[] = 'Content-Type: application/json';
        }

        foreach ($rawHeaders as $h) {
            $headers[] = $h;
        }

        $result = $this->exec($method, $url, $headers, $payload);

        // Redirect (bug real reportado: site com Cloudflare/host redirecionando
        // o domínio puro pro "www" — penazo.com -> www.penazo.com — fazia todo
        // sync falhar com "resposta não-JSON", já que a URL configurada nunca
        // era a de verdade). Não segue redirect automaticamente via cURL
        // (CURLOPT_FOLLOWLOCATION reenviaria a Application Password pra
        // QUALQUER host que o Location apontasse, inclusive um terceiro em
        // caso de DNS/host comprometido) — em vez disso, refaz a MESMA
        // chamada (mesmo método/corpo) uma única vez, só pro destino exato
        // que o próprio servidor indicou. Um hop já cobre o caso real; se
        // redirecionar de novo depois disso, o erro abaixo aparece normal.
        if (in_array($result['status'], [301, 302, 303, 307, 308], true) && $result['redirect'] !== '') {
            $result = $this->exec($method, $result['redirect'], $headers, $payload);
        }

        $status = $result['status'];
        $raw = $result['body'];
        $decoded = json_decode($raw, true);

        if ($status < 200 || $status >= 300) {
            $apiMessage = is_array($decoded) ? ($decoded['message'] ?? 'erro desconhecido') : 'resposta não-JSON';
            $hint = match ($status) {
                401     => ' — usuário ou Application Password incorretos.',
                403     => ' — o usuário não tem permissão para esta operação.',
                404     => ' — recurso ou REST API não encontrados (confira a URL do site).',
                default => '',
            };
            throw new WordPressException(
                "WordPress retornou HTTP {$status}: {$apiMessage}{$hint}",
                retryable: $status === 429 || $status >= 500,
                httpStatus: $status,
            );
        }

        if ($decoded === null && trim($raw) !== '') {
            throw new WordPressException("Resposta do WordPress não é JSON (HTTP {$status}). O endereço aponta para um WordPress?", httpStatus: $status);
        }

        return $decoded;
    }

    /**
     * Uma chamada cURL isolada — reaproveitada pra requisição original e,
     * quando houver, pro único hop de redirect que `send()` segue.
     *
     * @param list<string> $headers
     * @return array{status:int, body:string, redirect:string}
     */
    private function exec(string $method, string $url, array $headers, ?string $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => $this->config->timeoutSeconds,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $caBundle = CaBundle::path();
        if (is_file($caBundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        // Populado mesmo com FOLLOWLOCATION desligado — é pra isso que existe
        // (doc do libcurl: "especially useful in combination with
        // CURLOPT_FOLLOWLOCATION being disabled").
        $redirect = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        if ($errno !== 0) {
            throw new WordPressException("Falha de rede ao chamar o WordPress: {$error} (curl {$errno}).", retryable: true);
        }

        return ['status' => $status, 'body' => (string) $raw, 'redirect' => $redirect];
    }

    private static function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'upload';

        return trim($name, '-') ?: 'upload';
    }
}

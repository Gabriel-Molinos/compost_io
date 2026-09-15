<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use DOMDocument;
use DOMElement;

/**
 * Resolve os links internos que a IA escreve no corpo do artigo (fluxo-editorial
 * §31). A escrita costuma "chutar" caminhos como `/blog/algum-slug` que podem não
 * existir no site, ou usar `?p=ID` (formato que `PromptBuilder::internalLinksLayer()`
 * pede de propósito — funciona em qualquer estrutura de permalink). Antes de publicar:
 *
 *   - link interno cujo slug OU `?p=ID`/`?page_id=ID` bate com um post/página
 *     publicado de verdade no WordPress → vira o permalink real;
 *   - link interno sem correspondência (post não existe, ou existe mas não está
 *     `publish` — foi apagado/despublicado depois, achado real 2026-09-14) →
 *     o texto fica, o `<a>` sai (sem 404);
 *   - links externos, `mailto:`, `tel:` e âncoras `#` → intocados.
 */
final class InternalLinkResolver
{
    private readonly ?string $siteHost;

    /** @var array<string, array<string,mixed>|null> */
    private array $cache = [];

    public function __construct(
        private readonly WordPressClient $client,
        string $siteBaseUrl,
    ) {
        $host = parse_url($siteBaseUrl, PHP_URL_HOST);
        $this->siteHost = is_string($host) ? strtolower($host) : null;
    }

    /**
     * @return array{html:string, rewritten:int, unwrapped:int}
     */
    public function resolve(string $html): array
    {
        if (trim($html) === '' || stripos($html, '<a') === false) {
            return ['html' => $html, 'rewritten' => 0, 'unwrapped' => 0];
        }

        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root__">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('__root__');
        if ($root === null) {
            return ['html' => $html, 'rewritten' => 0, 'unwrapped' => 0];
        }

        $rewritten = 0;
        $unwrapped = 0;

        foreach (iterator_to_array($doc->getElementsByTagName('a')) as $a) {
            if (!$a instanceof DOMElement) {
                continue;
            }

            $href = trim($a->getAttribute('href'));
            if (!$this->isInternal($href)) {
                continue; // externo / âncora / mailto — não mexe
            }

            $postId = $this->postIdFromQuery($href);
            $match = $postId !== null ? $this->lookupById($postId) : $this->lookupBySlug($this->slugFromPath($href));
            if ($match !== null && !empty($match['link'])) {
                $a->setAttribute('href', (string) $match['link']);
                $a->removeAttribute('target');
                $a->removeAttribute('rel');
                $rewritten++;
                continue;
            }

            while ($a->firstChild !== null) {
                $a->parentNode?->insertBefore($a->firstChild, $a);
            }
            $a->parentNode?->removeChild($a);
            $unwrapped++;
        }

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return ['html' => trim($out), 'rewritten' => $rewritten, 'unwrapped' => $unwrapped];
    }

    /** Se o href é navegável dentro do próprio site (não externo/âncora/mailto/tel). */
    private function isInternal(string $href): bool
    {
        if ($href === '' || str_starts_with($href, '#')
            || preg_match('#^(mailto:|tel:)#i', $href)) {
            return false;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);
        if ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return false;
        }

        if ($scheme !== null) {
            $host = parse_url($href, PHP_URL_HOST);
            if (!is_string($host) || $this->siteHost === null || strtolower($host) !== $this->siteHost) {
                return false; // link externo
            }
        }

        return true;
    }

    /**
     * `?p=123` ou `?page_id=123` — formato que `PromptBuilder::internalLinksLayer()`
     * pede pra IA usar, independente da estrutura de permalink do site.
     */
    private function postIdFromQuery(string $href): ?int
    {
        $query = parse_url($href, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return null;
        }
        parse_str($query, $params);
        foreach (['p', 'page_id'] as $key) {
            if (isset($params[$key]) && is_string($params[$key]) && ctype_digit($params[$key])) {
                return (int) $params[$key];
            }
        }

        return null;
    }

    /** Slug de um href interno (último segmento do caminho), ou null se não der pra extrair. */
    private function slugFromPath(string $href): ?string
    {
        $path = parse_url($href, PHP_URL_PATH);
        if (!is_string($path)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn ($s) => $s !== ''));
        $slug = end($segments) ?: '';
        $slug = rawurldecode($slug);

        return preg_match('/^[A-Za-z0-9._%-]+$/', $slug) ? strtolower($slug) : null;
    }

    /** @return array<string,mixed>|null */
    private function lookupBySlug(?string $slug): ?array
    {
        if ($slug === null) {
            return null;
        }
        $key = 'slug:' . $slug;
        if (!array_key_exists($key, $this->cache)) {
            try {
                $this->cache[$key] = $this->client->findContentBySlug($slug);
            } catch (WordPressException) {
                $this->cache[$key] = null;
            }
        }

        return $this->cache[$key];
    }

    /**
     * @return array<string,mixed>|null null também quando o post existe mas não
     * está `publish` — foi apagado/despublicado depois de outro passo ter
     * guardado esse id (achado real 2026-09-14, post existia quando o artigo
     * que linka pra ele foi gerado, mas foi deletado no WordPress depois).
     */
    private function lookupById(int $postId): ?array
    {
        $key = 'id:' . $postId;
        if (!array_key_exists($key, $this->cache)) {
            try {
                $post = $this->client->getPost($postId);
                $this->cache[$key] = ($post['status'] ?? null) === 'publish' ? $post : null;
            } catch (WordPressException) {
                $this->cache[$key] = null;
            }
        }

        return $this->cache[$key];
    }
}

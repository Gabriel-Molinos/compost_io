<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use DOMDocument;
use DOMElement;

/**
 * Resolve os links internos que a IA escreve no corpo do artigo (fluxo-editorial
 * §31). A escrita costuma "chutar" caminhos como `/blog/algum-slug` que podem não
 * existir no site. Antes de publicar:
 *
 *   - link interno cujo slug bate com um post/página do WordPress → vira o
 *     permalink real;
 *   - link interno sem correspondência → o texto fica, o `<a>` sai (sem 404);
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

            $slug = $this->internalSlug(trim($a->getAttribute('href')));
            if ($slug === null) {
                continue; // externo / âncora / mailto — não mexe
            }

            $match = $this->lookup($slug);
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

    /** Slug de um href interno, ou null se o link não for interno/navegável. */
    private function internalSlug(string $href): ?string
    {
        if ($href === '' || str_starts_with($href, '#')
            || preg_match('#^(mailto:|tel:)#i', $href)) {
            return null;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);
        if ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        if ($scheme !== null) {
            $host = parse_url($href, PHP_URL_HOST);
            if (!is_string($host) || $this->siteHost === null || strtolower($host) !== $this->siteHost) {
                return null; // link externo
            }
        }

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
    private function lookup(string $slug): ?array
    {
        if (!array_key_exists($slug, $this->cache)) {
            try {
                $this->cache[$slug] = $this->client->findContentBySlug($slug);
            } catch (WordPressException) {
                $this->cache[$slug] = null;
            }
        }

        return $this->cache[$slug];
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitiza HTML vindo da IA antes de guardar/exibir (o corpo do artigo é HTML
 * por natureza, então não dá para só escapar). Allowlist de tags/atributos;
 * remove `<script>`/`<style>`, handlers `on*` e URLs `javascript:`.
 *
 * Não é um sanitizador de propósito geral — cobre o subconjunto que os prompts
 * de `docs/ai/writing.md` pedem (headings, parágrafos, listas, links, tabelas).
 */
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 'blockquote',
        'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'figure', 'figcaption',
        'img', 'code', 'pre', 'span', 'div', 'section',
    ];

    private const ALLOWED_ATTRS = [
        'a'   => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td'  => ['colspan', 'rowspan'],
        'th'  => ['colspan', 'rowspan', 'scope'],
    ];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
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
            return '';
        }

        self::walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form'], true)) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            self::walk($child);

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // Tag não permitida: mantém o conteúdo, descarta a tag.
                while ($child->firstChild !== null) {
                    $child->parentNode?->insertBefore($child->firstChild, $child);
                }
                $child->parentNode?->removeChild($child);
                continue;
            }

            self::cleanAttributes($child, $tag);
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];

        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $name = strtolower($attr->nodeName);

            if (!in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }

            if (($name === 'href' || $name === 'src') && self::isDangerousUrl($attr->nodeValue ?? '')) {
                $el->removeAttribute($attr->nodeName);
            }
        }

        // Links externos abrem em nova aba com rel seguro (seo.md).
        if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function isDangerousUrl(string $url): bool
    {
        $url = trim($url);

        return (bool) preg_match('#^\s*(javascript|data|vbscript)\s*:#i', $url);
    }
}

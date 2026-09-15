<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMText;
use DOMXPath;

/**
 * Edita UM `<a href>` específico dentro de um HTML de artigo (Central de
 * Links, achado real 2026-09-10: o redator precisa corrigir um link pontual
 * sem editar o HTML inteiro). Mesma técnica DOM de
 * `App\Integrations\WordPress\ExternalLinkVerifier`/`InternalLinkResolver`
 * (`DOMDocument` + `libxml_use_internal_errors`), só que mirando um único
 * href em vez de varrer todos.
 */
final class HtmlLinks
{
    /** Remove o `<a>` daquele href — mantém o texto, nunca some com a citação. */
    public static function unwrap(string $html, string $href): array
    {
        return self::edit($html, $href, static function (DOMElement $a): void {
            while ($a->firstChild !== null) {
                $a->parentNode?->insertBefore($a->firstChild, $a);
            }
            $a->parentNode?->removeChild($a);
        });
    }

    /** Troca só o atributo `href` daquele link — texto/âncora continuam como estavam. */
    public static function replaceHref(string $html, string $oldHref, string $newHref): array
    {
        return self::edit($html, $oldHref, static function (DOMElement $a) use ($newHref): void {
            $a->setAttribute('href', $newHref);
        });
    }

    /**
     * Transforma a PRIMEIRA ocorrência literal de `$needle` (texto plano) num
     * link de verdade — usado pra sugestão de link interno retroativo
     * (Central de Links: artigo antigo ganhando um link pro artigo novo).
     * Só mexe em texto fora de qualquer `<a>` já existente — nunca aninha
     * link dentro de link. Não achou o texto → `changed: false`, quem chama
     * descarta a sugestão em vez de forçar (mesmo princípio dos outros dois
     * métodos: nunca inventa, só age sobre o que existe de verdade).
     *
     * @return array{html:string, changed:bool}
     */
    public static function wrapFirstOccurrence(string $html, string $needle, string $href): array
    {
        $needle = trim($needle);
        if ($needle === '' || trim($html) === '') {
            return ['html' => $html, 'changed' => false];
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
            return ['html' => $html, 'changed' => false];
        }

        $xpath = new DOMXPath($doc);
        $textNodes = $xpath->query('.//text()[not(ancestor::a)]', $root);

        $changed = false;
        if ($textNodes !== false) {
            foreach ($textNodes as $node) {
                if (!$node instanceof DOMText) {
                    continue;
                }
                $pos = mb_stripos($node->data, $needle);
                if ($pos === false) {
                    continue;
                }

                $before = mb_substr($node->data, 0, $pos);
                $match = mb_substr($node->data, $pos, mb_strlen($needle));
                $after = mb_substr($node->data, $pos + mb_strlen($needle));

                $parent = $node->parentNode;
                if ($parent === null) {
                    continue;
                }

                $anchor = $doc->createElement('a');
                $anchor->setAttribute('href', $href);
                $anchor->appendChild($doc->createTextNode($match));

                if ($before !== '') {
                    $parent->insertBefore($doc->createTextNode($before), $node);
                }
                $parent->insertBefore($anchor, $node);
                if ($after !== '') {
                    $parent->insertBefore($doc->createTextNode($after), $node);
                }
                $parent->removeChild($node);

                $changed = true;
                break;
            }
        }

        if (!$changed) {
            return ['html' => $html, 'changed' => false];
        }

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return ['html' => trim($out), 'changed' => true];
    }

    /**
     * Conta links internos vs externos no corpo — usado pelo checklist de
     * pré-aprovação (`ArticleReviewService::checklist()`, achado real
     * 2026-09-15: artigos bloqueados em compliance/SEO por fugir de 3-5
     * links internos / máx. 2 externos, mas nada barrava a aprovação de
     * verdade antes disso). Mesmo critério de host do
     * `InternalLinkResolver`: sem host (link relativo) ou host igual ao do
     * site é interno; qualquer outro host é externo.
     *
     * @return array{internal: int, external: int}
     */
    public static function countByType(string $html, ?string $siteHost): array
    {
        $counts = ['internal' => 0, 'external' => 0];
        if (trim($html) === '' || stripos($html, '<a') === false) {
            return $counts;
        }

        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root__">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $siteHost = $siteHost !== null ? strtolower($siteHost) : null;

        foreach ($doc->getElementsByTagName('a') as $a) {
            if (!$a instanceof DOMElement) {
                continue;
            }
            $href = trim($a->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            $host = parse_url($href, PHP_URL_HOST);
            $host = is_string($host) ? strtolower($host) : null;

            if ($host === null || $host === $siteHost) {
                $counts['internal']++;
            } else {
                $counts['external']++;
            }
        }

        return $counts;
    }

    /** @return array{html:string, changed:bool} */
    private static function edit(string $html, string $targetHref, callable $mutate): array
    {
        if (trim($html) === '' || stripos($html, '<a') === false) {
            return ['html' => $html, 'changed' => false];
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
            return ['html' => $html, 'changed' => false];
        }

        $changed = false;
        foreach (iterator_to_array($doc->getElementsByTagName('a')) as $a) {
            if (!$a instanceof DOMElement || trim($a->getAttribute('href')) !== $targetHref) {
                continue;
            }
            $mutate($a);
            $changed = true;
            break; // só o primeiro — se a mesma URL repetir, o redator resolve uma vez por vez
        }

        if (!$changed) {
            return ['html' => $html, 'changed' => false];
        }

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return ['html' => trim($out), 'changed' => true];
    }
}

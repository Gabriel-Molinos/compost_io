<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use DOMDocument;
use DOMElement;

/**
 * Insere as imagens de corpo (já enviadas à media library) no HTML do artigo,
 * distribuídas entre as seções `<h2>`, na ordem em que foram geradas
 * (decisão do usuário — Fase 7.5). Sem depender do `placement` da IA.
 *
 * Imagens além do número de seções (ou artigos sem `<h2>`) vão para o fim.
 */
final class BodyImageInjector
{
    /**
     * @param list<array{src:string, alt:string}> $images
     */
    public static function inject(string $html, array $images): string
    {
        $images = array_values(array_filter($images, static fn ($i) => trim($i['src'] ?? '') !== ''));
        if ($images === [] || trim($html) === '') {
            return $html;
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
            return $html;
        }

        /** @var list<DOMElement> $headings */
        $headings = [];
        foreach ($doc->getElementsByTagName('h2') as $h) {
            if ($h instanceof DOMElement) {
                $headings[] = $h;
            }
        }

        // Qualquer <img> que já venha no corpo também recebe width:100% (não quebrar layout).
        foreach ($doc->getElementsByTagName('img') as $existing) {
            if ($existing instanceof DOMElement && $existing->getAttribute('style') === '') {
                $existing->setAttribute('style', 'width:100%;height:auto;display:block');
                $existing->removeAttribute('width');
                $existing->removeAttribute('height');
            }
        }

        $count = count($images);
        // Posições candidatas: antes de cada h2 exceto o primeiro (não colar no topo).
        $candidates = array_slice($headings, 1);
        $slots = count($candidates);

        $used = -1;
        foreach ($images as $i => $image) {
            $figure = self::figure($doc, $image['src'], $image['alt'] ?? '');

            $target = null;
            if ($slots > 0) {
                $idx = (int) floor(($i + 1) * ($slots + 1) / ($count + 1)) - 1;
                $idx = max(0, min($slots - 1, $idx));
                if ($idx <= $used) {
                    $idx = $used + 1;
                }
                if ($idx < $slots) {
                    $used = $idx;
                    $target = $candidates[$idx];
                }
            }

            if ($target !== null) {
                $target->parentNode?->insertBefore($figure, $target);
            } else {
                $root->appendChild($figure);
            }
        }

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function figure(DOMDocument $doc, string $src, string $alt): DOMElement
    {
        $figure = $doc->createElement('figure');
        $figure->setAttribute('class', 'wp-block-image size-large');
        $figure->setAttribute('style', 'margin:1.5rem 0;max-width:100%');

        $img = $doc->createElement('img');
        $img->setAttribute('src', $src);
        $img->setAttribute('alt', $alt);
        $img->setAttribute('loading', 'lazy');
        // width:100% para a imagem não estourar o layout do tema (pedido do usuário).
        $img->setAttribute('style', 'width:100%;height:auto;display:block');
        $figure->appendChild($img);

        if (trim($alt) !== '') {
            $caption = $doc->createElement('figcaption');
            $caption->appendChild($doc->createTextNode($alt));
            $figure->appendChild($caption);
        }

        return $figure;
    }
}

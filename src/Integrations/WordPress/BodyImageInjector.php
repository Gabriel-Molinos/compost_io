<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use DOMDocument;
use DOMElement;

/**
 * Insere as imagens de corpo (já enviadas à media library) no HTML do artigo,
 * distribuídas entre as seções `<h2>`, na ORDEM DADA (decisão do usuário —
 * Fase 7.5; a partir de 2026-09-28 essa ordem é escolhida pelo Redator-Chefe
 * via `ImageService::reorderBody()`, antes disso era sempre a ordem de
 * geração). Sem depender do `placement` da IA.
 *
 * Imagens além do número de seções (ou artigos sem `<h2>`) vão para o fim.
 */
final class BodyImageInjector
{
    /**
     * Pra qual seção (índice 0-based entre os candidatos — todo `<h2>` menos o
     * 1º) cada imagem, na ordem dada, vai ANTES — `null` = vai pro fim do
     * artigo (mais imagens que seção, ou artigo sem `<h2>` o bastante).
     * Distribuição uniforme (mesmo espaçamento entre imagens). Extraído de
     * `inject()` pra ser a MESMA conta usada na prévia que o Redator-Chefe vê
     * na tela (`ProductionController::show()`/`sites/production/show.php`) —
     * nunca duas implementações da mesma matemática podendo divergir.
     *
     * @return list<int|null> um item por imagem, na mesma ordem de entrada
     */
    public static function assignSlots(int $imageCount, int $slotCount): array
    {
        $used = -1;
        $out = [];
        for ($i = 0; $i < $imageCount; $i++) {
            $idx = null;
            if ($slotCount > 0) {
                $candidate = (int) floor(($i + 1) * ($slotCount + 1) / ($imageCount + 1)) - 1;
                $candidate = max(0, min($slotCount - 1, $candidate));
                if ($candidate <= $used) {
                    $candidate = $used + 1;
                }
                if ($candidate < $slotCount) {
                    $used = $candidate;
                    $idx = $candidate;
                }
            }
            $out[] = $idx;
        }

        return $out;
    }

    /**
     * @param list<array{src:string, alt:string}> $images
     */
    public static function inject(string $html, array $images): string
    {
        $images = array_values(array_filter($images, static fn ($i) => trim($i['src'] ?? '') !== ''));

        return self::place($html, $images, static fn (DOMDocument $doc, array $image): DOMElement => self::figure($doc, $image['src'], $image['alt'] ?? ''));
    }

    /**
     * Mesma distribuição de `inject()`, mas pra prévia INTERATIVA que o Redator-Chefe
     * vê na tela (`sites/production/show.php`) — cada `<figure>` carrega `data-image-id`
     * e fica arrastável (`draggable`), pra `image-reorder.js` reordenar direto ali dentro
     * do corpo (pedido do responsável 2026-09-28: "tem que aparecer no corpo do post e
     * poder arrastar pra mudar", não uma lista à parte com texto dizendo onde vai ficar).
     * `inject()` continua sendo a versão limpa usada na publicação de verdade
     * (`WordPressPublishService`) — HTML sem nada de chrome de editor.
     *
     * @param list<array{id:int, src:string, alt:string}> $images
     */
    public static function injectForPreview(string $html, array $images): string
    {
        $images = array_values(array_filter($images, static fn ($i) => trim($i['src'] ?? '') !== ''));

        return self::place($html, $images, static fn (DOMDocument $doc, array $image): DOMElement => self::previewFigure($doc, (int) $image['id'], $image['src'], $image['alt'] ?? ''));
    }

    /**
     * @param list<array{src:string, alt:string}> $images
     * @param callable(DOMDocument, array{src:string, alt:string}): DOMElement $buildFigure
     */
    private static function place(string $html, array $images, callable $buildFigure): string
    {
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

        // Posições candidatas: antes de cada h2 exceto o primeiro (não colar no topo).
        $candidates = array_slice($headings, 1);
        $slotIndexes = self::assignSlots(count($images), count($candidates));

        foreach ($images as $i => $image) {
            $figure = $buildFigure($doc, $image);
            $idx = $slotIndexes[$i];

            if ($idx !== null) {
                $candidates[$idx]->parentNode?->insertBefore($figure, $candidates[$idx]);
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

    /** Mesma figura, mais o gancho de arrastar (`image-reorder.js`) — ver `injectForPreview()`. */
    private static function previewFigure(DOMDocument $doc, int $imageId, string $src, string $alt): DOMElement
    {
        $figure = self::figure($doc, $src, $alt);
        $figure->setAttribute('class', trim($figure->getAttribute('class') . ' body-image-preview'));
        $figure->setAttribute('data-image-id', (string) $imageId);
        $figure->setAttribute('draggable', 'true');

        return $figure;
    }
}

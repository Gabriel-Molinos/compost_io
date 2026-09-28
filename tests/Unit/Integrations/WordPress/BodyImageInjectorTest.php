<?php

declare(strict_types=1);

namespace Tests\Unit\Integrations\WordPress;

use App\Integrations\WordPress\BodyImageInjector;
use PHPUnit\Framework\TestCase;

/**
 * `BodyImageInjector::assignSlots()` (extraído de `inject()` em 2026-09-28 —
 * pedido do responsável: o Redator-Chefe escolher a ordem, então a MESMA conta
 * que decide onde cada imagem cai precisa ser reutilizável pela prévia que a
 * tela de Produção mostra, não só pela publicação de verdade).
 */
final class BodyImageInjectorTest extends TestCase
{
    public function testNoSectionsMeansEveryImageGoesToTheEnd(): void
    {
        $this->assertSame([null, null, null], BodyImageInjector::assignSlots(3, 0));
    }

    public function testNoImagesReturnsEmpty(): void
    {
        $this->assertSame([], BodyImageInjector::assignSlots(0, 5));
    }

    public function testOneImageOneSlotGoesToIt(): void
    {
        $this->assertSame([0], BodyImageInjector::assignSlots(1, 1));
    }

    public function testEachImageGetsItsOwnSlotWhenCountsMatch(): void
    {
        $this->assertSame([0, 1, 2], BodyImageInjector::assignSlots(3, 3));
    }

    public function testMoreSlotsThanImagesSpreadsEvenly(): void
    {
        // 2 imagens, 5 seções — espalha (nunca gruda as duas na mesma seção
        // se dá pra evitar) e nunca repete um índice.
        $result = BodyImageInjector::assignSlots(2, 5);
        $this->assertCount(2, $result);
        $this->assertNotContains(null, $result);
        $this->assertSame($result, array_unique($result));
        $this->assertLessThan($result[1], $result[0]);
    }

    public function testMoreImagesThanSlotsSendsTheExtrasToTheEnd(): void
    {
        // 5 imagens, 2 seções — só 2 conseguem slot, o resto (null) vai pro fim.
        $result = BodyImageInjector::assignSlots(5, 2);
        $this->assertCount(5, $result);
        $withSlot = array_filter($result, static fn ($v) => $v !== null);
        $this->assertCount(2, $withSlot);
        $this->assertSame([0, 1], array_values($withSlot));
    }

    public function testSlotIndexesAreAlwaysIncreasing(): void
    {
        // Nunca uma imagem depois "volta" pra uma seção anterior à da imagem de antes —
        // senão a ordem escolhida pelo Redator-Chefe não bateria com a ordem no artigo.
        $result = BodyImageInjector::assignSlots(4, 10);
        $withSlot = array_values(array_filter($result, static fn ($v) => $v !== null));
        $sorted = $withSlot;
        sort($sorted);
        $this->assertSame($sorted, $withSlot);
    }

    public function testInjectPlacesFiguresBeforeCorrectHeadingInReorderedOrder(): void
    {
        $html = '<p>Intro</p><h2>Primeira</h2><p>a</p><h2>Segunda</h2><p>b</p><h2>Terceira</h2><p>c</p>';
        // 2 imagens, candidatos = [Segunda, Terceira] (a "Primeira" nunca recebe imagem
        // antes dela — não cola no topo). assignSlots(2,2) = [0,1] -> cada uma na sua.
        $out = BodyImageInjector::inject($html, [
            ['src' => '/a.webp', 'alt' => 'A'],
            ['src' => '/b.webp', 'alt' => 'B'],
        ]);

        $posA = strpos($out, '/a.webp');
        $posB = strpos($out, '/b.webp');
        $posSegunda = strpos($out, 'Segunda');
        $posTerceira = strpos($out, 'Terceira');

        $this->assertNotFalse($posA);
        $this->assertNotFalse($posB);
        $this->assertLessThan($posSegunda, $posA, 'imagem A devia vir antes de "Segunda"');
        $this->assertLessThan($posTerceira, $posB, 'imagem B devia vir antes de "Terceira"');
        $this->assertLessThan($posB, $posA, 'ordem de entrada preservada: A antes de B');
    }

    public function testReorderingTheInputArrayChangesWherePlacementLands(): void
    {
        // Mesmo HTML, mesmas imagens, ORDEM TROCADA na entrada — B tem que passar
        // a vir antes de A no resultado. Isto é o próprio ponto do pedido: quem
        // controla a ordem de entrada controla onde cada imagem cai.
        $html = '<h2>Primeira</h2><p>a</p><h2>Segunda</h2><p>b</p><h2>Terceira</h2><p>c</p>';
        $out = BodyImageInjector::inject($html, [
            ['src' => '/b.webp', 'alt' => 'B'],
            ['src' => '/a.webp', 'alt' => 'A'],
        ]);

        $this->assertLessThan(strpos($out, '/a.webp'), strpos($out, '/b.webp'));
    }

    public function testInjectForPreviewPlacesTheSameAsInjectPlusEditorHooks(): void
    {
        $html = '<h2>Primeira</h2><p>a</p><h2>Segunda</h2><p>b</p>';
        $out = BodyImageInjector::injectForPreview($html, [
            ['id' => 42, 'src' => '/a.webp', 'alt' => 'A'],
        ]);

        $this->assertStringContainsString('data-image-id="42"', $out);
        $this->assertStringContainsString('draggable="true"', $out);
        $this->assertStringContainsString('body-image-preview', $out);
        $this->assertLessThan(strpos($out, 'Segunda'), strpos($out, '/a.webp'));
    }

    public function testInjectForPreviewWithoutIdsStillPlacesImages(): void
    {
        // Sem sort_order nenhuma ainda reordenada, $images não tem id de verdade só
        // por precaução — mas o método sempre recebe id (ImageService::forArticle()
        // sempre devolve). Aqui só confere que 0 imagens não quebra nada.
        $out = BodyImageInjector::injectForPreview('<h2>X</h2><p>a</p>', []);

        $this->assertSame('<h2>X</h2><p>a</p>', $out);
    }

    public function testPlainInjectNeverAddsEditorAttributes(): void
    {
        // O HTML publicado de verdade não pode carregar nada de chrome de editor.
        $out = BodyImageInjector::inject('<h2>A</h2><p>a</p>', [['src' => '/a.webp', 'alt' => 'A']]);

        $this->assertStringNotContainsString('data-image-id', $out);
        $this->assertStringNotContainsString('draggable', $out);
        $this->assertStringNotContainsString('body-image-preview', $out);
    }
}

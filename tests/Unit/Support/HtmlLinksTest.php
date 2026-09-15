<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\HtmlLinks;
use PHPUnit\Framework\TestCase;

final class HtmlLinksTest extends TestCase
{
    public function testCountByTypeClassifiesBySiteHost(): void
    {
        $html = '<p>Veja <a href="https://meusite.com/outro-artigo">este</a> e '
            . '<a href="https://fontes-externas.gov.br/dados">este outro</a>.</p>';

        $counts = HtmlLinks::countByType($html, 'meusite.com');

        $this->assertSame(1, $counts['internal']);
        $this->assertSame(1, $counts['external']);
    }

    public function testCountByTypeTreatsRelativeLinkAsInternal(): void
    {
        $html = '<p><a href="/outro-artigo">link relativo</a></p>';

        $counts = HtmlLinks::countByType($html, 'meusite.com');

        $this->assertSame(1, $counts['internal']);
        $this->assertSame(0, $counts['external']);
    }

    public function testCountByTypeIsCaseInsensitiveOnHost(): void
    {
        $html = '<p><a href="https://MeuSite.com/artigo">link</a></p>';

        $counts = HtmlLinks::countByType($html, 'meusite.com');

        $this->assertSame(1, $counts['internal']);
        $this->assertSame(0, $counts['external']);
    }

    public function testCountByTypeHandlesEmptyHtml(): void
    {
        $counts = HtmlLinks::countByType('', 'meusite.com');

        $this->assertSame(['internal' => 0, 'external' => 0], $counts);
    }

    public function testCountByTypeHandlesHtmlWithoutLinks(): void
    {
        $counts = HtmlLinks::countByType('<p>Sem nenhum link aqui.</p>', 'meusite.com');

        $this->assertSame(['internal' => 0, 'external' => 0], $counts);
    }

    public function testUnwrapRemovesAnchorButKeepsText(): void
    {
        $html = '<p>Veja a <a href="https://exemplo.com/x">fonte original</a> aqui.</p>';

        $result = HtmlLinks::unwrap($html, 'https://exemplo.com/x');

        $this->assertTrue($result['changed']);
        $this->assertStringNotContainsString('<a', $result['html']);
        $this->assertStringContainsString('fonte original', $result['html']);
    }

    public function testUnwrapReportsNoChangeWhenHrefNotFound(): void
    {
        $html = '<p><a href="https://exemplo.com/x">link</a></p>';

        $result = HtmlLinks::unwrap($html, 'https://exemplo.com/outro-que-nao-existe');

        $this->assertFalse($result['changed']);
        $this->assertSame($html, $result['html']);
    }

    public function testReplaceHrefSwapsOnlyTheAttribute(): void
    {
        $html = '<p><a href="https://exemplo.com/velho">texto do link</a></p>';

        $result = HtmlLinks::replaceHref($html, 'https://exemplo.com/velho', 'https://exemplo.com/novo');

        $this->assertTrue($result['changed']);
        $this->assertStringContainsString('https://exemplo.com/novo', $result['html']);
        $this->assertStringContainsString('texto do link', $result['html']);
        $this->assertStringNotContainsString('https://exemplo.com/velho', $result['html']);
    }

    public function testWrapFirstOccurrenceCreatesRealAnchor(): void
    {
        $html = '<p>O guia completo de finanças pessoais ajuda muita gente.</p>';

        $result = HtmlLinks::wrapFirstOccurrence($html, 'guia completo de finanças', 'https://exemplo.com/guia');

        $this->assertTrue($result['changed']);
        $this->assertStringContainsString('<a href="https://exemplo.com/guia">guia completo de finanças</a>', $result['html']);
    }

    public function testWrapFirstOccurrenceDoesNothingWhenTextNotFound(): void
    {
        $html = '<p>Texto qualquer sem relação.</p>';

        $result = HtmlLinks::wrapFirstOccurrence($html, 'trecho que não existe', 'https://exemplo.com/x');

        $this->assertFalse($result['changed']);
        $this->assertSame($html, $result['html']);
    }

    public function testWrapFirstOccurrenceNeverNestsInsideExistingAnchor(): void
    {
        $html = '<p><a href="https://ja-existe.com">um texto sobre finanças aqui</a></p>';

        $result = HtmlLinks::wrapFirstOccurrence($html, 'finanças', 'https://exemplo.com/novo');

        $this->assertFalse($result['changed']);
    }
}

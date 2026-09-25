<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PixelitoGuide;
use PHPUnit\Framework\TestCase;

final class PixelitoGuideTest extends TestCase
{
    /** @return list<string> padrões (regex) das rotas GET declaradas em routes/web.php */
    private function getRoutePatterns(): array
    {
        $source = (string) file_get_contents(__DIR__ . '/../../../routes/web.php');
        preg_match_all("/->add\('GET',\s*'([^']+)'/", $source, $m);
        $this->assertNotEmpty($m[1], 'não achei nenhuma rota GET em routes/web.php');

        // "/sites/{id}/goals" → #^/sites/[^/]+/goals$# (parte fixa escapada, {parâmetro} vira "qualquer coisa sem /")
        return array_map(
            static fn (string $path): string => '#^' . implode(
                '[^/]+',
                array_map(static fn (string $part): string => preg_quote($part, '#'), (array) preg_split('/\{[a-z]+\}/', $path)),
            ) . '$#',
            $m[1],
        );
    }

    public function testEveryEntryHasQuestionAndAnswer(): void
    {
        $count = 0;
        foreach (PixelitoGuide::topics(1) as $topic) {
            $this->assertNotSame('', trim($topic['topic']));
            $this->assertNotEmpty($topic['items'], "assunto \"{$topic['topic']}\" vazio");
            foreach ($topic['items'] as $item) {
                $count++;
                $this->assertNotSame('', trim($item['q']), 'pergunta vazia');
                $this->assertNotSame('', trim($item['a']), "resposta vazia em \"{$item['q']}\"");
                $this->assertLessThanOrEqual(420, mb_strlen($item['a']), "resposta longa demais em \"{$item['q']}\" — o painel é pra respostas curtas");
                if ($item['link'] !== null) {
                    $this->assertNotSame('', trim((string) $item['linkLabel']), "link sem rótulo em \"{$item['q']}\"");
                }
            }
        }
        $this->assertGreaterThanOrEqual(10, $count);
    }

    public function testEveryLinkPointsToARealRoute(): void
    {
        $patterns = $this->getRoutePatterns();

        foreach ([7, null] as $siteId) {
            foreach (PixelitoGuide::topics($siteId) as $topic) {
                foreach ($topic['items'] as $item) {
                    if ($item['link'] === null) {
                        continue;
                    }
                    $this->assertStringNotContainsString('{', $item['link'], "placeholder sem trocar em \"{$item['q']}\"");
                    $found = false;
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $item['link']) === 1) {
                            $found = true;
                            break;
                        }
                    }
                    $this->assertTrue($found, "o link {$item['link']} (\"{$item['q']}\") não bate com nenhuma rota GET");
                }
            }
        }
    }

    public function testWithoutASiteTheSiteLinksFallBackToTheSiteList(): void
    {
        foreach (PixelitoGuide::topics(null) as $topic) {
            foreach ($topic['items'] as $item) {
                $this->assertStringNotContainsString('/sites/', (string) $item['link'], "\"{$item['q']}\" linka pra um site que não existe");
            }
        }

        $goalLinks = [];
        foreach (PixelitoGuide::topics(7) as $topic) {
            foreach ($topic['items'] as $item) {
                if (str_starts_with((string) $item['link'], '/sites/7/')) {
                    $goalLinks[] = $item['link'];
                }
            }
        }
        $this->assertContains('/sites/7/goals/new', $goalLinks);
    }
}

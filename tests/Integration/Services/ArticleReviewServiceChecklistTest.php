<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\ArticleReviewService;
use App\Services\ArticleService;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * `ArticleReviewService::checklist()` contra o banco de dev de verdade —
 * checklist de pré-aprovação (RF-008, categoria + 3-5 links internos + máx.
 * 2 externos). Cria um artigo descartável (+ versão de corpo) em `setUp()`,
 * remove em `tearDown()`. Mesmo padrão de skip-se-banco-fora-do-ar de
 * `SiteSourceServiceTest`.
 */
final class ArticleReviewServiceChecklistTest extends TestCase
{
    private const TEST_SITE_ID = 2;

    private ArticleReviewService $review;
    private ArticleService $articles;
    private ?int $articleId = null;
    private ?string $siteHost = null;

    protected function setUp(): void
    {
        try {
            Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }

        $this->review = new ArticleReviewService();
        $this->articles = new ArticleService();

        $url = Connection::get()->query('SELECT wordpress_url FROM sites WHERE id = ' . self::TEST_SITE_ID)->fetchColumn();
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
        if (!is_string($host)) {
            $this->markTestSkipped('Site de teste sem wordpress_url configurada — não dá pra classificar link interno/externo.');
        }
        $this->siteHost = strtolower((string) $host);
    }

    protected function tearDown(): void
    {
        if ($this->articleId !== null) {
            Connection::get()->prepare('DELETE FROM articles WHERE id = :id')->execute(['id' => $this->articleId]);
        }
    }

    private function createArticle(?int $categoryId): int
    {
        $pdo = Connection::get();
        $pdo->prepare('INSERT INTO articles (site_id, status, category_id) VALUES (:s, :st, :c)')
            ->execute(['s' => self::TEST_SITE_ID, 'st' => 'IN_REVIEW', 'c' => $categoryId]);
        $id = (int) $pdo->lastInsertId();
        $this->articleId = $id;

        return $id;
    }

    public function testFailsAllThreeWhenNoCategoryAndNoLinks(): void
    {
        $id = $this->createArticle(null);
        $this->articles->addVersion($id, '<p>Corpo sem link nenhum.</p>', 10);

        $article = $this->articles->findById($id);
        $checklist = $this->review->checklist($article);

        $this->assertFalse($checklist['category']['ok']);
        $this->assertFalse($checklist['internal_links']['ok'], '0 link interno está fora da faixa 3-5');
        $this->assertTrue($checklist['external_links']['ok'], '0 link externo está dentro do máx. 2');
    }

    public function testPassesAllThreeWithCategoryAndCorrectLinkCounts(): void
    {
        $categoryId = (int) Connection::get()
            ->query('SELECT id FROM categories WHERE site_id = ' . self::TEST_SITE_ID . ' LIMIT 1')
            ->fetchColumn();
        if ($categoryId === 0) {
            $this->markTestSkipped('Site de teste sem nenhuma categoria cadastrada.');
        }

        $id = $this->createArticle($categoryId);
        $internalLinks = str_repeat('<a href="https://' . $this->siteHost . '/artigo-relacionado">interno</a> ', 4);
        $externalLinks = '<a href="https://fonte-externa.gov.br/dados">externo</a>';
        $this->articles->addVersion($id, "<p>{$internalLinks}{$externalLinks}</p>", 500);

        $article = $this->articles->findById($id);
        $checklist = $this->review->checklist($article);

        $this->assertTrue($checklist['category']['ok']);
        $this->assertTrue($checklist['internal_links']['ok'], '4 links internos está dentro da faixa 3-5');
        $this->assertTrue($checklist['external_links']['ok'], '1 link externo está dentro do máx. 2');
        $this->assertTrue(ArticleReviewService::checklistPassed($checklist));
    }

    public function testApproveThrowsWhenChecklistFails(): void
    {
        $id = $this->createArticle(null);
        $this->articles->addVersion($id, '<p>Sem link nenhum, sem categoria.</p>', 10);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Checklist de pré-aprovação não passou/');

        $this->review->approve($id);
    }

    public function testApproveNeverChangesStatusWhenChecklistFails(): void
    {
        $id = $this->createArticle(null);
        $this->articles->addVersion($id, '<p>Sem link nenhum, sem categoria.</p>', 10);

        try {
            $this->review->approve($id);
        } catch (\RuntimeException) {
            // esperado — o que importa pra este teste é o estado depois.
        }

        $article = $this->articles->findById($id);
        $this->assertSame('IN_REVIEW', $article['status'], 'aprovação recusada nunca deveria mudar o status');
    }
}

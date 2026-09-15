<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\ArticleService;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * `ArticleService::allForSite()`/`countsByStatusGroup()`/`firstInReview()`
 * contra o banco de dev de verdade (site "Gavsy", id 2 — já tem histórico
 * real, por isso as asserções são sempre "baseline + delta dos artigos que
 * este teste criou", nunca um número absoluto). Cria artigos descartáveis em
 * `setUp()`, remove em `tearDown()`. Mesmo padrão de skip-se-banco-fora-do-ar
 * de `SiteSourceServiceTest`.
 */
final class ArticleServicePaginationTest extends TestCase
{
    private const TEST_SITE_ID = 2;

    private ArticleService $articles;
    /** @var list<int> */
    private array $createdIds = [];

    protected function setUp(): void
    {
        try {
            Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }

        $this->articles = new ArticleService();
    }

    protected function tearDown(): void
    {
        if ($this->createdIds === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($this->createdIds), '?'));
        Connection::get()
            ->prepare("DELETE FROM articles WHERE id IN ({$placeholders})")
            ->execute($this->createdIds);
    }

    /** @param array<string, mixed> $extra */
    private function createArticle(string $status, array $extra = []): int
    {
        $pdo = Connection::get();
        $columns = array_merge(['site_id' => self::TEST_SITE_ID, 'status' => $status], $extra);
        $names = implode(', ', array_map(static fn (string $c): string => "`{$c}`", array_keys($columns)));
        $placeholders = implode(', ', array_map(static fn (string $c): string => ":{$c}", array_keys($columns)));
        $pdo->prepare("INSERT INTO articles ({$names}) VALUES ({$placeholders})")->execute($columns);
        $id = (int) $pdo->lastInsertId();
        $this->createdIds[] = $id;

        return $id;
    }

    public function testCountsByStatusGroupReflectsNewArticlesByGroup(): void
    {
        $before = $this->articles->countsByStatusGroup(self::TEST_SITE_ID);

        $this->createArticle('PLANNED');       // progress
        $this->createArticle('APPROVED');      // done
        $this->createArticle('BLOCKED');       // attention
        $this->createArticle('DISCARDED');     // discarded

        $after = $this->articles->countsByStatusGroup(self::TEST_SITE_ID);

        $this->assertSame($before['all'] + 4, $after['all']);
        $this->assertSame($before['progress'] + 1, $after['progress']);
        $this->assertSame($before['done'] + 1, $after['done']);
        $this->assertSame($before['attention'] + 1, $after['attention']);
        $this->assertSame($before['discarded'] + 1, $after['discarded']);
    }

    public function testAllForSiteFiltersByStatusGroup(): void
    {
        $id = $this->createArticle('BLOCKED', ['title' => 'Artigo de teste bloqueado']);

        $page = $this->articles->allForSite(self::TEST_SITE_ID, 'attention', 1, 50);

        $ids = array_column($page, 'id');
        $this->assertContains($id, $ids);
        foreach ($page as $article) {
            $this->assertContains($article['status'], ['BLOCKED', 'ERROR'], 'allForSite("attention") só deveria trazer BLOCKED/ERROR');
        }
    }

    public function testAllForSiteRespectsPerPageLimit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->createArticle('PLANNED', ['title' => "Paginação teste {$i}"]);
        }

        $page = $this->articles->allForSite(self::TEST_SITE_ID, 'all', 1, 2);

        $this->assertCount(2, $page);
    }

    public function testFirstInReviewFindsAnyPageRegardlessOfPagination(): void
    {
        $id = $this->createArticle('IN_REVIEW', ['title' => 'Artigo de teste em revisão']);

        $found = $this->articles->firstInReview(self::TEST_SITE_ID);

        $this->assertNotNull($found);
        // Não afirma que É este id (pode já existir outro IN_REVIEW mais
        // recente no site real) — só que a busca funciona e devolve um id válido.
        $this->assertIsInt($found['id']);
    }

    public function testFirstInReviewReturnsNullWhenNoneExists(): void
    {
        // Site isolado sintético (id inexistente de propósito) — sem
        // depender de nenhum artigo real do banco pra garantir "nenhum".
        $found = $this->articles->firstInReview(999999);

        $this->assertNull($found);
    }
}

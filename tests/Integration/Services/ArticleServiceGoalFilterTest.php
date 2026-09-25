<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\ArticleService;
use App\Services\GoalService;
use App\Services\SiteService;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Filtro por meta na Produção (2026-09-25): um id = artigos daquela meta,
 * GOAL_NONE = artigos sem meta, null = todos — e as contagens das abas
 * (que alimentam os badges) têm que bater com a lista filtrada.
 * Apagar o site de teste leva junto metas e artigos (ON DELETE CASCADE).
 */
final class ArticleServiceGoalFilterTest extends TestCase
{
    private ?int $siteId = null;
    private int $goalA;
    private int $goalB;
    private ArticleService $articles;

    protected function setUp(): void
    {
        try {
            Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }
        $this->siteId = (new SiteService())->create([
            'name'     => 'Site de teste FiltroMeta ' . bin2hex(random_bytes(4)),
            'niche'    => 'testes automatizados',
            'language' => 'pt-BR',
            'tone'     => 'neutro',
        ]);
        $goals = new GoalService();
        $this->goalA = $goals->create($this->siteId, '2026-09', 10, null, []);
        $this->goalB = $goals->create($this->siteId, '2026-10', 10, null, []);

        $this->articles = new ArticleService();
        $this->articles->create($this->siteId, $this->goalA);
        $this->articles->create($this->siteId, $this->goalA);
        $this->articles->create($this->siteId, $this->goalB);
        $this->articles->create($this->siteId, null); // sem meta
    }

    protected function tearDown(): void
    {
        if ($this->siteId !== null) {
            Connection::get()->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $this->siteId]);
            $this->siteId = null;
        }
    }

    private function listCount(?int $goalId): int
    {
        return count($this->articles->allForSite($this->siteId, 'all', 1, 50, null, null, null, null, $goalId));
    }

    public function testEachGoalShowsOnlyItsOwnArticles(): void
    {
        $this->assertSame(2, $this->listCount($this->goalA));
        $this->assertSame(1, $this->listCount($this->goalB));
    }

    public function testNoGoalShowsOnlyArticlesWithoutOne(): void
    {
        $this->assertSame(1, $this->listCount(ArticleService::GOAL_NONE));
        foreach ($this->articles->allForSite($this->siteId, 'all', 1, 50, null, null, null, null, ArticleService::GOAL_NONE) as $row) {
            $this->assertNull($row['goal_period'], 'artigo "sem meta" veio com período de meta');
        }
    }

    public function testNoFilterShowsEverything(): void
    {
        $this->assertSame(4, $this->listCount(null));
    }

    public function testCountsAndPaginationAgreeWithTheFilteredList(): void
    {
        foreach ([$this->goalA => 2, $this->goalB => 1, ArticleService::GOAL_NONE => 1] as $goalId => $expected) {
            $this->assertSame($expected, $this->articles->countsByStatusGroup($this->siteId, null, null, null, $goalId)['all'], "badge 'todos' da meta {$goalId}");
            $this->assertSame($expected, $this->articles->countFiltered($this->siteId, 'all', null, null, null, null, $goalId), "total paginado da meta {$goalId}");
        }
        // Todos os artigos de teste começam em PLANNED/IN_PROGRESS — o sub-filtro por status exato também respeita a meta.
        $exact = $this->articles->countsByExactStatus($this->siteId, 'progress', null, null, null, $this->goalA);
        $this->assertSame(2, array_sum($exact));
    }

    public function testAnotherSitesGoalIdMatchesNothingHere(): void
    {
        $this->assertSame(0, $this->listCount(999999999));
    }
}

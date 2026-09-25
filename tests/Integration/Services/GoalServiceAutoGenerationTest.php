<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\ArticleService;
use App\Services\CategoryService;
use App\Services\GoalService;
use App\Services\NotificationService;
use App\Services\SiteService;
use DateTimeImmutable;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Geração automática só com meta (2026-09-25): usa a do mês atual, senão a do
 * PRÓXIMO mês, senão nada (o worker avisa a equipe 1x por dia e pula).
 * Apagar o site de teste leva junto metas, artigos e notificações (ON DELETE CASCADE).
 */
final class GoalServiceAutoGenerationTest extends TestCase
{
    private ?int $siteId = null;

    protected function setUp(): void
    {
        try {
            Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }
        $this->siteId = (new SiteService())->create([
            'name'     => 'Site de teste AutoGen ' . bin2hex(random_bytes(4)),
            'niche'    => 'testes automatizados',
            'language' => 'pt-BR',
            'tone'     => 'neutro',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->siteId !== null) {
            Connection::get()->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $this->siteId]);
            $this->siteId = null;
        }
    }

    public function testCurrentMonthGoalWinsOverNextMonth(): void
    {
        $goals = new GoalService();
        $current = $goals->create($this->siteId, '2026-09', 10, null, []);
        $goals->create($this->siteId, '2026-10', 10, null, []);

        $plan = $goals->findForAutoGeneration($this->siteId, new DateTimeImmutable('2026-09-15'));

        $this->assertNotNull($plan);
        $this->assertSame($current, (int) $plan['goal']['id']);
        $this->assertSame('2026-09', $plan['period']);
        $this->assertFalse($plan['is_next_month']);
    }

    public function testFallsBackToNextMonthWhenCurrentHasNoGoal(): void
    {
        $goals = new GoalService();
        $next = $goals->create($this->siteId, '2026-10', 10, null, []);

        $plan = $goals->findForAutoGeneration($this->siteId, new DateTimeImmutable('2026-09-15'));

        $this->assertNotNull($plan);
        $this->assertSame($next, (int) $plan['goal']['id']);
        $this->assertSame('2026-10', $plan['period']);
        $this->assertTrue($plan['is_next_month']);
    }

    public function testNextMonthAcrossTheYearBoundaryAndOnDay31(): void
    {
        $goals = new GoalService();
        $january = $goals->create($this->siteId, '2027-01', 10, null, []);

        $plan = $goals->findForAutoGeneration($this->siteId, new DateTimeImmutable('2026-12-31'));

        $this->assertNotNull($plan);
        $this->assertSame($january, (int) $plan['goal']['id']);
        $this->assertTrue($plan['is_next_month']);

        // 31/01 + '1 month' pularia fevereiro (viraria 03/03) — tem que dar fevereiro mesmo.
        $february = $goals->create($this->siteId, '2026-02', 10, null, []);
        $plan = $goals->findForAutoGeneration($this->siteId, new DateTimeImmutable('2026-01-31'));
        $this->assertNotNull($plan);
        $this->assertSame($february, (int) $plan['goal']['id']);
    }

    public function testNoGoalInCurrentOrNextMonthMeansNull(): void
    {
        $goals = new GoalService();
        $goals->create($this->siteId, '2026-08', 10, null, []); // passado
        $goals->create($this->siteId, '2026-11', 10, null, []); // 2 meses à frente — não vale

        $this->assertNull($goals->findForAutoGeneration($this->siteId, new DateTimeImmutable('2026-09-15')));
    }

    public function testNextMonthGoalSpreadsCategoriesByGoalNotByCalendarMonth(): void
    {
        $categories = new CategoryService();
        $a = $categories->create($this->siteId, 'Categoria A', null);
        $b = $categories->create($this->siteId, 'Categoria B', null);
        $goals = new GoalService();
        $goalId = $goals->create($this->siteId, '2026-10', 5, null, [$a => 3, $b => 2]);

        // 2 artigos já gerados pra categoria A sob esta meta — criados AGORA, então o created_at
        // NÃO é de 2026-10. Contando por mês daria 0 e escolheria A de novo (o bug "só uma categoria").
        $articles = new ArticleService();
        foreach ([1, 2] as $_) {
            $id = $articles->create($this->siteId, $goalId, 'AUTO');
            Connection::get()->prepare('UPDATE articles SET category_id = :c WHERE id = :id')->execute(['c' => $a, 'id' => $id]);
        }

        $this->assertSame($b, $goals->mostUnderTargetCategory($this->siteId, $goalId, '2026-10', true));
        $this->assertSame($a, $goals->mostUnderTargetCategory($this->siteId, $goalId, '2026-10', false), 'contagem por mês ignora artigos de outro mês');
    }

    public function testMissingGoalNotificationGoesOutOnlyOncePerDay(): void
    {
        $notifications = new NotificationService();
        $type = NotificationService::TYPE_ATTENTION;
        $title = 'Geração automática pausada: falta a meta';

        $this->assertTrue($notifications->notifySiteTeamOncePerDay($this->siteId, $type, $title, 'msg', '/x'));
        $this->assertFalse($notifications->notifySiteTeamOncePerDay($this->siteId, $type, $title, 'msg', '/x'), 'segunda varredura no mesmo dia não repete');
        $this->assertTrue(
            $notifications->notifySiteTeamOncePerDay($this->siteId, $type, 'Outro aviso', 'msg', '/x'),
            'título diferente é outro aviso',
        );
    }
}

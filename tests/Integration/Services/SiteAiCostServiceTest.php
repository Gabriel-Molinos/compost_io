<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\ReportService;
use App\Services\SiteAiCostService;
use App\Services\SiteService;
use DateTimeImmutable;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Achado real 2026-09-28: `ai_executions` só cobre custo de IA POR ARTIGO —
 * Centro de Inteligência (`IntelligenceService`) e sugestão automática de
 * identidade editorial (`EditorialIdentityAnalysisService`) são chamadas
 * pagas sem artigo nenhum, e ficavam fora do orçamento mostrado na Visão
 * Geral. `site_ai_costs` (migration 0029) fecha essa lacuna — este teste
 * cobre o registro (`log()`) e a agregação (`ReportService`).
 */
final class SiteAiCostServiceTest extends TestCase
{
    private ?int $siteId = null;
    private SiteService $sites;
    private SiteAiCostService $costs;

    protected function setUp(): void
    {
        try {
            Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }
        $this->sites = new SiteService();
        $this->costs = new SiteAiCostService();
    }

    protected function tearDown(): void
    {
        if ($this->siteId !== null) {
            Connection::get()->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $this->siteId]);
            $this->siteId = null;
        }
    }

    private function createSite(): int
    {
        $this->siteId = $this->sites->create(['name' => 'Site de teste CustoIA ' . bin2hex(random_bytes(4)), 'language' => 'pt-BR']);

        return $this->siteId;
    }

    public function testLogAndSumForWindow(): void
    {
        $siteId = $this->createSite();
        $period = (new DateTimeImmutable('now'))->format('Y-m');
        $win = [$period . '-01 00:00:00', (new DateTimeImmutable($period . '-01'))->modify('+1 month')->format('Y-m-d H:i:s')];

        $this->assertSame(0.0, $this->costs->sumForWindow($siteId, $win[0], $win[1]));

        $this->costs->log($siteId, SiteAiCostService::SOURCE_INTELLIGENCE_INSIGHT, 1.5);
        $this->costs->log($siteId, SiteAiCostService::SOURCE_EDITORIAL_IDENTITY_SUGGESTION, 0.25);

        $this->assertSame(1.75, $this->costs->sumForWindow($siteId, $win[0], $win[1]));

        $byMonth = $this->costs->sumByMonthForWindow($siteId, $win[0], $win[1]);
        $this->assertSame(1.75, $byMonth[$period] ?? null);
    }

    public function testDoesNotLeakToAnotherSite(): void
    {
        $siteA = $this->createSite();
        $siteB = $this->sites->create(['name' => 'Site de teste CustoIA B ' . bin2hex(random_bytes(4)), 'language' => 'pt-BR']);

        try {
            $this->costs->log($siteA, SiteAiCostService::SOURCE_INTELLIGENCE_INSIGHT, 5.0);

            $period = (new DateTimeImmutable('now'))->format('Y-m');
            $win = [$period . '-01 00:00:00', (new DateTimeImmutable($period . '-01'))->modify('+1 month')->format('Y-m-d H:i:s')];

            $this->assertSame(5.0, $this->costs->sumForWindow($siteA, $win[0], $win[1]));
            $this->assertSame(0.0, $this->costs->sumForWindow($siteB, $win[0], $win[1]));
        } finally {
            Connection::get()->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $siteB]);
        }
    }

    public function testReportServiceCurrentSpendIncludesSiteAiCosts(): void
    {
        // `currentSpend()` é cacheado de verdade (Redis, TTL 120s) por site+período — o
        // teste loga o custo ANTES da única chamada, pra não ler um valor cacheado antigo
        // (site novo a cada teste = chave de cache sempre inédita, então não há como colidir
        // com outro teste, mas colidiria consigo mesmo se chamasse currentSpend() duas vezes).
        $siteId = $this->createSite();
        $period = (new DateTimeImmutable('now'))->format('Y-m');
        $this->costs->log($siteId, SiteAiCostService::SOURCE_EDITORIAL_IDENTITY_SUGGESTION, 3.0);

        $spend = (new ReportService())->currentSpend($siteId, $period);

        $this->assertSame(3.0, $spend['ai_cost']);
    }

    public function testReportServiceMonthlyIncludesSiteAiCosts(): void
    {
        $siteId = $this->createSite();
        $period = (new DateTimeImmutable('now'))->format('Y-m');
        $this->costs->log($siteId, SiteAiCostService::SOURCE_INTELLIGENCE_INSIGHT, 2.25);

        $monthly = (new ReportService())->monthly($siteId, $period);

        $this->assertSame(2.25, $monthly['ai_cost']);
    }
}

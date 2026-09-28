<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

/**
 * Livro-razão de custo de IA por site NÃO ligado a um artigo (migration 0029,
 * achado real 2026-09-28) — `ai_executions` exige `article_id`, mas Centro de
 * Inteligência (`IntelligenceService`) e sugestão automática de identidade
 * editorial (`EditorialIdentityAnalysisService`) são chamadas pagas ao Gemini
 * no nível do SITE. Sem isso, o orçamento de IA da Visão Geral
 * (`ReportService::currentSpend()`/`monthly()`) subestimava o gasto real.
 */
final class SiteAiCostService
{
    public const SOURCE_INTELLIGENCE_INSIGHT = 'intelligence_insight';
    public const SOURCE_EDITORIAL_IDENTITY_SUGGESTION = 'editorial_identity_suggestion';

    public function log(int $siteId, string $source, float $cost): void
    {
        Connection::get()->prepare(
            'INSERT INTO site_ai_costs (site_id, source, cost) VALUES (:s, :src, :c)'
        )->execute(['s' => $siteId, 'src' => $source, 'c' => round($cost, 6)]);
    }

    /** Soma no período [a, b) — mesma janela que `ReportService::windowFor()` monta. */
    public function sumForWindow(int $siteId, string $start, string $end): float
    {
        $stmt = Connection::get()->prepare(
            'SELECT COALESCE(SUM(cost), 0) FROM site_ai_costs
             WHERE site_id = :s AND created_at >= :a AND created_at < :b'
        );
        $stmt->execute(['s' => $siteId, 'a' => $start, 'b' => $end]);

        return (float) $stmt->fetchColumn();
    }

    /** @return array<string, float> "YYYY-MM" => soma — mesmo formato que `ReportService::sumByMonth()` devolve. */
    public function sumByMonthForWindow(int $siteId, string $start, string $end): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(cost) AS total
             FROM site_ai_costs
             WHERE site_id = :s AND created_at >= :a AND created_at < :b
             GROUP BY ym"
        );
        $stmt->execute(['s' => $siteId, 'a' => $start, 'b' => $end]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['ym']] = (float) $row['total'];
        }

        return $out;
    }
}

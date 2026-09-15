<?php

declare(strict_types=1);

namespace App\Services;

use App\Cache\CacheService;
use App\Database\Connection;

/**
 * Controle de custo de IA por site/mês (RF a definir, fluxo-editorial/testes-e-observabilidade §95).
 *
 * Método (decidido): limite = custo médio por artigo × meta de artigos/mês do
 * site × margem de segurança. Com pouco dado real de custo ainda, "custo médio"
 * usa o MAIOR custo por artigo já observado em qualquer site (não uma média de
 * amostra pequena) — mais conservador, e recalcula sozinho conforme mais
 * artigos forem produzidos, sem precisar guardar um número fixo em tabela
 * nova (por isso não há migration nesta fatia).
 *
 * Comportamento ao atingir o limite: só alertar (indicador na Visão Geral do
 * site) — nunca bloquear produção automaticamente (§95).
 */
final class CostBudgetService
{
    /** Margem de segurança sobre o custo estimado (decisão: 1,5x). */
    private const SAFETY_MARGIN = 1.5;

    private CacheService $cache;

    public function __construct(?CacheService $cache = null)
    {
        $this->cache = $cache ?? new CacheService();
    }

    /**
     * Maior custo de IA já registrado para um único artigo, em qualquer site.
     * `null` se ainda não há nenhuma execução de IA com custo no banco.
     *
     * Cacheado (achado real 2026-09-14, ver `docs/technical/cache.md`):
     * agregação global sobre TODA `ai_executions`,
     * chamada em toda visita à Visão Geral de qualquer site + no laço diário
     * do worker. Chave única (não por site — o resultado é o mesmo pra
     * qualquer site que perguntar), TTL 10 min — número que só cresce,
     * usado num alerta suave (nunca bloqueio), então uma folga dessas não
     * tem custo prático.
     */
    public function maxObservedCostPerArticle(): ?float
    {
        return $this->cache->remember('cost:max_observed', 600, function (): ?float {
            $stmt = Connection::get()->query(
                'SELECT MAX(custo_artigo) FROM (
                    SELECT SUM(e.cost) AS custo_artigo
                    FROM ai_executions e
                    WHERE e.cost IS NOT NULL
                    GROUP BY e.article_id
                 ) sub'
            );
            $max = $stmt->fetchColumn();

            return $max !== null && $max !== false ? (float) $max : null;
        });
    }

    /**
     * Situação de custo do site no mês, a partir do relatório já calculado
     * por `ReportService::monthly()` (reaproveita `goal_total` e `ai_cost` —
     * sem consulta nova além do custo máximo observado).
     *
     * `null` quando não dá pra calcular sem inventar número: sem meta definida
     * pro mês, ou nenhum dado real de custo em nenhum site ainda.
     *
     * @param array<string, mixed> $monthlyReport resultado de ReportService::monthly()
     * @return array{spent: float, limit: float, percent: int, over: bool}|null
     */
    public function evaluate(array $monthlyReport): ?array
    {
        $goalTotal = $monthlyReport['goal_total'] ?? null;
        if ($goalTotal === null || $goalTotal <= 0) {
            return null;
        }

        $maxCost = $this->maxObservedCostPerArticle();
        if ($maxCost === null) {
            return null;
        }

        $limit = $maxCost * $goalTotal * self::SAFETY_MARGIN;
        $spent = (float) ($monthlyReport['ai_cost'] ?? 0.0);
        $percent = $limit > 0 ? (int) round(min($spent / $limit, 1.5) * 100) : 0;

        return [
            'spent'   => $spent,
            'limit'   => $limit,
            'percent' => $percent,
            'over'    => $spent >= $limit,
        ];
    }
}

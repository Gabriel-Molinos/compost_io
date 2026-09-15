<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use Throwable;

/**
 * Metas editoriais por período (goals) e a distribuição por categoria
 * (goal_categories). A parte por categoria é sempre reescrita em bloco,
 * dentro de uma transação, para manter meta + distribuição consistentes.
 */
final class GoalService
{
    /**
     * Metas do site, mais recentes primeiro, com o total já alocado por categoria.
     *
     * @return list<array<string, mixed>>
     */
    public function allForSite(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT g.id, g.period, g.total_articles, g.general_guidelines,
                    COALESCE(SUM(gc.target_count), 0) AS allocated,
                    (SELECT COUNT(*) FROM articles a
                      WHERE a.goal_id = g.id AND a.deleted_at IS NULL
                        AND a.status IN ('APPROVED','SCHEDULED','PUBLISHED')) AS approved
             FROM goals g
             LEFT JOIN goal_categories gc ON gc.goal_id = g.id
             WHERE g.site_id = :s
             GROUP BY g.id
             ORDER BY g.period DESC"
        );
        $stmt->execute(['s' => $siteId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $siteId, int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM goals WHERE id = :id AND site_id = :s LIMIT 1'
        );
        $stmt->execute(['id' => $id, 's' => $siteId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Distribuição atual da meta: category_id => target_count.
     *
     * @return array<int, int>
     */
    public function categoryTargets(int $goalId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT category_id, target_count FROM goal_categories WHERE goal_id = :g ORDER BY category_id'
        );
        $stmt->execute(['g' => $goalId]);

        $targets = [];
        foreach ($stmt->fetchAll() as $row) {
            $targets[(int) $row['category_id']] = (int) $row['target_count'];
        }

        return $targets;
    }

    /** A meta do site pro período (AAAA-MM), se existir — usado pela geração automática (bin/worker.php). */
    public function findByPeriod(int $siteId, string $period): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM goals WHERE site_id = :s AND period = :p LIMIT 1'
        );
        $stmt->execute(['s' => $siteId, 'p' => $period]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Categoria mais atrasada em relação ao alvo do mês (maior `target - realizado`),
     * pra geração automática escolher sozinha (bin/worker.php) — mesma ideia do
     * relatório "Por categoria" (ReportService::monthly()), só que devolvendo 1 id
     * em vez da tabela inteira. `null` se a meta não tem distribuição por categoria
     * ou se todo mundo já bateu o alvo (a geração segue sem categoria, como já
     * acontece quando o campo fica em branco na geração manual).
     */
    public function mostUnderTargetCategory(int $siteId, int $goalId, string $period): ?int
    {
        $targets = $this->categoryTargets($goalId);
        if ($targets === []) {
            return null;
        }

        $stmt = Connection::get()->prepare(
            "SELECT category_id, COUNT(*) AS produced
             FROM articles
             WHERE site_id = :s AND category_id IS NOT NULL AND deleted_at IS NULL
               AND DATE_FORMAT(created_at, '%Y-%m') = :p
               AND status IN ('APPROVED','SCHEDULED','PUBLISHED')
             GROUP BY category_id"
        );
        $stmt->execute(['s' => $siteId, 'p' => $period]);
        $produced = [];
        foreach ($stmt->fetchAll() as $row) {
            $produced[(int) $row['category_id']] = (int) $row['produced'];
        }

        $best = null;
        $bestGap = 0;
        foreach ($targets as $categoryId => $target) {
            $gap = $target - ($produced[$categoryId] ?? 0);
            if ($gap > $bestGap) {
                $bestGap = $gap;
                $best = $categoryId;
            }
        }

        return $best;
    }

    public function periodExists(int $siteId, string $period, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM goals WHERE site_id = :s AND period = :p';
        $params = ['s' => $siteId, 'p' => $period];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function countForSite(int $siteId): int
    {
        $stmt = Connection::get()->prepare('SELECT COUNT(*) FROM goals WHERE site_id = :s');
        $stmt->execute(['s' => $siteId]);

        return (int) $stmt->fetchColumn();
    }

    /** @param array<int, int> $targets category_id => target_count (> 0) */
    public function create(int $siteId, string $period, int $totalArticles, ?string $guidelines, array $targets): int
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO goals (site_id, period, total_articles, general_guidelines)
                 VALUES (:s, :p, :t, :g)'
            );
            $stmt->execute(['s' => $siteId, 'p' => $period, 't' => $totalArticles, 'g' => $guidelines]);
            $goalId = (int) $pdo->lastInsertId();
            $this->syncTargets($goalId, $targets);
            $pdo->commit();

            return $goalId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<int, int> $targets category_id => target_count (> 0) */
    public function update(int $id, string $period, int $totalArticles, ?string $guidelines, array $targets): void
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE goals SET period = :p, total_articles = :t, general_guidelines = :g WHERE id = :id'
            )->execute(['p' => $period, 't' => $totalArticles, 'g' => $guidelines, 'id' => $id]);
            $this->syncTargets($id, $targets);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        Connection::get()->prepare('DELETE FROM goals WHERE id = :id')->execute(['id' => $id]);
    }

    /** @param array<int, int> $targets category_id => target_count (> 0) */
    private function syncTargets(int $goalId, array $targets): void
    {
        $pdo = Connection::get();
        $pdo->prepare('DELETE FROM goal_categories WHERE goal_id = :g')->execute(['g' => $goalId]);

        if ($targets === []) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO goal_categories (goal_id, category_id, target_count) VALUES (:g, :c, :n)'
        );
        foreach ($targets as $categoryId => $count) {
            $stmt->execute(['g' => $goalId, 'c' => $categoryId, 'n' => $count]);
        }
    }
}

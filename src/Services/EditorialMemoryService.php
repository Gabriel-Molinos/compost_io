<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

/**
 * Memória editorial curada (Fase 9, pendência desde a 6.3 — schema.md §87):
 * "lições" duradouras por site, escritas por um humano (Redator-Chefe/Admin)
 * — nunca geradas sozinhas pela IA (Regra de não-invenção, §58). Diferente do
 * feedback bruto recente (`FeedbackService::recentForSite`, últimas 8
 * rejeições — some da janela quando fica velho), uma lição fica até alguém
 * desativar ou apagar, e entra em toda geração do site via
 * `ArticlePipeline::siteMemoryContext()`, SOMADA ao que já existia.
 */
final class EditorialMemoryService
{
    /** Lições ativas — o que entra no prompt (mais recentes primeiro). @return list<array<string, mixed>> */
    public function activeForSite(int $siteId, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = Connection::get()->prepare(
            "SELECT lesson FROM editorial_memory
             WHERE site_id = :s AND active = 1
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['s' => $siteId]);

        return $stmt->fetchAll();
    }

    /** Todas as lições do site (ativas e desativadas) pra tela de gestão. @return list<array<string, mixed>> */
    public function allForSite(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT m.id, m.lesson, m.active, m.source_feedback_id, m.created_at, u.name AS author
             FROM editorial_memory m
             LEFT JOIN users u ON u.id = m.created_by
             WHERE m.site_id = :s
             ORDER BY m.active DESC, m.id DESC"
        );
        $stmt->execute(['s' => $siteId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $siteId, int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM editorial_memory WHERE id = :id AND site_id = :s LIMIT 1'
        );
        $stmt->execute(['id' => $id, 's' => $siteId]);

        return $stmt->fetch() ?: null;
    }

    /** Ids de feedback já promovidos a lição neste site — pra tela não oferecer promover de novo. @return list<int> */
    public function promotedFeedbackIds(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT source_feedback_id FROM editorial_memory
             WHERE site_id = :s AND source_feedback_id IS NOT NULL'
        );
        $stmt->execute(['s' => $siteId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function create(int $siteId, string $lesson, ?int $userId, ?int $sourceFeedbackId = null): int
    {
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO editorial_memory (site_id, lesson, source_feedback_id, created_by)
             VALUES (:s, :l, :f, :u)'
        )->execute([
            's' => $siteId,
            'l' => trim($lesson),
            'f' => $sourceFeedbackId,
            'u' => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, string $lesson): void
    {
        Connection::get()->prepare(
            'UPDATE editorial_memory SET lesson = :l WHERE id = :id'
        )->execute(['l' => trim($lesson), 'id' => $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        Connection::get()->prepare(
            'UPDATE editorial_memory SET active = :a WHERE id = :id'
        )->execute(['a' => $active ? 1 : 0, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        Connection::get()->prepare('DELETE FROM editorial_memory WHERE id = :id')->execute(['id' => $id]);
    }
}

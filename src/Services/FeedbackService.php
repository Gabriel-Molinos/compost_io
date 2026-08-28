<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

/**
 * Feedback de rejeição de um artigo (tabela `feedback`, fluxo-editorial §28).
 * Motivo + justificativa + quem rejeitou. O artigo rejeitado fica no histórico.
 */
final class FeedbackService
{
    public function add(int $articleId, ?int $userId, string $reason, string $justification): int
    {
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO feedback (article_id, reason, justification, created_by)
             VALUES (:a, :r, :j, :u)'
        )->execute([
            'a' => $articleId,
            'r' => mb_substr($reason, 0, 50),
            'j' => trim($justification),
            'u' => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function forArticle(int $articleId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT f.reason, f.justification, f.created_at, u.name AS author
             FROM feedback f
             LEFT JOIN users u ON u.id = f.created_by
             WHERE f.article_id = :a
             ORDER BY f.id DESC'
        );
        $stmt->execute(['a' => $articleId]);

        return $stmt->fetchAll();
    }

    /**
     * Feedback relevante para a página do artigo: se tem linhagem, mostra a
     * cadeia inteira; senão, só o do próprio artigo.
     *
     * @return list<array<string, mixed>>
     */
    public function forContext(int $articleId, ?int $lineageId): array
    {
        return $lineageId !== null ? $this->forLineage($lineageId) : $this->forArticle($articleId);
    }

    /**
     * Rejeições recentes de qualquer artigo do site — "memória editorial" que
     * entra em toda geração para a IA não repetir erros do site (fatia 6.3).
     *
     * @return list<array<string, mixed>>
     */
    public function recentForSite(int $siteId, int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = Connection::get()->prepare(
            "SELECT f.reason, f.justification, f.created_at
             FROM feedback f
             JOIN articles a ON a.id = f.article_id
             WHERE a.site_id = :s
             ORDER BY f.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['s' => $siteId]);

        return $stmt->fetchAll();
    }

    /**
     * Feedback de toda a linhagem (todas as tentativas do mesmo conteúdo) —
     * usado pela regeneração para a IA não repetir os erros (fatia 6.2).
     *
     * @return list<array<string, mixed>>
     */
    public function forLineage(int $lineageId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT f.reason, f.justification, f.created_at, a.attempt_number, u.name AS author
             FROM feedback f
             JOIN articles a ON a.id = f.article_id
             LEFT JOIN users u ON u.id = f.created_by
             WHERE a.lineage_id = :l
             ORDER BY a.attempt_number, f.id'
        );
        $stmt->execute(['l' => $lineageId]);

        return $stmt->fetchAll();
    }
}

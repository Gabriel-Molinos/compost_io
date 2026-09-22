<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;

/**
 * Feedback geral sobre o COMPOST em si — não sobre o conteúdo gerado, não
 * pra "ensinar a IA" (isso já existe, é a Memória Editorial de cada site,
 * `FeedbackService` + `EditorialMemoryService`). Aqui é o Redator-Chefe
 * avaliando a plataforma (o que percebeu, o que trava, o que está bom) pra
 * admin + Claude Code melhorarem, ver migration 0024.
 *
 * Nome diferente de `FeedbackService` de propósito — aquele já existia (feedback
 * de rejeição de artigo, tabela `feedback`) muito antes deste. Nomear os dois
 * igual (achado real, 2026-09-17) sobrescreveu o arquivo antigo por completo,
 * derrubando a revisão editorial inteira (`ArticleReviewService`, regeneração,
 * memória editorial) — mesmo namespace, classe com o mesmo nome literalmente
 * substitui a outra ao salvar o arquivo, não é possível ter as duas.
 */
final class PlatformFeedbackService
{
    public function create(int $userId, ?int $siteId, ?int $rating, string $message): int
    {
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO platform_feedback (user_id, site_id, rating, message) VALUES (:u, :s, :r, :m)'
        )->execute(['u' => $userId, 's' => $siteId, 'r' => $rating, 'm' => $message]);

        return (int) $pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listAll(int $limit = 200): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT f.*, u.name AS author_name, u.avatar_path AS author_avatar, s.name AS site_name, r.name AS reviewer_name
             FROM platform_feedback f
             JOIN users u ON u.id = f.user_id
             LEFT JOIN sites s ON s.id = f.site_id
             LEFT JOIN users r ON r.id = f.reviewed_by
             ORDER BY f.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId, int $limit = 50): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT f.*, s.name AS site_name
             FROM platform_feedback f
             LEFT JOIN sites s ON s.id = f.site_id
             WHERE f.user_id = :u
             ORDER BY f.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue('u', $userId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countPending(): int
    {
        return (int) Connection::get()
            ->query('SELECT COUNT(*) FROM platform_feedback WHERE reviewed_at IS NULL')
            ->fetchColumn();
    }

    public function markReviewed(int $id, int $reviewerId): void
    {
        Connection::get()->prepare(
            'UPDATE platform_feedback SET reviewed_at = NOW(), reviewed_by = :r WHERE id = :id AND reviewed_at IS NULL'
        )->execute(['r' => $reviewerId, 'id' => $id]);
    }
}

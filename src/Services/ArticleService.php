<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\HtmlSanitizer;

/**
 * Artigos e seus dados diretos (versões de corpo, fontes). A máquina de estados
 * (requisitos §65) é dirigida pelo `ArticlePipeline` e, mais tarde, pela revisão
 * humana (Fase 6). Soft delete via `deleted_at`.
 */
final class ArticleService
{
    /** @return list<array<string, mixed>> */
    public function allForSite(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT a.id, a.title, a.status, a.focus_keyword, a.category_id, a.created_at,
                    a.attempt_number, a.lineage_id,
                    c.name AS category_name,
                    COALESCE((SELECT SUM(cost) FROM ai_executions e WHERE e.article_id = a.id), 0) AS ai_cost,
                    (SELECT MAX(word_count) FROM article_versions v WHERE v.article_id = a.id) AS word_count
             FROM articles a
             LEFT JOIN categories c ON c.id = a.category_id
             WHERE a.site_id = :s AND a.deleted_at IS NULL
             ORDER BY a.created_at DESC"
        );
        $stmt->execute(['s' => $siteId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $siteId, int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM articles WHERE id = :id AND site_id = :s AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id, 's' => $siteId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Últimos artigos já aprovados do site (memória editorial — fatia 6.3):
     * a IA usa para não repetir tema/ângulo e evitar canibalização.
     *
     * @return list<array<string, mixed>>
     */
    public function recentApprovedForSite(int $siteId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = Connection::get()->prepare(
            "SELECT title, focus_keyword
             FROM articles
             WHERE site_id = :s AND deleted_at IS NULL
               AND status IN ('APPROVED','SCHEDULED','PUBLISHED')
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['s' => $siteId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null artigo por id, sem escopo de site (uso interno de serviços) */
    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM articles WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** Artigos criados no site nas últimas 24h (guarda de custo de IA — requisitos §95). */
    public function countCreatedLast24h(int $siteId): int
    {
        $stmt = Connection::get()->prepare(
            'SELECT COUNT(*) FROM articles WHERE site_id = :s AND created_at >= (NOW() - INTERVAL 1 DAY)'
        );
        $stmt->execute(['s' => $siteId]);

        return (int) $stmt->fetchColumn();
    }

    public function create(int $siteId, ?int $goalId): int
    {
        $pdo = Connection::get();
        $pdo->prepare(
            "INSERT INTO articles (site_id, goal_id, status, attempt_number)
             VALUES (:s, :g, 'PLANNED', 1)"
        )->execute(['s' => $siteId, 'g' => $goalId]);

        return (int) $pdo->lastInsertId();
    }

    /** Nova tentativa da mesma linhagem (regeneração — fluxo-editorial §29). */
    public function createAttempt(int $siteId, ?int $goalId, int $lineageId, int $attemptNumber): int
    {
        $pdo = Connection::get();
        $pdo->prepare(
            "INSERT INTO articles (site_id, goal_id, status, lineage_id, attempt_number)
             VALUES (:s, :g, 'PLANNED', :l, :n)"
        )->execute(['s' => $siteId, 'g' => $goalId, 'l' => $lineageId, 'n' => $attemptNumber]);

        return (int) $pdo->lastInsertId();
    }

    /** Fixa o lineage_id (só se ainda estiver nulo) — o 1º artigo aponta para si mesmo. */
    public function setLineage(int $id, int $lineageId): void
    {
        Connection::get()->prepare(
            'UPDATE articles SET lineage_id = :l WHERE id = :id AND lineage_id IS NULL'
        )->execute(['l' => $lineageId, 'id' => $id]);
    }

    public function applyPlan(int $id, string $title, string $focusKeyword, ?int $categoryId): void
    {
        Connection::get()->prepare(
            'UPDATE articles SET title = :t, focus_keyword = :k, category_id = :c WHERE id = :id'
        )->execute([
            't' => mb_substr($title, 0, 255),
            'k' => mb_substr($focusKeyword, 0, 191),
            'c' => $categoryId,
            'id' => $id,
        ]);
    }

    public function applyWriting(int $id, int $siteId, string $title, string $slug, string $focusKeyword, string $metaDescription): void
    {
        Connection::get()->prepare(
            "UPDATE articles
             SET title = :t, slug = :sl, focus_keyword = :k, meta_description = :m, status = 'IN_PROGRESS'
             WHERE id = :id"
        )->execute([
            't' => mb_substr($title, 0, 255),
            'sl' => $this->uniqueSlug($siteId, $slug, $id),
            'k' => mb_substr($focusKeyword, 0, 191),
            'm' => mb_substr($metaDescription, 0, 320),
            'id' => $id,
        ]);
    }

    public function setStatus(int $id, string $status): void
    {
        // Carimba a janela de revisão humana para o relatório mensal (Fase 8, §32).
        $extra = match ($status) {
            'IN_REVIEW'                        => ', review_started_at = COALESCE(review_started_at, NOW())',
            'APPROVED', 'REVISION_REQUESTED'   => ', reviewed_at = NOW()',
            default                            => '',
        };

        Connection::get()->prepare("UPDATE articles SET status = :st{$extra} WHERE id = :id")
            ->execute(['st' => $status, 'id' => $id]);
    }

    public function softDelete(int $id): void
    {
        Connection::get()->prepare("UPDATE articles SET deleted_at = NOW(), status = 'DISCARDED' WHERE id = :id")
            ->execute(['id' => $id]);
    }

    /** Canibalização: a palavra-chave já está em outro artigo vivo do site? (§21, seo.md) */
    public function keywordExists(int $siteId, string $focusKeyword, int $ignoreArticleId): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT COUNT(*) FROM articles
             WHERE site_id = :s AND focus_keyword = :k AND id <> :ig AND deleted_at IS NULL'
        );
        $stmt->execute(['s' => $siteId, 'k' => $focusKeyword, 'ig' => $ignoreArticleId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function addVersion(int $articleId, string $contentHtml, int $wordCount): int
    {
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO article_versions (article_id, content, word_count) VALUES (:a, :c, :w)'
        )->execute(['a' => $articleId, 'c' => HtmlSanitizer::clean($contentHtml), 'w' => $wordCount]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function latestVersion(int $articleId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM article_versions WHERE article_id = :a ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['a' => $articleId]);

        return $stmt->fetch() ?: null;
    }

    /** @param array<string, mixed> $source */
    public function addSource(int $articleId, array $source): void
    {
        Connection::get()->prepare(
            'INSERT INTO article_sources (article_id, url, title, publisher, accessed_at)
             VALUES (:a, :u, :t, :p, :d)'
        )->execute([
            'a' => $articleId,
            'u' => mb_substr((string) ($source['source_url'] ?? ''), 0, 1024),
            't' => self::nullable($source['source_title'] ?? null, 255),
            'p' => self::nullable($source['publisher'] ?? null, 191),
            'd' => self::validDate($source['accessed_at'] ?? null),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function sources(int $articleId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT url, title, publisher, accessed_at FROM article_sources WHERE article_id = :a ORDER BY id'
        );
        $stmt->execute(['a' => $articleId]);

        return $stmt->fetchAll();
    }

    /** Garante slug único por site (`uq_articles_site_slug`), somando -2, -3… se preciso. */
    private function uniqueSlug(int $siteId, string $slug, int $articleId): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($slug)) ?? '', '-');
        $base = $base !== '' ? mb_substr($base, 0, 180) : 'artigo-' . $articleId;

        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM articles WHERE site_id = :s AND slug = :sl AND id <> :id'
        );

        $candidate = $base;
        $n = 2;
        while (true) {
            $stmt->execute(['s' => $siteId, 'sl' => $candidate, 'id' => $articleId]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $candidate;
            }
            $candidate = $base . '-' . $n++;
        }
    }

    private static function nullable(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private static function validDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value . ' 00:00:00' : null;
    }
}

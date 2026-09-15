<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

/**
 * Pareceres em JSON de cada passo da IA para um artigo (tabela `article_ai_notes`).
 * Uma linha por (artigo, passo) — regravar o mesmo passo sobrescreve.
 */
final class ArticleNoteService
{
    /** @param array<string, mixed> $payload */
    public function save(int $articleId, string $step, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = json_encode(['_error' => 'payload não serializável']);
        }

        Connection::get()->prepare(
            'INSERT INTO article_ai_notes (article_id, step, payload) VALUES (:a, :s, :p)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload)'
        )->execute(['a' => $articleId, 's' => $step, 'p' => $json]);
    }

    /**
     * Artigos do site com link pendente de ação humana — Central de Links
     * (`/sites/{id}/links`, achado real 2026-09-10): ambíguo (bloqueio de bot,
     * não confirmado) ou link rot (morreu depois de publicado,
     * `bin/worker.php`). Busca tudo e filtra em PHP — mesmo padrão já usado
     * no projeto pra payload JSON, sem índice JSON do MySQL.
     *
     * @return list<array{article_id:int, title:string, status:string, ambiguous_links:list<string>, link_rot_dead_urls:list<string>, backlink_suggestions:list<array<string,mixed>>}>
     */
    public function flaggedLinksForSite(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT n.article_id, n.payload, a.title, a.status
             FROM article_ai_notes n
             JOIN articles a ON a.id = n.article_id
             WHERE n.step = 'pipeline' AND a.site_id = :s AND a.deleted_at IS NULL"
        );
        $stmt->execute(['s' => $siteId]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $payload = json_decode((string) $row['payload'], true);
            $ambiguous = is_array($payload) ? (array) ($payload['ambiguous_links'] ?? []) : [];
            $deadUrls = is_array($payload) ? (array) ($payload['link_rot_dead_urls'] ?? []) : [];
            $backlinks = is_array($payload) ? (array) ($payload['backlink_suggestions'] ?? []) : [];
            if ($ambiguous === [] && $deadUrls === [] && $backlinks === []) {
                continue;
            }
            $out[] = [
                'article_id' => (int) $row['article_id'],
                'title' => (string) $row['title'],
                'status' => (string) $row['status'],
                'ambiguous_links' => array_values(array_map('strval', $ambiguous)),
                'link_rot_dead_urls' => array_values(array_map('strval', $deadUrls)),
                'backlink_suggestions' => array_values(array_filter($backlinks, 'is_array')),
            ];
        }

        return $out;
    }

    /**
     * Lacunas de pesquisa apontadas pela IA (achado real 2026-09-15,
     * recomendação do relatório de Inteligência: "usar as lacunas de
     * pesquisa como pauta pra aprofundar a apuração dos próximos
     * conteúdos") — o passo `research` já marca o que não conseguiu
     * confirmar em fonte confiável (`gaps`, docs/ai/research.md), mas isso
     * ficava só preso na nota de pipeline de cada artigo, um por um.
     * Agregado por site, mais recente primeiro, pra virar ideia de pauta.
     *
     * @return list<array{article_id:int, title:string, updated_at:string, gaps:list<string>}>
     */
    public function researchGapsForSite(int $siteId, int $limit = 30): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT n.article_id, n.payload, n.updated_at, a.title
             FROM article_ai_notes n
             JOIN articles a ON a.id = n.article_id
             WHERE n.step = 'pipeline' AND a.site_id = :s AND a.deleted_at IS NULL
             ORDER BY n.updated_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue('s', $siteId, \PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $payload = json_decode((string) $row['payload'], true);
            $gaps = is_array($payload) ? (array) ($payload['research_gaps'] ?? []) : [];
            if ($gaps === []) {
                continue;
            }
            $out[] = [
                'article_id' => (int) $row['article_id'],
                'title'      => (string) $row['title'],
                'updated_at' => (string) $row['updated_at'],
                'gaps'       => array_values(array_map('strval', $gaps)),
            ];
        }

        return $out;
    }

    /** @return array<string, array<string, mixed>> passo => payload decodificado */
    public function forArticle(int $articleId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT step, payload FROM article_ai_notes WHERE article_id = :a ORDER BY id'
        );
        $stmt->execute(['a' => $articleId]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $decoded = json_decode((string) $row['payload'], true);
            $out[$row['step']] = is_array($decoded) ? $decoded : [];
        }

        return $out;
    }
}

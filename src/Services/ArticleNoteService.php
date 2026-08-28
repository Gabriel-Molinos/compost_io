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

<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Integrations\AIResult;
use App\Integrations\Gemini\GeminiPricing;

/**
 * Ciclo de vida de uma execução técnica da IA na tabela `ai_executions`
 * (schema §87; retry §96; custo §95).
 *
 *   create()  -> QUEUED
 *   markRunning()   -> RUNNING
 *   markRetrying()  -> RETRYING (com retry_count)
 *   markSuccess()   -> SUCCESS  (cost + finished_at)
 *   markFailed()    -> FAILED   (error_message + finished_at)
 */
final class AiExecutionService
{
    public const STEPS = ['planning', 'research', 'writing', 'seo', 'compliance', 'image', 'review', 'backlink_suggestions', 'external_link_suggestions', 'internal_link_suggestions'];

    public function create(int $articleId, string $step, string $provider, ?string $queueJobId = null): int
    {
        $pdo = Connection::get();
        $pdo->prepare(
            "INSERT INTO ai_executions (article_id, step, provider, status, queue_job_id, queued_at)
             VALUES (:a, :s, :p, 'QUEUED', :j, NOW())"
        )->execute(['a' => $articleId, 's' => $step, 'p' => $provider, 'j' => $queueJobId]);

        return (int) $pdo->lastInsertId();
    }

    public function markRunning(int $id): void
    {
        Connection::get()->prepare(
            "UPDATE ai_executions SET status = 'RUNNING', started_at = COALESCE(started_at, NOW())
             WHERE id = :id"
        )->execute(['id' => $id]);
    }

    public function markRetrying(int $id, int $attempt, string $error): void
    {
        Connection::get()->prepare(
            "UPDATE ai_executions
             SET status = 'RETRYING', retry_count = :n, error_message = :e
             WHERE id = :id"
        )->execute(['n' => $attempt, 'e' => self::trim($error), 'id' => $id]);
    }

    public function markSuccess(int $id, AIResult $result): void
    {
        Connection::get()->prepare(
            "UPDATE ai_executions
             SET status = 'SUCCESS', cost = :c, error_message = NULL, finished_at = NOW()
             WHERE id = :id"
        )->execute(['c' => GeminiPricing::estimate($result), 'id' => $id]);
    }

    /** Sucesso com custo já calculado por quem chama (ex.: imagem — custo por imagem, não por token). */
    public function markSuccessCost(int $id, float $cost): void
    {
        Connection::get()->prepare(
            "UPDATE ai_executions
             SET status = 'SUCCESS', cost = :c, error_message = NULL, finished_at = NOW()
             WHERE id = :id"
        )->execute(['c' => round($cost, 6), 'id' => $id]);
    }

    public function markFailed(int $id, string $error): void
    {
        Connection::get()->prepare(
            "UPDATE ai_executions
             SET status = 'FAILED', error_message = :e, finished_at = NOW()
             WHERE id = :id"
        )->execute(['e' => self::trim($error), 'id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM ai_executions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> execuções do artigo, na ordem em que rodaram */
    public function forArticle(int $articleId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, step, provider, status, cost, retry_count, error_message, started_at, finished_at
             FROM ai_executions WHERE article_id = :a ORDER BY id'
        );
        $stmt->execute(['a' => $articleId]);

        return $stmt->fetchAll();
    }

    /**
     * Passo mais recente de cada artigo (o que a IA está fazendo agora, pra
     * quem ainda está gerando) — uma consulta só pra lista inteira.
     *
     * @param list<int> $articleIds
     * @return array<int, string> article_id => step
     */
    public function latestSteps(array $articleIds): array
    {
        if ($articleIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
        $stmt = Connection::get()->prepare(
            "SELECT e.article_id, e.step FROM ai_executions e
             JOIN (SELECT article_id, MAX(id) AS id FROM ai_executions
                   WHERE article_id IN ({$placeholders}) GROUP BY article_id) last ON last.id = e.id"
        );
        $stmt->execute(array_values($articleIds));

        $steps = [];
        foreach ($stmt->fetchAll() as $row) {
            $steps[(int) $row['article_id']] = (string) $row['step'];
        }

        return $steps;
    }

    /** Custo total de IA já registrado para um artigo (USD). */
    public function totalCostForArticle(int $articleId): float
    {
        $stmt = Connection::get()->prepare(
            'SELECT COALESCE(SUM(cost), 0) FROM ai_executions WHERE article_id = :a'
        );
        $stmt->execute(['a' => $articleId]);

        return (float) $stmt->fetchColumn();
    }

    private static function trim(string $error): string
    {
        return mb_substr($error, 0, 2000);
    }
}

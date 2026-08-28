<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use RuntimeException;

/**
 * Revisão humana de um artigo (fluxo-editorial §27–28, RF-007/008/009).
 * O Redator-Chefe aprova ou rejeita um artigo em `IN_REVIEW`:
 *
 *   IN_REVIEW --aprovar--> APPROVED
 *   IN_REVIEW --rejeitar--> REVISION_REQUESTED  (+ linha em `feedback`)
 *
 * A aprovação é sempre humana (requisitos §65.1). Rejeitar exige motivo +
 * justificativa (RB-004).
 */
final class ArticleReviewService
{
    /** Motivos de rejeição (fluxo-editorial §28). value => rótulo. */
    public const REJECT_REASONS = [
        'tema_fraco'            => 'Tema fraco',
        'informacao_incorreta'  => 'Informação incorreta',
        'conteudo_superficial'  => 'Conteúdo superficial',
        'fora_do_tom'           => 'Fora do tom',
        'tema_repetido'         => 'Tema repetido',
        'nao_seguiu_meta'       => 'Não seguiu a meta',
        'problema_compliance'   => 'Problema de compliance',
        'imagem_inadequada'     => 'Imagem inadequada',
        'outro'                 => 'Outro',
    ];

    private ArticleService $articles;
    private FeedbackService $feedback;

    public function __construct(?ArticleService $articles = null, ?FeedbackService $feedback = null)
    {
        $this->articles = $articles ?? new ArticleService();
        $this->feedback = $feedback ?? new FeedbackService();
    }

    /** @throws RuntimeException se o artigo não estiver em IN_REVIEW */
    public function approve(int $articleId): void
    {
        $this->assertInReview($articleId);
        $this->articles->setStatus($articleId, 'APPROVED');
    }

    /**
     * @throws RuntimeException se o artigo não estiver em IN_REVIEW ou o motivo for inválido
     */
    public function requestRevision(int $articleId, ?int $userId, string $reason, string $justification): void
    {
        $this->assertInReview($articleId);

        if (!array_key_exists($reason, self::REJECT_REASONS)) {
            throw new RuntimeException('Motivo de rejeição inválido.');
        }
        if (trim($justification) === '') {
            throw new RuntimeException('A justificativa é obrigatória (RB-004).');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $this->feedback->add($articleId, $userId, $reason, $justification);
            $this->articles->setStatus($articleId, 'REVISION_REQUESTED');
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function assertInReview(int $articleId): void
    {
        $article = $this->articles->findById($articleId);
        if ($article === null) {
            throw new RuntimeException('Artigo não encontrado.');
        }
        if ($article['status'] !== 'IN_REVIEW') {
            throw new RuntimeException('Só é possível revisar um artigo em "Em revisão" (status atual: ' . $article['status'] . ').');
        }
    }
}

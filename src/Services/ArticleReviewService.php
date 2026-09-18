<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\HtmlLinks;
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

    /** Faixa de link exigida pro checklist de pré-aprovação (RF-008, docs/ai/seo.md "Links internos"/docs/ai/compliance.md). */
    private const MIN_INTERNAL_LINKS = 3;
    private const MAX_INTERNAL_LINKS = 5;
    private const MAX_EXTERNAL_LINKS = 2;

    private ArticleService $articles;
    private FeedbackService $feedback;
    private SiteService $sites;

    public function __construct(?ArticleService $articles = null, ?FeedbackService $feedback = null, ?SiteService $sites = null)
    {
        $this->articles = $articles ?? new ArticleService();
        $this->feedback = $feedback ?? new FeedbackService();
        $this->sites = $sites ?? new SiteService();
    }

    /**
     * Checklist obrigatório de pré-aprovação (achado real 2026-09-15,
     * recomendação do relatório de Inteligência: artigos chegavam a
     * `BLOCKED` em compliance/SEO por fugir de categoria válida ou da faixa
     * de link — mas nada impedia a aprovação manual de um artigo fora dessa
     * faixa antes disso). Cada item tem `ok` + `detail` (pro painel mostrar
     * o porquê); `approve()` usa isto pra travar de verdade, não só avisar.
     *
     * @param array<string, mixed> $article linha de `articles` (findById())
     * @return array<string, array{ok: bool, label: string, detail: string}>
     */
    public function checklist(array $article): array
    {
        $version = $this->articles->latestVersion((int) $article['id']);
        // A coluna de article_versions é `content` (não `content_html`, que é só o nome
        // do campo no JSON do passo de escrita da IA). Com a chave errada o corpo vinha
        // sempre vazio → 0 links → todo artigo reprovava no checklist (achado real
        // 2026-09-18, ao rodar a suíte de integração de verdade pela 1ª vez).
        $html = (string) ($version['content'] ?? '');

        $site = $this->sites->find((int) $article['site_id']);
        $siteHost = $site !== null && !empty($site['wordpress_url'])
            ? parse_url((string) $site['wordpress_url'], PHP_URL_HOST)
            : null;
        $links = HtmlLinks::countByType($html, is_string($siteHost) ? $siteHost : null);

        $internalOk = $links['internal'] >= self::MIN_INTERNAL_LINKS && $links['internal'] <= self::MAX_INTERNAL_LINKS;
        $externalOk = $links['external'] <= self::MAX_EXTERNAL_LINKS;

        return [
            'category' => [
                'ok'     => !empty($article['category_id']),
                'label'  => 'Categoria válida',
                'detail' => !empty($article['category_id']) ? 'Definida.' : 'Nenhuma categoria definida.',
            ],
            'internal_links' => [
                'ok'     => $internalOk,
                'label'  => 'Links internos (3 a 5)',
                'detail' => $links['internal'] . ' encontrado(s).',
            ],
            'external_links' => [
                'ok'     => $externalOk,
                'label'  => 'Links externos (máx. 2)',
                'detail' => $links['external'] . ' encontrado(s).',
            ],
        ];
    }

    public static function checklistPassed(array $checklist): bool
    {
        foreach ($checklist as $item) {
            if (!$item['ok']) {
                return false;
            }
        }

        return true;
    }

    /** @throws RuntimeException se o artigo não estiver em IN_REVIEW ou não passar no checklist de pré-aprovação */
    public function approve(int $articleId): void
    {
        $article = $this->assertInReview($articleId);

        $checklist = $this->checklist($article);
        if (!self::checklistPassed($checklist)) {
            $failed = array_values(array_map(
                static fn (array $item): string => $item['label'],
                array_filter($checklist, static fn (array $item): bool => !$item['ok']),
            ));
            throw new RuntimeException(
                'Checklist de pré-aprovação não passou: ' . implode(', ', $failed) . '. Ajuste o artigo antes de aprovar.'
            );
        }

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

    /** @return array<string, mixed> */
    private function assertInReview(int $articleId): array
    {
        $article = $this->articles->findById($articleId);
        if ($article === null) {
            throw new RuntimeException('Artigo não encontrado.');
        }
        if ($article['status'] !== 'IN_REVIEW') {
            throw new RuntimeException('Só é possível revisar um artigo em "Em revisão" (status atual: ' . $article['status'] . ').');
        }

        return $article;
    }
}

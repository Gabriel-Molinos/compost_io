<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\HtmlLinks;
use RuntimeException;

use function str_word_count;
use function strip_tags;

// ArticleNoteService fica no mesmo namespace App\Services — sem use extra necessário.

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

    /** Extensão mínima de compliance/AdSense (docs/editorial/compliance.md, docs/ai/compliance.md). */
    private const MIN_WORDS = 1500;

    private ArticleService $articles;
    private FeedbackService $feedback;
    private SiteService $sites;
    private ArticleNoteService $notes;

    public function __construct(
        ?ArticleService $articles = null,
        ?FeedbackService $feedback = null,
        ?SiteService $sites = null,
        ?ArticleNoteService $notes = null,
    ) {
        $this->articles = $articles ?? new ArticleService();
        $this->feedback = $feedback ?? new FeedbackService();
        $this->sites = $sites ?? new SiteService();
        $this->notes = $notes ?? new ArticleNoteService();
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
    public function checklist(array $article, ?array $version = null, ?array $site = null): array
    {
        // $version/$site opcionais: quem já os carregou (a tela do artigo) passa
        // adiante e poupa 2 idas ao banco por request.
        $version ??= $this->articles->latestVersion((int) $article['id']);
        // A coluna de article_versions é `content` (não `content_html`, que é só o nome
        // do campo no JSON do passo de escrita da IA). Com a chave errada o corpo vinha
        // sempre vazio → 0 links → todo artigo reprovava no checklist (achado real
        // 2026-09-18, ao rodar a suíte de integração de verdade pela 1ª vez).
        $html = (string) ($version['content'] ?? '');
        $wordCount = (int) ($version['word_count'] ?? 0);
        if ($wordCount <= 0 && $html !== '') {
            $wordCount = str_word_count(strip_tags($html));
        }

        $site ??= $this->sites->find((int) $article['site_id']);
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
            'min_words' => [
                'ok'     => $wordCount >= self::MIN_WORDS,
                'label'  => 'Extensão mínima (' . self::MIN_WORDS . ' palavras)',
                'detail' => $wordCount . ' palavra(s) — regra de compliance/AdSense (docs/editorial/compliance.md).',
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

    /**
     * Itens de `blocking` do passo de compliance da IA (docs/ai/compliance.md), se houver.
     * Diferente do checklist acima, isto é parecer da IA (pode errar — ver aviso sobre
     * falso-positivo de sintaxe HTML nos prompts) — por isso nunca trava sozinho; exige
     * confirmação explícita do humano em vez de bloquear a aprovação (ver {@see approve()}).
     *
     * @return list<array{rule: string, evidence: string, fix: string}>
     */
    public function complianceBlocking(int $articleId): array
    {
        $compliance = $this->notes->forArticle($articleId)['compliance'] ?? null;
        if (!is_array($compliance)) {
            return [];
        }

        $out = [];
        foreach ((array) ($compliance['blocking'] ?? []) as $b) {
            if (!is_array($b)) {
                continue;
            }
            $out[] = [
                'rule'     => (string) ($b['rule'] ?? ''),
                'evidence' => (string) ($b['evidence'] ?? ''),
                'fix'      => (string) ($b['fix'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @throws RuntimeException se o artigo não estiver em IN_REVIEW, não passar no
     *         checklist de pré-aprovação, ou houver pendência de compliance sem confirmação
     */
    public function approve(int $articleId, bool $complianceAck = false): void
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

        $blocking = $this->complianceBlocking($articleId);
        if ($blocking !== [] && !$complianceAck) {
            throw new RuntimeException(
                'A IA apontou ' . count($blocking) . ' pendência(s) de compliance — reveja a seção "Compliance" '
                . 'da página e marque "Revisei as pendências e decido aprovar assim mesmo" antes de confirmar.'
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

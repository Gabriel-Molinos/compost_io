<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\WordPress\ExternalLinkVerifier;
use App\Integrations\WordPress\InternalLinkResolver;
use App\Integrations\WordPress\WordPressException;
use App\Support\HtmlLinks;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Ações da Central de Links (`/sites/{id}/links`, achado real 2026-09-10):
 * o Redator-Chefe corrige um link específico (ambíguo ou que morreu depois
 * de publicado — `bin/worker.php`) sem editar HTML. Se o artigo já está
 * publicado, manda a correção pro WordPress na mesma ação — não devolve pro
 * redator um segundo passo pra lembrar.
 */
final class LinkFixService
{
    private ArticleService $articles;
    private ArticleNoteService $notes;
    private WordPressConnectionService $connections;
    private SiteService $sites;

    public function __construct(
        ?ArticleService $articles = null,
        ?ArticleNoteService $notes = null,
        ?WordPressConnectionService $connections = null,
        ?SiteService $sites = null,
    ) {
        $this->articles = $articles ?? new ArticleService();
        $this->notes = $notes ?? new ArticleNoteService();
        $this->connections = $connections ?? new WordPressConnectionService();
        $this->sites = $sites ?? new SiteService();
    }

    /** @return array{synced_to_wordpress:bool, wordpress_error:?string} */
    public function removeLink(int $articleId, int $siteId, string $url): array
    {
        $version = $this->requireVersion($articleId);
        $result = HtmlLinks::unwrap((string) $version['content'], $url);
        if (!$result['changed']) {
            throw new RuntimeException('Esse link não foi encontrado no corpo atual do artigo — talvez já tenha sido corrigido.');
        }

        $this->saveAndClear($articleId, $result['html'], $url);

        return $this->syncIfPublished($articleId, $siteId);
    }

    /** @return array{synced_to_wordpress:bool, wordpress_error:?string, warning:?string} */
    public function replaceLink(int $articleId, int $siteId, string $oldUrl, string $newUrl): array
    {
        $newUrl = trim($newUrl);
        if (filter_var($newUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('A URL nova não parece válida — confira se copiou o endereço completo (com https://).');
        }

        $version = $this->requireVersion($articleId);
        $result = HtmlLinks::replaceHref((string) $version['content'], $oldUrl, $newUrl);
        if (!$result['changed']) {
            throw new RuntimeException('Esse link não foi encontrado no corpo atual do artigo — talvez já tenha sido corrigido.');
        }

        $this->saveAndClear($articleId, $result['html'], $oldUrl);

        // Aviso, não bloqueio: o bot pode estar errado (mesmo motivo do estado
        // "ambíguo" existir) — o redator decide, não o verificador automático.
        $warning = null;
        try {
            $check = (new ExternalLinkVerifier())->verify('<a href="' . htmlspecialchars($newUrl, ENT_QUOTES) . '">x</a>');
            if ($check['unwrapped'] > 0) {
                $warning = 'Atenção: essa URL nova também parece fora do ar (verificação automática) — confira antes de confiar nela.';
            } elseif ($check['ambiguous'] !== []) {
                $warning = 'Essa URL nova não pôde ser confirmada automaticamente (bloqueio comum de bot) — confira manualmente se ela é real.';
            }
        } catch (Throwable) {
            // Checagem é só um bônus informativo — falha nela não deve impedir salvar a troca.
        }

        $sync = $this->syncIfPublished($articleId, $siteId);

        return $sync + ['warning' => $warning];
    }

    public function confirmOk(int $articleId, string $url): void
    {
        $note = $this->notes->forArticle($articleId)['pipeline'] ?? [];
        $ambiguous = array_values(array_diff((array) ($note['ambiguous_links'] ?? []), [$url]));
        if (count($ambiguous) === count((array) ($note['ambiguous_links'] ?? []))) {
            throw new RuntimeException('Esse link não está mais na lista de ambíguos.');
        }
        $note['ambiguous_links'] = $ambiguous;
        $this->notes->save($articleId, 'pipeline', $note);
    }

    /**
     * Aplica uma sugestão de link interno retroativo (achado real
     * 2026-09-10, `BacklinkSuggestionService`) — insere o link de verdade no
     * corpo deste artigo (antigo) apontando pro artigo novo, e some da
     * lista de sugestões.
     *
     * `$url` chega no formato `?p=ID` (`BacklinkSuggestionService`, funciona em
     * qualquer WordPress independente da estrutura de permalink), mas as
     * diretrizes oficiais do Google pra links rastreáveis e consolidação de
     * URLs duplicadas pedem link consistente pro URL canônico dentro do site
     * — não pro formato que só resolve depois de um redirect. Resolve pro
     * permalink real antes de gravar (mesmo `InternalLinkResolver` já usado
     * na geração e na edição manual do corpo, achado real 2026-09-14),
     * em vez de confiar só na resolução que roda de novo na publicação.
     *
     * @return array{synced_to_wordpress:bool, wordpress_error:?string}
     */
    public function applyBacklink(int $articleId, int $siteId, string $anchorText, string $url): array
    {
        $version = $this->requireVersion($articleId);
        $result = HtmlLinks::wrapFirstOccurrence((string) $version['content'], $anchorText, $url);
        if (!$result['changed']) {
            throw new RuntimeException('Esse trecho não foi encontrado no corpo atual do artigo — talvez já tenha mudado desde a sugestão.');
        }

        $html = $this->resolveToCanonical($siteId, $result['html']);

        $wordCount = str_word_count(strip_tags($html));
        $this->articles->addVersion($articleId, $html, $wordCount);
        $this->removeBacklinkSuggestion($articleId, $anchorText, $url);

        return $this->syncIfPublished($articleId, $siteId);
    }

    /** Sem WordPress conectado (ou falha de rede), devolve o HTML sem mexer — a publicação continua sendo a rede de segurança final. */
    private function resolveToCanonical(int $siteId, string $html): string
    {
        try {
            $client = $this->connections->client($siteId);
            $site = $this->sites->find($siteId);
            $baseUrl = (string) ($site['wordpress_url'] ?? '');
        } catch (WordPressException) {
            return $html;
        }

        return (new InternalLinkResolver($client, $baseUrl))->resolve($html)['html'];
    }

    public function dismissBacklink(int $articleId, string $anchorText, string $url): void
    {
        $this->removeBacklinkSuggestion($articleId, $anchorText, $url);
    }

    private function removeBacklinkSuggestion(int $articleId, string $anchorText, string $url): void
    {
        $note = $this->notes->forArticle($articleId)['pipeline'] ?? [];
        $suggestions = (array) ($note['backlink_suggestions'] ?? []);
        $filtered = array_values(array_filter(
            $suggestions,
            static fn ($s): bool => !is_array($s) || ($s['anchor_text'] ?? null) !== $anchorText || ($s['url'] ?? null) !== $url,
        ));
        if (count($filtered) === count($suggestions)) {
            throw new RuntimeException('Essa sugestão não está mais na lista.');
        }
        $note['backlink_suggestions'] = $filtered;
        $this->notes->save($articleId, 'pipeline', $note);
    }

    /** @return array<string, mixed> */
    private function requireVersion(int $articleId): array
    {
        $version = $this->articles->latestVersion($articleId);
        if ($version === null) {
            throw new RuntimeException('Artigo sem corpo ainda — nada para corrigir.');
        }

        return $version;
    }

    private function saveAndClear(int $articleId, string $html, string $url): void
    {
        $wordCount = str_word_count(strip_tags($html));
        $this->articles->addVersion($articleId, $html, $wordCount);

        $note = $this->notes->forArticle($articleId)['pipeline'] ?? [];
        $note['ambiguous_links'] = array_values(array_diff((array) ($note['ambiguous_links'] ?? []), [$url]));
        $note['link_rot_dead_urls'] = array_values(array_diff((array) ($note['link_rot_dead_urls'] ?? []), [$url]));
        $this->notes->save($articleId, 'pipeline', $note);
    }

    /** @return array{synced_to_wordpress:bool, wordpress_error:?string} */
    private function syncIfPublished(int $articleId, int $siteId): array
    {
        $article = $this->articles->find($siteId, $articleId);
        if ($article === null || $article['status'] !== 'PUBLISHED') {
            return ['synced_to_wordpress' => false, 'wordpress_error' => null];
        }

        try {
            (new WordPressPublishService())->update($articleId, $siteId);

            return ['synced_to_wordpress' => true, 'wordpress_error' => null];
        } catch (Throwable $e) {
            return ['synced_to_wordpress' => false, 'wordpress_error' => $e->getMessage()];
        }
    }
}

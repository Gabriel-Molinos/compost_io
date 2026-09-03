<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Monta o prompt final em camadas (docs/editorial/fluxo-editorial.md §22):
 *
 *   Prompt Base + Passo + Identidade do Site + Meta + Categoria + Brief + Memória
 *
 * O Prompt Base e os prompts de passo são arquivos versionados em `docs/ai/`.
 * As demais camadas vêm do banco (site, regras editoriais, meta, categoria).
 * A camada de Memória Editorial entra na Fase 6 — por ora é sempre vazia.
 *
 * Esta classe só monta texto: não chama nenhuma API externa.
 */
final class PromptBuilder
{
    /** Passos com arquivo de prompt dedicado em docs/ai/. */
    public const STEPS = ['planning', 'research', 'writing', 'seo', 'compliance', 'review', 'image'];

    private string $promptsDir;
    private SiteService $sites;
    private EditorialRuleService $rules;
    private CategoryService $categories;
    private GoalService $goals;
    private ArticleService $articles;

    /** Texto de "memória editorial" injetado pelo pipeline (regeneração — Fase 6, feedback da linhagem). */
    private ?string $editorialContext = null;

    public function __construct(?string $promptsDir = null)
    {
        $this->promptsDir = $promptsDir ?? dirname(__DIR__, 2) . '/docs/ai';
        $this->sites = new SiteService();
        $this->rules = new EditorialRuleService();
        $this->categories = new CategoryService();
        $this->goals = new GoalService();
        $this->articles = new ArticleService();
    }

    /**
     * @param array{title?:string,focus_keyword?:string,angle?:string,notes?:string} $brief
     * @param array{title?:string,slug?:string,meta_description?:string,content_html?:string,word_count?:int} $draft
     *        artigo já escrito — usado pelos passos de auditoria (seo/compliance/review)
     */
    public function build(
        string $step,
        int $siteId,
        ?int $goalId = null,
        ?int $categoryId = null,
        array $brief = [],
        array $draft = [],
    ): string {
        if (!in_array($step, self::STEPS, true)) {
            throw new InvalidArgumentException("Passo inválido: {$step}.");
        }

        $site = $this->sites->find($siteId);
        if ($site === null) {
            throw new InvalidArgumentException("Site {$siteId} não encontrado.");
        }

        $layers = [
            $this->readPrompt('base-editorial'),
            $this->readPrompt($step),
            $this->siteIdentityLayer($site),
        ];

        if ($goalId !== null) {
            $layers[] = $this->goalLayer($siteId, $goalId);
        }
        if ($categoryId !== null) {
            $layers[] = $this->categoryLayer($siteId, $categoryId);
        }
        $layers[] = $this->briefLayer($brief);
        $layers[] = $this->draftLayer($draft);
        $layers[] = $this->memoryLayer();
        if ($step === 'writing') {
            $layers[] = $this->internalLinksLayer($siteId, (string) ($site['wordpress_url'] ?? ''));
        }

        $layers = array_values(array_filter($layers, static fn (string $l): bool => trim($l) !== ''));

        return implode("\n\n---\n\n", $layers) . "\n";
    }

    /** Lê um arquivo de prompt de docs/ai/, sem o bloco de metadados `> ...` do topo. */
    private function readPrompt(string $name): string
    {
        $path = $this->promptsDir . '/' . $name . '.md';
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Prompt não encontrado: {$path}");
        }

        // Remove as linhas de citação (`> ...`) que documentam a camada para humanos.
        $lines = preg_split('/\R/', $raw) ?: [];
        $kept = array_filter($lines, static fn (string $line): bool => !str_starts_with(ltrim($line), '>'));
        $text = implode("\n", $kept);

        // Colapsa as linhas em branco que sobraram onde estava o bloco `> ...`.
        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** @param array<string,mixed> $site */
    private function siteIdentityLayer(array $site): string
    {
        $grouped = $this->rules->groupedForSite((int) $site['id']);

        $out = "# IDENTIDADE DO SITE\n\n"
            . '- Nome: ' . $this->val($site['name']) . "\n"
            . '- Nicho: ' . $this->val($site['niche'] ?? null) . "\n"
            . '- Idioma de publicação: ' . $this->val($site['language'] ?? null) . "\n"
            . '- Público-alvo: ' . $this->val($site['target_audience'] ?? null) . "\n"
            . '- Tom de voz: ' . $this->val($site['tone'] ?? null) . "\n";

        $identity = trim((string) ($site['editorial_identity'] ?? ''));
        if ($identity !== '') {
            $out .= "\n## Identidade editorial\n" . $identity . "\n";
        }

        $out .= "\n## Interesses (valorizar) — intensidade 1 a 5\n" . $this->ruleList($grouped['INTEREST']);
        $out .= "\n## Não-interesses (evitar) — intensidade 1 a 5\n" . $this->ruleList($grouped['NON_INTEREST']);

        return trim($out);
    }

    /** @param list<array<string,mixed>> $rules */
    private function ruleList(array $rules): string
    {
        if ($rules === []) {
            return "- (nenhum cadastrado)\n";
        }

        $lines = array_map(
            fn (array $r): string => '- [' . (int) $r['intensity'] . '] ' . $this->val($r['description']),
            $rules,
        );

        return implode("\n", $lines) . "\n";
    }

    private function goalLayer(int $siteId, int $goalId): string
    {
        $goal = $this->goals->find($siteId, $goalId);
        if ($goal === null) {
            throw new InvalidArgumentException("Meta {$goalId} não encontrada neste site.");
        }

        $out = '# META DO PERÍODO (' . $this->val($goal['period']) . ")\n\n"
            . '- Total de artigos no período: ' . (int) $goal['total_articles'] . "\n"
            . '- Diretrizes gerais: ' . $this->val($goal['general_guidelines'] ?? null) . "\n";

        $targets = $this->goals->categoryTargets($goalId);
        if ($targets !== []) {
            $names = [];
            foreach ($this->categories->allForSite($siteId) as $c) {
                $names[(int) $c['id']] = (string) $c['name'];
            }
            $out .= "- Distribuição por categoria:\n";
            foreach ($targets as $catId => $count) {
                $out .= '  - ' . ($names[$catId] ?? "categoria #{$catId}") . ': ' . $count . "\n";
            }
        }

        return trim($out);
    }

    private function categoryLayer(int $siteId, int $categoryId): string
    {
        $category = $this->categories->find($siteId, $categoryId);
        if ($category === null) {
            throw new InvalidArgumentException("Categoria {$categoryId} não encontrada neste site.");
        }

        return "# CATEGORIA: " . $this->val($category['name']) . "\n\n"
            . 'Diretrizes da categoria: ' . $this->val($category['guidelines'] ?? null);
    }

    /** @param array<string,mixed> $brief */
    private function briefLayer(array $brief): string
    {
        $fields = [
            'title'         => 'Título de trabalho',
            'focus_keyword' => 'Palavra-chave principal',
            'angle'         => 'Ângulo',
            'notes'         => 'Observações',
        ];

        $lines = [];
        foreach ($fields as $key => $label) {
            $value = trim((string) ($brief[$key] ?? ''));
            if ($value !== '') {
                $lines[] = "- {$label}: {$value}";
            }
        }

        if ($lines === []) {
            return "# BRIEF DO ARTIGO\n\n(Sem brief específico — este passo ainda vai definir o tema.)";
        }

        return "# BRIEF DO ARTIGO\n\n" . implode("\n", $lines);
    }

    /** @param array<string, mixed> $draft */
    private function draftLayer(array $draft): string
    {
        $html = trim((string) ($draft['content_html'] ?? ''));
        if ($html === '') {
            return '';
        }

        $meta = [];
        foreach (['title' => 'Título', 'slug' => 'Slug', 'meta_description' => 'Meta descrição', 'word_count' => 'Palavras'] as $k => $label) {
            $v = trim((string) ($draft[$k] ?? ''));
            if ($v !== '') {
                $meta[] = "- {$label}: {$v}";
            }
        }

        return "# ARTIGO PRODUZIDO (para auditar)\n\n"
            . ($meta !== [] ? implode("\n", $meta) . "\n\n" : '')
            . "Corpo (HTML):\n\n" . $html;
    }

    /**
     * Memória editorial (Fase 6): contexto aprendido do histórico do site —
     * hoje, o feedback das tentativas anteriores da linhagem na regeneração.
     * Injetado pelo `ArticlePipeline` via `setEditorialContext()`.
     */
    public function setEditorialContext(?string $text): void
    {
        $this->editorialContext = ($text !== null && trim($text) !== '') ? trim($text) : null;
    }

    private function memoryLayer(): string
    {
        return $this->editorialContext === null
            ? ''
            : "# MEMÓRIA EDITORIAL\n\n" . $this->editorialContext;
    }

    /**
     * Alvos reais de link interno (Fase 9): sem isso, o passo `writing` era
     * instruído a linkar artigos internos "chutando" um slug — o
     * `InternalLinkResolver` já limpa o `<a>` na publicação se não bater com
     * nada no WordPress, mas o resultado prático era quase nenhum link
     * interno sobrevivendo. Usa `?p=ID`, formato de permalink que funciona em
     * qualquer WordPress independente da estrutura de URL configurada — não
     * precisa de resolução posterior.
     */
    private function internalLinksLayer(int $siteId, string $wordpressUrl): string
    {
        $wordpressUrl = rtrim($wordpressUrl, '/');
        if ($wordpressUrl === '') {
            return '';
        }

        $recent = $this->articles->recentPublishedForLinking($siteId);
        if ($recent === []) {
            return "# ARTIGOS JÁ PUBLICADOS NESTE SITE\n\n(Nenhum ainda — não há artigo interno pra linkar. Não invente.)";
        }

        $lines = array_map(
            fn (array $a): string => '- ' . $this->val($a['title']) . ': ' . $wordpressUrl . '/?p=' . $a['wordpress_post_id'],
            $recent
        );

        return "# ARTIGOS JÁ PUBLICADOS NESTE SITE\n\n"
            . "Únicos alvos válidos pra link interno — use a URL exata de um destes se algum for relevante ao tema. "
            . "Não existe nenhum outro artigo além dos listados aqui.\n\n"
            . implode("\n", $lines);
    }

    private function val(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? '—' : $value;
    }
}

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
    private SiteSourceService $siteSources;

    /** Texto de "memória editorial" injetado pelo pipeline (regeneração — Fase 6, feedback da linhagem). */
    private ?string $editorialContext = null;

    /** Texto dos posts existentes no WordPress, injetado pelo pipeline só pro passo `planning` (Fase 9.3). */
    private ?string $existingContentContext = null;

    /** Texto dos alvos válidos de link interno, injetado pelo pipeline pros passos writing/seo/compliance (ver setInternalLinkCandidates()). */
    private ?string $internalLinkCandidatesContext = null;

    public function __construct(?string $promptsDir = null)
    {
        $this->promptsDir = $promptsDir ?? dirname(__DIR__, 2) . '/docs/ai';
        $this->sites = new SiteService();
        $this->rules = new EditorialRuleService();
        $this->categories = new CategoryService();
        $this->goals = new GoalService();
        $this->articles = new ArticleService();
        $this->siteSources = new SiteSourceService();
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
        if (in_array($step, ['writing', 'seo', 'compliance'], true)) {
            $layers[] = $this->internalLinksLayer();
        }
        if ($step === 'planning') {
            $layers[] = $this->categoriesLayer($siteId);
            $layers[] = $this->existingContentLayer();
        }
        if ($step === 'research') {
            $layers[] = $this->siteSourcesLayer($siteId);
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

    /**
     * Lista real das categorias cadastradas do site (achado real, 2026-09-10):
     * sem isso, o passo `planning` só via categoria pela meta (`goalLayer()`,
     * condicional a ter meta com distribuição por categoria) — sem meta, a IA
     * chutava um nome que nunca batia com o cadastro real, e o artigo ficava
     * sem categoria (`ArticlePipeline::matchCategory()` reprova em silêncio).
     */
    private function categoriesLayer(int $siteId): string
    {
        $categories = $this->categories->allForSite($siteId);
        if ($categories === []) {
            return "# CATEGORIAS CADASTRADAS DO SITE\n\n"
                . "(Nenhuma cadastrada ainda — deixe `category` vazio em vez de inventar uma.)";
        }

        $names = array_map(fn (array $c): string => '- ' . $this->val($c['name']), $categories);

        return "# CATEGORIAS CADASTRADAS DO SITE\n\n"
            . "Escolha `category` como exatamente um destes nomes (cópia exata, sem inventar variação) — "
            . "o que melhor encaixa o tema. Se nenhum encaixar bem, deixe `category` vazio em vez de forçar um "
            . "nome que não está na lista.\n\n"
            . implode("\n", $names);
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
     * Posts já existentes no WordPress do site (achado real, 2026-09-09):
     * o passo `planning` já era instruído a "comparar com os artigos já
     * publicados do site" pra evitar canibalização, mas nunca recebia
     * nenhuma lista de verdade — só chutava. Diferente de
     * `internalLinksLayer()` (que só sabe dos artigos que ESTE app já
     * publicou, via tabela local `articles`), esta lista vem direto do
     * WordPress (`ArticlePipeline::run()`, antes do passo `planning`) —
     * pega também posts que já existiam no site antes dele entrar na
     * plataforma, ou publicados por fora. `setExistingContentContext()`
     * fica de fora do `memoryLayer()` de propósito: memória editorial é
     * sobre feedback de tentativas passadas, isto aqui é sobre o que já
     * existe no site — confundir os dois numa seção só ("MEMÓRIA
     * EDITORIAL") deixaria a instrução ambígua pra IA.
     */
    public function setExistingContentContext(?string $text): void
    {
        $this->existingContentContext = ($text !== null && trim($text) !== '') ? trim($text) : null;
    }

    private function existingContentLayer(): string
    {
        return $this->existingContentContext === null
            ? ''
            : "# POSTS JÁ PUBLICADOS NO WORDPRESS DESTE SITE\n\n"
                . "Antes de propor o tema, confira esta lista de verdade (não é só o que este sistema já gerou — "
                . "inclui qualquer post que já exista no site). Se o tema/ângulo pedido for muito parecido com "
                . "algum destes, ajuste o ângulo pra não duplicar ou marque `cannibalization_risk`.\n\n"
                . $this->existingContentContext;
    }

    /**
     * Alvos reais de link interno (Fase 9, revisto 2026-09-14): sem isso, o
     * passo `writing` era instruído a linkar artigos internos "chutando" um
     * slug — o `InternalLinkResolver` já limpa o `<a>` na publicação se não
     * bater com nada no WordPress, mas o resultado prático era quase nenhum
     * link interno sobrevivendo. Antes vinha só da tabela local `articles`
     * (limitada aos artigos gerados por este app, mais recentes primeiro, e
     * vulnerável a ficar desatualizada — achado real: chegou a zerar
     * enquanto o site tinha 165 posts reais publicados). Agora recebe a
     * mesma lista, vinda direto do WordPress, que `existingContentLayer()`
     * usa pro `planning` — qualquer post publicado de verdade no site,
     * antigo ou novo, é um alvo válido (injetado por
     * `ArticlePipeline::run()` via setInternalLinkCandidates(), com a URL
     * (`link`) real do post — já é o permalink canônico, não precisa de
     * resolução posterior).
     */
    public function setInternalLinkCandidates(?string $text): void
    {
        $this->internalLinkCandidatesContext = ($text !== null && trim($text) !== '') ? trim($text) : null;
    }

    /**
     * Fontes confiáveis cadastradas pelo redator pra este site (achado real
     * 2026-09-15): o passo `research` não tem busca na web de verdade — só a
     * memória de treinamento da IA, daí toda a regra "nunca inventar URL" em
     * docs/ai/research.md. Isto dá um pool prioritário de fontes já validadas
     * por um humano; a IA ainda pode citar outras fontes conhecidas (mesma
     * regra de nunca inventar se aplica a elas também). Consulta o banco
     * direto (`SiteSourceService`), sem precisar de injeção pelo pipeline —
     * diferente de `internalLinkCandidatesContext`/`existingContentContext`,
     * que dependem de uma chamada HTTP real ao WordPress.
     */
    private function siteSourcesLayer(int $siteId): string
    {
        $digest = $this->siteSources->digestForPrompt($siteId);

        return $digest === null
            ? ''
            : "# FONTES CONFIÁVEIS CADASTRADAS PELO REDATOR DESTE SITE\n\n"
                . "Priorize estas fontes quando forem relevantes ao tema deste artigo — foram validadas por um "
                . "humano de antemão. Não são a única opção: se nenhuma cobrir a afirmação, ainda pode citar outra "
                . "fonte confiável conhecida, seguindo a mesma regra de nunca inventar URL.\n\n"
                . $digest;
    }

    private function internalLinksLayer(): string
    {
        return $this->internalLinkCandidatesContext === null
            ? "# ARTIGOS JÁ PUBLICADOS NESTE SITE\n\n(Nenhum ainda — não há artigo interno pra linkar. Não invente.)"
            : "# ARTIGOS JÁ PUBLICADOS NESTE SITE\n\n"
                . "Únicos alvos válidos pra link interno — nunca invente ou use uma URL fora desta lista. Mas a "
                . "lista inteira NÃO é relevante: é só o inventário de tudo que já existe publicado, pode ter "
                . "assunto bem diferente do artigo atual. Antes de linkar, julgue pelo título: o artigo de destino "
                . "precisa ter relação genuína com o trecho onde o link vai entrar — complementa, aprofunda ou dá "
                . "contexto pro que está sendo dito ali. Nunca linke só pra bater a cota de 3 a 5 — é melhor um "
                . "artigo com 1 link interno de verdade relevante do que 5 forçados sem relação real com o "
                . "tema.\n\n"
                . $this->internalLinkCandidatesContext;
    }

    private function val(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? '—' : $value;
    }
}

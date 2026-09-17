<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Integrations\WordPress\ExternalLinkVerifier;
use App\Integrations\WordPress\InternalLinkResolver;
use App\Integrations\WordPress\WordPressException;
use App\Services\AiExecutionService;
use App\Services\ArticleNoteService;
use App\Services\ArticleReviewService;
use App\Services\ArticleService;
use App\Services\AuthService;
use App\Services\CategoryService;
use App\Services\DailyLimitExceededException;
use App\Services\ExternalLinkSuggestionService;
use App\Services\FeedbackService;
use App\Services\GoalService;
use App\Services\ImageService;
use App\Services\InternalLinkSuggestionService;
use App\Services\ScheduleService;
use App\Services\WordPressConnectionService;
use App\Queue\ArticleJobHandlers;
use App\Queue\Job;
use App\Queue\Queue;
use App\Services\Pipeline\ArticlePipeline;
use App\Support\ImageStorage;
use App\Services\Pipeline\PipelineException;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\View;
use Throwable;

/**
 * Produção de artigos pela IA (Fase 4.4a). "Gerar"/"Regenerar" só fazem a
 * parte síncrona e rápida (criar a linha do artigo) e despacham um `Job` pra
 * fila (Fase 9.1b) — quem chama IA de verdade é `bin/worker.php` (ou o
 * próprio driver síncrono, inline, se `QUEUE_DRIVER=sync`).
 */
final class ProductionController extends Controller
{
    private ArticleService $articles;
    private AiExecutionService $executions;

    public function __construct()
    {
        $this->articles = new ArticleService();
        $this->executions = new AiExecutionService();
    }

    public function index(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        $categories = (new CategoryService())->allForSite((int) $site['id']);

        // Filtro melhor na Produção (pedido do responsável, 2026-09-17):
        // além das abas de grupo já existentes, agora dá pra refinar por
        // status exato dentro do grupo, categoria, origem (manual/automático)
        // e busca por título/palavra-chave — tudo combinável, sempre via
        // querystring (link compartilhável/voltável, nunca JS escondendo
        // linha — mesma filosofia da paginação).
        $categoryId = ($_GET['category_id'] ?? '') !== '' && (int) $_GET['category_id'] > 0
            ? (int) $_GET['category_id']
            : null;
        if ($categoryId !== null && !in_array($categoryId, array_column($categories, 'id'), true)) {
            $categoryId = null; // categoria de outro site (forjado/defasado) — ignora em vez de dar 0 resultado silencioso
        }
        $origin = in_array($_GET['origin'] ?? '', ['AUTO', 'MANUAL'], true) ? $_GET['origin'] : null;
        $search = trim((string) ($_GET['q'] ?? ''));
        $search = $search !== '' ? $search : null;

        $counts = $this->articles->countsByStatusGroup((int) $site['id'], $categoryId, $origin, $search);
        $statusGroup = (string) ($_GET['status'] ?? 'all');
        if (!isset($counts[$statusGroup])) {
            $statusGroup = 'all';
        }

        $exactStatus = (string) ($_GET['exact_status'] ?? '');
        $exactStatus = in_array($exactStatus, ArticleService::allStatuses(), true) ? $exactStatus : null;
        // Status exato só faz sentido se pertencer ao grupo escolhido — senão
        // os sub-chips (calculados a partir de $statusGroup) não teriam como
        // mostrar ele marcado, e o usuário perderia a noção de onde está.
        if ($exactStatus !== null && !in_array($exactStatus, ArticleService::statusGroups()[$statusGroup] ?? [], true)) {
            $exactStatus = null;
        }
        $exactCounts = $this->articles->countsByExactStatus((int) $site['id'], $statusGroup, $categoryId, $origin, $search);

        $totalForGroup = $exactStatus !== null
            ? $this->articles->countFiltered((int) $site['id'], $statusGroup, $exactStatus, $categoryId, $origin, $search)
            : $counts[$statusGroup];
        $totalPages = max(1, (int) ceil($totalForGroup / ArticleService::PER_PAGE));
        $page = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));

        View::render('sites/production/index', [
            'title'        => 'Produção · ' . $site['name'],
            'site'         => $site,
            'articles'     => $this->articles->allForSite((int) $site['id'], $statusGroup, $page, ArticleService::PER_PAGE, $exactStatus, $categoryId, $origin, $search),
            'goals'        => (new GoalService())->allForSite((int) $site['id']),
            'categories'   => $categories,
            'counts'       => $counts,
            'exactCounts'  => $exactCounts,
            'statusGroup'  => $statusGroup,
            'exactStatus'  => $exactStatus,
            'categoryId'   => $categoryId,
            'origin'       => $origin,
            'search'       => $search,
            'filtersActive' => $statusGroup !== 'all' || $exactStatus !== null || $categoryId !== null || $origin !== null || $search !== null,
            'totalForGroup' => $totalForGroup,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'tourReviewArticle' => $this->articles->firstInReview((int) $site['id']),
        ]);
    }

    public function generate(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        // goal_id / category_id precisam ser deste site (POST pode vir forjado ou defasado).
        $goalId = null;
        if (($_POST['goal_id'] ?? '') !== '' && (new GoalService())->find((int) $site['id'], (int) $_POST['goal_id']) !== null) {
            $goalId = (int) $_POST['goal_id'];
        }
        $categoryId = null;
        if (($_POST['category_id'] ?? '') !== '' && (new CategoryService())->find((int) $site['id'], (int) $_POST['category_id']) !== null) {
            $categoryId = (int) $_POST['category_id'];
        }

        $pipeline = new ArticlePipeline();

        // Guarda de custo (requisitos §95) — check + create atômicos (Fase 9,
        // fecha a race condition de countCreatedLast24h isolado).
        try {
            $articleId = $this->articles->createWithDailyLimit(
                (int) $site['id'],
                ArticleService::DAILY_LIMIT,
                fn () => $pipeline->prepareGenerate((int) $site['id'], $goalId),
            );
        } catch (DailyLimitExceededException $e) {
            Session::flash('error', $e->getMessage());
            Http::redirect('/sites/' . $site['id'] . '/production');
            return;
        }

        try {
            $this->dispatchArticleJob(new Job('article.generate', [
                'article_id'  => $articleId,
                'site_id'     => (int) $site['id'],
                'goal_id'     => $goalId,
                'category_id' => $categoryId,
                // Quem clicou "Gerar rascunho" agora — só pra notificação de
                // ERROR (ArticleJobHandlers); a geração automática diária
                // (bin/worker.php) nunca manda isso, não tem humano por trás.
                'user_id'     => AuthService::id(),
            ]));
        } catch (PipelineException $e) {
            // Só acontece com QUEUE_DRIVER=sync (o handler roda inline, nesta
            // mesma requisição) — com redis, o dispatch só enfileira.
            Session::flash('error', 'Falha na geração (' . $e->step . '): ' . $e->getMessage()
                . ' — rascunho #' . $e->articleId . ' ficou com status ERROR.');
            Http::redirect('/sites/' . $site['id'] . '/production/' . $e->articleId);
            return;
        } catch (Throwable $e) {
            Session::flash('error', 'Erro inesperado na geração: ' . $e->getMessage());
            Http::redirect('/sites/' . $site['id'] . '/production/' . $articleId);
            return;
        }

        Session::flash('success', 'Geração iniciada — atualize a página em alguns segundos.');
        Http::redirect('/sites/' . $site['id'] . '/production/' . $articleId);
    }

    /**
     * Registra os handlers de artigo e despacha. Com `QUEUE_DRIVER=sync`
     * (padrão hoje) isso ainda roda o pipeline inline, na mesma requisição —
     * daí o `set_time_limit`, igual antes da Fase 9.1b. Com `redis`, só
     * enfileira e retorna na hora (quem processa é `bin/worker.php`).
     */
    private function dispatchArticleJob(Job $job): void
    {
        set_time_limit(900);
        @ini_set('max_execution_time', '900');

        $queue = new Queue();
        ArticleJobHandlers::register($queue, new ArticlePipeline(), $this->articles);
        $queue->dispatch($job);
    }

    public function show(string $siteId, string $articleId): void
    {
        $site = $this->requireSite($siteId);
        $article = $this->articles->find((int) $site['id'], (int) $articleId) ?? $this->notFound();

        $schedules = new ScheduleService();

        // Enquanto o worker ainda está rodando (Fase 9.1b), a página se atualiza sozinha.
        $generating = in_array($article['status'], ['PLANNED', 'IN_PROGRESS'], true);

        $internalCandidates = $this->publishedWordPressPosts((int) $site['id']);
        // A checagem de sugestão travada (freshNotes) usa o pool local, que é
        // o mesmo que `InternalLinkSuggestionService` consultou pra gerar a
        // sugestão em primeiro lugar (tem o wordpress_post_id que a sugestão
        // guardou) — diferente do painel acima, que agora mostra QUALQUER
        // post publicado de verdade, direto do WordPress.
        $notes = $this->freshNotes((int) $article['id'], $this->articles->recentPublishedForLinking((int) $site['id']));

        // Checklist de pré-aprovação (RF-008) — só faz sentido mostrar
        // enquanto o Redator-Chefe ainda pode agir (IN_REVIEW); pra qualquer
        // outro status já foi decidido, exibir aqui só confundiria.
        $checklist = $article['status'] === 'IN_REVIEW'
            ? (new ArticleReviewService())->checklist($article)
            : null;

        View::render('sites/production/show', [
            'title'       => ($article['title'] ?: 'Rascunho #' . $article['id']) . ' · ' . $site['name'],
            'metaRefresh' => $generating ? 5 : null,
            'site'       => $site,
            'article'    => $article,
            'version'    => $this->articles->latestVersion((int) $article['id']),
            'sources'    => $this->articles->sources((int) $article['id']),
            'internalCandidates' => $internalCandidates,
            'executions' => $this->executions->forArticle((int) $article['id']),
            'totalCost'  => $this->executions->totalCostForArticle((int) $article['id']),
            'notes'      => $notes,
            'images'     => (new ImageService())->forArticle((int) $article['id']),
            'schedule'   => $schedules->activeForArticle((int) $article['id']),
            'lastSchedule' => $schedules->latestForArticle((int) $article['id']),
            'authors'    => $schedules->authorsForSite((int) $site['id']),
            // Data/hora que o agendamento automático vai usar — só pra mostrar
            // antes de agendar (RF-011 revisto: o Redator-Chefe não escolhe mais
            // a data, só autor + imagem). Calculado sempre; a view só exibe
            // quando o artigo está APPROVED e ainda sem agendamento.
            'nextSlot'   => $article['status'] === 'APPROVED' ? $schedules->nextAvailableSlot((int) $site['id']) : null,
            'feedback'   => (new FeedbackService())->forContext(
                (int) $article['id'],
                $article['lineage_id'] !== null ? (int) $article['lineage_id'] : null,
            ),
            'checklist'  => $checklist,
        ]);
    }

    /**
     * Posts publicados de verdade no WordPress do site — qualquer um,
     * antigo ou novo (achado real 2026-09-14, mesmo ajuste do passo de
     * escrita — ver `ArticlePipeline::existingWordPressPostsDigest()`):
     * o painel "Links internos disponíveis" antes só listava os artigos que
     * a tabela local `articles` rastreava como publicados, que pode sair de
     * sincronia com a realidade (chegou a mostrar 0 enquanto o site tinha
     * 165 posts publicados de verdade). Direto da API, sem WordPress
     * conectado (ou falha de rede) devolve lista vazia — o painel já lida
     * com isso mostrando "nenhum artigo publicado".
     *
     * @return list<array{title:string, link:string}>
     */
    private function publishedWordPressPosts(int $siteId): array
    {
        try {
            return (new WordPressConnectionService())->client($siteId)->listRecentPosts();
        } catch (WordPressException) {
            return [];
        }
    }

    /**
     * Notas do artigo, mas descartando sugestões de link interno que
     * apontam pra um artigo que não está mais na lista real de publicados
     * (achado real 2026-09-14): a sugestão fica guardada na nota de quando
     * foi gerada — se o post-alvo foi apagado/despublicado no WordPress
     * depois disso, a nota velha continuava mostrando o link morto direto,
     * sem passar pela verificação ao vivo que `$internalCandidates` já tem.
     * Se alguma sugestão foi descartada, regrava a nota já limpa — não
     * precisa checar de novo nas próximas visitas a esta página.
     *
     * @param list<array{title:string, wordpress_post_id:int}> $internalCandidates
     * @return array<string, array<string, mixed>>
     */
    private function freshNotes(int $articleId, array $internalCandidates): array
    {
        $notesService = new ArticleNoteService();
        $notes = $notesService->forArticle($articleId);

        $suggestions = $notes['pipeline']['internal_link_suggestions'] ?? null;
        if (!is_array($suggestions) || $suggestions === []) {
            return $notes;
        }

        $validPostIds = array_map(static fn (array $c): int => (int) $c['wordpress_post_id'], $internalCandidates);
        $fresh = array_values(array_filter(
            $suggestions,
            static fn ($s): bool => is_array($s) && in_array((int) ($s['wordpress_post_id'] ?? 0), $validPostIds, true)
        ));

        if (count($fresh) !== count($suggestions)) {
            $notes['pipeline']['internal_link_suggestions'] = $fresh;
            $notesService->save($articleId, 'pipeline', $notes['pipeline']);
        }

        return $notes;
    }

    /** Regenera um artigo rejeitado (RF-010): nova tentativa na mesma linhagem. */
    public function regenerate(string $siteId, string $articleId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $article = $this->articles->find((int) $site['id'], (int) $articleId) ?? $this->notFound();

        // Validação/BLOCKED-check é síncrona e rápida (sem IA) — falha aqui
        // ainda vira flash normal, redirecionando pro artigo anterior.
        try {
            $prepared = (new ArticlePipeline())->prepareRegenerate((int) $article['id'], AuthService::id());
        } catch (PipelineException $e) {
            Session::flash('error', $e->getMessage());
            Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id']);
            return;
        }

        try {
            $this->dispatchArticleJob(new Job('article.regenerate', [
                'article_id'  => $prepared['article_id'],
                'site_id'     => $prepared['site_id'],
                'goal_id'     => $prepared['goal_id'],
                'category_id' => $prepared['category_id'],
                'lineage_id'  => $prepared['lineage_id'],
                'user_id'     => AuthService::id(),
            ]));
        } catch (PipelineException $e) {
            // Só acontece com QUEUE_DRIVER=sync — ver dispatchArticleJob().
            Session::flash('error', $e->getMessage());
            Http::redirect('/sites/' . $site['id'] . '/production/' . $prepared['article_id']);
            return;
        } catch (Throwable $e) {
            Session::flash('error', 'Erro inesperado na regeneração: ' . $e->getMessage());
            Http::redirect('/sites/' . $site['id'] . '/production/' . $prepared['article_id']);
            return;
        }

        Session::flash('success', 'Nova tentativa iniciada — atualize a página em alguns segundos.');
        Http::redirect('/sites/' . $site['id'] . '/production/' . $prepared['article_id']);
    }

    /**
     * Redator-Chefe edita o corpo do rascunho direto no dashboard, evitando
     * um ciclo de rejeição+regeneração — com custo real de IA — só
     * pra corrigir um trecho. Só em `IN_REVIEW` (mesma janela da decisão de
     * aprovar/rejeitar); grava como nova versão em `article_versions`
     * (histórico preservado, mesmo padrão que os passos da IA já usam).
     */
    public function updateContent(string $siteId, string $articleId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $article = $this->articles->find((int) $site['id'], (int) $articleId) ?? $this->notFound();

        // Verifica cada link interno/externo do corpo com requisição HTTP de
        // verdade (`InternalLinkResolver`/`ExternalLinkVerifier`) — com vários
        // links, retry e backoff de rate-limit somados passam fácil dos 30s
        // padrão do PHP (achado real 2026-09-14: artigo com fontes suficientes
        // já bateu esse limite e voltou erro fatal pro redator no meio do salvamento).
        set_time_limit(120);

        if ($article['status'] !== 'IN_REVIEW') {
            Session::flash('error', 'Só é possível editar o corpo enquanto o artigo está em revisão.');
            Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id']);
            return;
        }

        $content = trim((string) ($_POST['content_html'] ?? ''));
        if ($content === '') {
            Session::flash('error', 'O corpo não pode ficar vazio.');
            Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id']);
            return;
        }

        // Mesma checagem que a geração automática já faz (ArticlePipeline::run())
        // — edição manual também pode introduzir um link interno inventado ou
        // uma fonte morta, e essa era a única porta de entrada de conteúdo que
        // não passava por nenhum verificador (achado real, 2026-09-09).
        $noteBits = [];
        try {
            $client = (new WordPressConnectionService())->client((int) $site['id']);
            $internal = (new InternalLinkResolver($client, (string) ($site['wordpress_url'] ?? '')))->resolve($content);
            $content = $internal['html'];
            if ($internal['unwrapped'] > 0) {
                $noteBits[] = $internal['unwrapped'] . ' link(s) interno(s) removido(s) por não bater com nenhum post real';
            }
        } catch (WordPressException) {
            // Site ainda sem WordPress conectado — segue sem essa checagem específica.
        }

        $external = (new ExternalLinkVerifier())->verify($content);
        $content = $external['html'];
        if ($external['unwrapped'] > 0) {
            $noteBits[] = $external['unwrapped'] . ' link(s) de fonte removido(s) por estarem fora do ar (404)';
        }
        $ambiguousLinks = $external['ambiguous'];
        if ($ambiguousLinks !== []) {
            $noteBits[] = count($ambiguousLinks) . ' link(s) não confirmado(s) automaticamente (confira à mão): '
                . implode(', ', $ambiguousLinks);
        }

        $wordCount = str_word_count(strip_tags($content));
        $this->articles->addVersion((int) $article['id'], $content, $wordCount);
        if ($noteBits !== [] || $ambiguousLinks !== []) {
            (new ArticleNoteService())->save((int) $article['id'], 'pipeline', [
                'warnings' => $noteBits,
                'ambiguous_links' => $ambiguousLinks,
            ]);
        }
        Session::flash('success', 'Corpo atualizado.' . ($noteBits !== [] ? ' (' . implode(' · ', $noteBits) . ')' : ''));
        Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id']);
    }

    /**
     * "Gerar mais links externos relacionados" (editor de corpo, achado real
     * 2026-09-10) — sob demanda, o redator clica quando quer mais opções pra
     * citar. Cada sugestão só aparece se confirmar viva de verdade
     * (`ExternalLinkSuggestionService`); as que já existiam continuam lá,
     * isso só acrescenta.
     */
    public function suggestExternalLinks(string $siteId, string $articleId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $article = $this->articles->find((int) $site['id'], (int) $articleId) ?? $this->notFound();

        // Chamada de IA + até 6 verificações HTTP de verdade (cada uma com
        // retry/backoff próprio) — soma fácil mais que os 30s padrão do PHP
        // (mesmo achado real de updateContent(), 2026-09-14).
        set_time_limit(120);

        try {
            $added = (new ExternalLinkSuggestionService())->suggest((int) $article['id'], (int) $site['id']);
            Session::flash(
                'success',
                $added === []
                    ? 'Nenhuma fonte nova confirmada como confiável agora — tente de novo em um momento.'
                    : count($added) . ' fonte(s) nova(s) adicionada(s) ao painel de links externos.'
            );
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id'] . '#editar-corpo');
    }

    /**
     * "Sugerir por relevância" pro painel de links internos (editor de corpo,
     * achado real 2026-09-10) — sob demanda, a IA olha o conteúdo do artigo
     * atual e escolhe quais artigos antigos já publicados genuinamente
     * combinam, em vez do redator ter que julgar uma lista só por recência.
     * Nunca edita conteúdo — só reordena/filtra a lista que o próprio
     * redator copia e cola.
     */
    public function suggestInternalLinks(string $siteId, string $articleId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $article = $this->articles->find((int) $site['id'], (int) $articleId) ?? $this->notFound();

        // Só uma chamada de IA aqui (sem verificação HTTP em lote) — tempo
        // de resposta varia, mesma margem de segurança dos outros dois
        // botões deste editor.
        set_time_limit(120);

        try {
            $suggestions = (new InternalLinkSuggestionService())->suggest((int) $article['id'], (int) $site['id']);
            Session::flash(
                'success',
                $suggestions === []
                    ? 'Nenhum artigo antigo relevante encontrado pra este conteúdo agora.'
                    : count($suggestions) . ' sugestão(ões) de link interno por relevância.'
            );
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id'] . '#editar-corpo');
    }

    /** Redator-Chefe aprova o artigo (RF-008): IN_REVIEW → APPROVED. */
    public function approve(string $siteId, string $articleId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $article = $this->articles->find((int) $site['id'], (int) $articleId) ?? $this->notFound();

        try {
            (new ArticleReviewService())->approve((int) $article['id']);
            Session::flash('success', 'Artigo aprovado. Agora pode ser agendado.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id']);
    }

    /** Redator-Chefe rejeita o artigo (RF-009, RB-004): IN_REVIEW → REVISION_REQUESTED + feedback. */
    public function reject(string $siteId, string $articleId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $article = $this->articles->find((int) $site['id'], (int) $articleId) ?? $this->notFound();

        $reason = (string) ($_POST['reason'] ?? '');
        $justification = (string) ($_POST['justification'] ?? '');

        try {
            (new ArticleReviewService())->requestRevision(
                (int) $article['id'],
                AuthService::id(),
                $reason,
                $justification,
            );
            Session::flash('success', 'Artigo rejeitado. Motivo registrado — pode ser regenerado.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id']);
    }

    /** Redator-Chefe escolhe a imagem destacada (fluxo-editorial §25, Fase 5.4). */
    public function selectImage(string $siteId, string $articleId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $article = $this->articles->find((int) $site['id'], (int) $articleId) ?? $this->notFound();

        $images = new ImageService();
        $imageId = (int) ($_POST['image_id'] ?? 0);
        $image = $imageId > 0 ? $images->find((int) $article['id'], $imageId) : null;

        if ($image === null || $image['role'] !== 'FEATURED') {
            Session::flash('error', 'Selecione uma das opções de imagem destacada.');
        } else {
            $images->select((int) $article['id'], $imageId);
            Session::flash('success', 'Imagem destacada escolhida.');
        }

        Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id'] . '#imagens');
    }

    /** Redator-Chefe descarta uma imagem inadequada (Fase 5.4). */
    public function deleteImage(string $siteId, string $articleId, string $imageId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $article = $this->articles->find((int) $site['id'], (int) $articleId) ?? $this->notFound();

        $removed = (new ImageService())->delete((int) $article['id'], (int) $imageId);
        if ($removed !== null) {
            (new ImageStorage())->delete((string) $removed['url']);
            Session::flash('success', 'Imagem removida.');
        } else {
            Session::flash('error', 'Imagem não encontrada.');
        }

        Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id'] . '#imagens');
    }

    public function destroy(string $siteId, string $articleId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $article = $this->articles->find((int) $site['id'], (int) $articleId) ?? $this->notFound();

        $this->articles->softDelete((int) $article['id']);
        Session::flash('success', 'Rascunho descartado.');
        Http::redirect('/sites/' . $site['id'] . '/production');
    }
}

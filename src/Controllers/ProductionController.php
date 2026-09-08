<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AiExecutionService;
use App\Services\ArticleNoteService;
use App\Services\ArticleReviewService;
use App\Services\ArticleService;
use App\Services\AuthService;
use App\Services\CategoryService;
use App\Services\DailyLimitExceededException;
use App\Services\FeedbackService;
use App\Services\GoalService;
use App\Services\ImageService;
use App\Services\ScheduleService;
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

        View::render('sites/production/index', [
            'title'      => 'Produção · ' . $site['name'],
            'site'       => $site,
            'articles'   => $this->articles->allForSite((int) $site['id']),
            'goals'      => (new GoalService())->allForSite((int) $site['id']),
            'categories' => (new CategoryService())->allForSite((int) $site['id']),
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

        View::render('sites/production/show', [
            'title'       => ($article['title'] ?: 'Rascunho #' . $article['id']) . ' · ' . $site['name'],
            'metaRefresh' => $generating ? 5 : null,
            'site'       => $site,
            'article'    => $article,
            'version'    => $this->articles->latestVersion((int) $article['id']),
            'sources'    => $this->articles->sources((int) $article['id']),
            'executions' => $this->executions->forArticle((int) $article['id']),
            'totalCost'  => $this->executions->totalCostForArticle((int) $article['id']),
            'notes'      => (new ArticleNoteService())->forArticle((int) $article['id']),
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
        ]);
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

        $wordCount = str_word_count(strip_tags($content));
        $this->articles->addVersion((int) $article['id'], $content, $wordCount);
        Session::flash('success', 'Corpo atualizado.');
        Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id']);
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

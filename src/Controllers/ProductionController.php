<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AiExecutionService;
use App\Services\ArticleNoteService;
use App\Services\ArticleReviewService;
use App\Services\ArticleService;
use App\Services\AuthService;
use App\Services\CategoryService;
use App\Services\FeedbackService;
use App\Services\GoalService;
use App\Services\ImageService;
use App\Services\ScheduleService;
use App\Services\Pipeline\ArticlePipeline;
use App\Support\ImageStorage;
use App\Services\Pipeline\PipelineException;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\View;
use Throwable;

/**
 * Produção de artigos pela IA (Fase 4.4a). "Gerar" roda o pipeline síncrono
 * (planning → research → writing) — várias chamadas ao Gemini numa requisição só.
 */
final class ProductionController extends Controller
{
    /** Teto diário de gerações por site enquanto não há limite de custo (§95). */
    private const DAILY_LIMIT = 15;

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

        // Guarda de custo enquanto não há limite por site/mês (requisitos §95).
        if ($this->articles->countCreatedLast24h((int) $site['id']) >= self::DAILY_LIMIT) {
            Session::flash('error', 'Limite de ' . self::DAILY_LIMIT . ' gerações por dia neste site atingido. Tente amanhã.');
            Http::redirect('/sites/' . $site['id'] . '/production');
            return;
        }

        // goal_id / category_id precisam ser deste site (POST pode vir forjado ou defasado).
        $goalId = null;
        if (($_POST['goal_id'] ?? '') !== '' && (new GoalService())->find((int) $site['id'], (int) $_POST['goal_id']) !== null) {
            $goalId = (int) $_POST['goal_id'];
        }
        $categoryId = null;
        if (($_POST['category_id'] ?? '') !== '' && (new CategoryService())->find((int) $site['id'], (int) $_POST['category_id']) !== null) {
            $categoryId = (int) $_POST['category_id'];
        }

        // O pipeline síncrono faz 6 chamadas ao gemini-2.5-pro — pode passar de 2 min.
        set_time_limit(900);
        @ini_set('max_execution_time', '900');

        try {
            $result = (new ArticlePipeline())->generate((int) $site['id'], $goalId, $categoryId);
        } catch (PipelineException $e) {
            Session::flash('error', 'Falha na geração (' . $e->step . '): ' . $e->getMessage()
                . ' — rascunho #' . $e->articleId . ' ficou incompleto.');
            Http::redirect('/sites/' . $site['id'] . '/production/' . $e->articleId);
            return;
        } catch (Throwable $e) {
            Session::flash('error', 'Erro inesperado na geração: ' . $e->getMessage());
            Http::redirect('/sites/' . $site['id'] . '/production');
            return;
        }

        $recLabels = ['ready_for_human' => 'pronto p/ revisão', 'needs_fix' => 'precisa de ajustes', 'discard' => 'IA sugere descartar'];
        $msg = 'Rascunho em revisão: "' . $result['title'] . '" · ' . $result['word_count']
            . ' palavras · custo ~US$ ' . number_format($result['cost'], 4)
            . ' · parecer IA: ' . ($recLabels[$result['recommendation']] ?? $result['recommendation']);
        $imageCount = (new ImageService())->countForArticle((int) $result['article_id']);
        if ($imageCount > 0) {
            $msg .= ' · ' . $imageCount . ' imagem(ns) gerada(s)';
        }
        if ($result['warnings'] !== []) {
            $msg .= ' · ' . count($result['warnings']) . ' aviso(s)';
        }
        Session::flash('success', $msg);
        Http::redirect('/sites/' . $site['id'] . '/production/' . $result['article_id']);
    }

    public function show(string $siteId, string $articleId): void
    {
        $site = $this->requireSite($siteId);
        $article = $this->articles->find((int) $site['id'], (int) $articleId) ?? $this->notFound();

        $schedules = new ScheduleService();

        View::render('sites/production/show', [
            'title'      => ($article['title'] ?: 'Rascunho #' . $article['id']) . ' · ' . $site['name'],
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

        set_time_limit(900);
        @ini_set('max_execution_time', '900');

        try {
            $result = (new ArticlePipeline())->regenerate((int) $article['id']);
        } catch (PipelineException $e) {
            Session::flash('error', $e->getMessage());
            Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id']);
            return;
        } catch (Throwable $e) {
            Session::flash('error', 'Erro inesperado na regeneração: ' . $e->getMessage());
            Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id']);
            return;
        }

        Session::flash('success', 'Nova tentativa gerada: "' . $result['title'] . '" · '
            . $result['word_count'] . ' palavras · custo ~US$ ' . number_format($result['cost'], 4)
            . ($result['warnings'] !== [] ? ' · ' . count($result['warnings']) . ' aviso(s)' : ''));
        Http::redirect('/sites/' . $site['id'] . '/production/' . $result['article_id']);
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

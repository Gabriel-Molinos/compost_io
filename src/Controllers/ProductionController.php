<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AiExecutionService;
use App\Services\ArticleNoteService;
use App\Services\ArticleService;
use App\Services\CategoryService;
use App\Services\GoalService;
use App\Services\Pipeline\ArticlePipeline;
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

        View::render('sites/production/show', [
            'title'      => ($article['title'] ?: 'Rascunho #' . $article['id']) . ' · ' . $site['name'],
            'site'       => $site,
            'article'    => $article,
            'version'    => $this->articles->latestVersion((int) $article['id']),
            'sources'    => $this->articles->sources((int) $article['id']),
            'executions' => $this->executions->forArticle((int) $article['id']),
            'totalCost'  => $this->executions->totalCostForArticle((int) $article['id']),
            'notes'      => (new ArticleNoteService())->forArticle((int) $article['id']),
        ]);
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

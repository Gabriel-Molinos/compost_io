<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AiExecutionService;
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

        $goalId = ($_POST['goal_id'] ?? '') !== '' ? (int) $_POST['goal_id'] : null;
        $categoryId = ($_POST['category_id'] ?? '') !== '' ? (int) $_POST['category_id'] : null;

        // O pipeline síncrono faz 3 chamadas ao gemini-2.5-pro — pode passar de 1 min.
        set_time_limit(600);
        @ini_set('max_execution_time', '600');

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

        $msg = 'Rascunho gerado: "' . $result['title'] . '" · ' . $result['word_count']
            . ' palavras · custo ~US$ ' . number_format($result['cost'], 4);
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

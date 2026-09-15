<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ArticleService;
use App\Services\AuthService;
use App\Services\CategoryService;
use App\Services\CostBudgetService;
use App\Services\EditorialRuleService;
use App\Services\GoalService;
use App\Services\ReportService;
use App\Services\SiteLogoLibraryService;
use App\Services\SiteService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\Support\Uploads;
use App\Support\Validator;
use App\View;

final class SiteController extends Controller
{
    private SiteService $sites;
    private CategoryService $categories;
    private EditorialRuleService $rules;
    private GoalService $goals;
    private ReportService $reports;
    private CostBudgetService $costBudget;
    private ArticleService $articles;

    public function __construct()
    {
        $this->sites = new SiteService();
        $this->categories = new CategoryService();
        $this->rules = new EditorialRuleService();
        $this->goals = new GoalService();
        $this->reports = new ReportService();
        $this->costBudget = new CostBudgetService();
        $this->articles = new ArticleService();
    }

    /** Lista de sites — ADMIN vê todos, Redator-Chefe vê os vinculados. */
    public function index(): void
    {
        $user = AuthService::user();
        $sites = ($user !== null && $user['role'] === 'ADMIN')
            ? $this->sites->all()
            : $this->sites->forUser((int) $user['id']);

        View::render('sites/index', [
            'title'   => 'Sites',
            'sites'   => $sites,
            'isAdmin' => AuthService::isAdmin(),
        ]);
    }

    /** Área de trabalho de um site (configuração editorial). */
    public function show(string $id): void
    {
        $site = $this->requireSite($id);

        // currentSpend() (2 queries), não monthly() (10 queries) — a Visão Geral só
        // usa goal_total/ai_cost; o relatório completo fica na aba Relatórios
        // (levantamento de performance, Fase 9).
        $currentPeriod = date('Y-m');
        $spend = $this->reports->currentSpend((int) $site['id'], $currentPeriod);

        View::render('sites/show', [
            'title'       => $site['name'],
            'site'        => $site,
            'categories'  => $this->categories->allForSite((int) $site['id']),
            'ruleCounts'  => $this->rules->countsForSite((int) $site['id']),
            'goalCount'   => $this->goals->countForSite((int) $site['id']),
            'canEditSite' => AuthService::isAdmin(),
            'costBudget'  => $this->costBudget->evaluate($spend),
            'attention'   => $this->articles->attentionCounts((int) $site['id']),
            'staleCount'  => $this->articles->staleGeneratingCount((int) $site['id']),
            // Só produzidos (mesma agregação leve do relatório, §97 performance) — dá
            // uma sensação de "painel de controle" na Visão Geral sem duplicar a
            // aba Relatórios (que segue sendo o lugar da análise completa).
            'trend'       => $this->reports->trend((int) $site['id'], $currentPeriod, 6),
        ]);
    }

    // --- CRUD da estrutura do site (somente ADMIN, via guard de rota) ---

    public function create(): void
    {
        View::render('sites/form', [
            'title'       => 'Novo site',
            'site'        => ['language' => 'pt-BR', 'is_active' => 1],
            'action'      => '/sites',
            'errors'      => [],
            'logoLibrary' => (new SiteLogoLibraryService())->all(),
        ]);
    }

    public function store(): void
    {
        Csrf::verify();

        $errors = $this->validate($_POST);
        if ($errors !== []) {
            http_response_code(422);
            View::render('sites/form', [
                'title' => 'Novo site', 'site' => $_POST, 'action' => '/sites', 'errors' => $errors,
                'logoLibrary' => (new SiteLogoLibraryService())->all(),
            ]);
            return;
        }

        $newId = $this->sites->create($_POST);

        try {
            $logo = $this->resolveLogo($newId, $_FILES['logo'] ?? null, (string) ($_POST['library_logo'] ?? ''), (string) ($_POST['wordpress_url'] ?? ''), allowAutoMatch: true);
            if ($logo !== null) {
                $this->sites->setLogo($newId, $logo);
            }
        } catch (\RuntimeException $e) {
            Session::flash('error', 'Site criado, mas o logo não foi salvo: ' . $e->getMessage());
            Http::redirect('/sites/' . $newId . '/edit');
            return;
        }

        Session::flash('success', 'Site criado.');
        Http::redirect('/sites/' . $newId . '/edit');
    }

    public function edit(string $id): void
    {
        $site = $this->sites->find((int) $id) ?? $this->notFound();

        View::render('sites/form', [
            'title'       => 'Editar site',
            'site'        => $site,
            'action'      => '/sites/' . $site['id'],
            'errors'      => [],
            'logoLibrary' => (new SiteLogoLibraryService())->all(),
        ]);
    }

    public function update(string $id): void
    {
        Csrf::verify();
        $site = $this->sites->find((int) $id) ?? $this->notFound();

        $errors = $this->validate($_POST);
        if ($errors !== []) {
            http_response_code(422);
            View::render('sites/form', [
                'title'  => 'Editar site',
                'site'   => $_POST + ['id' => $site['id']],
                'action' => '/sites/' . $site['id'],
                'errors' => $errors,
                'logoLibrary' => (new SiteLogoLibraryService())->all(),
            ]);
            return;
        }

        $this->sites->update((int) $site['id'], $_POST);

        if (!empty($_POST['remove_logo'])) {
            Uploads::delete($site['logo_path'] ?? null);
            $this->sites->setLogo((int) $site['id'], null);
        } else {
            try {
                $logo = $this->resolveLogo(
                    (int) $site['id'],
                    $_FILES['logo'] ?? null,
                    (string) ($_POST['library_logo'] ?? ''),
                    (string) ($_POST['wordpress_url'] ?? ''),
                    // Upload manual ou escolha explícita na biblioteca sempre
                    // vale; o casamento AUTOMÁTICO por domínio só entra se o
                    // site ainda não tem logo nenhuma — nunca troca uma logo
                    // já definida por trás do admin sem ele pedir.
                    allowAutoMatch: empty($site['logo_path']),
                );
                if ($logo !== null) {
                    $this->sites->setLogo((int) $site['id'], $logo);
                }
            } catch (\RuntimeException $e) {
                Session::flash('error', 'Site salvo, mas o logo não foi atualizado: ' . $e->getMessage());
                Http::redirect('/sites/' . $site['id'] . '/edit');
                return;
            }
        }

        Session::flash('success', 'Site atualizado.');
        Http::redirect('/sites/' . $site['id'] . '/edit');
    }

    /**
     * Exclusão de verdade — apaga o site e TUDO ligado a ele (artigos,
     * categorias, metas, custo de IA, conexão WordPress...) via
     * `ON DELETE CASCADE` (ver `SiteService::delete()`). Irreversível, por
     * isso exige o admin digitar o nome exato do site no formulário (dupla
     * checagem: o botão só habilita com o nome certo no JS, e aqui de novo
     * no servidor — nunca confia só no que o JS deixou passar).
     */
    public function destroy(string $id): void
    {
        Csrf::verify();
        $site = $this->sites->find((int) $id) ?? $this->notFound();

        $typed = trim((string) ($_POST['confirm_name'] ?? ''));
        if ($typed !== $site['name']) {
            Session::flash('error', 'Nome digitado não bateu com o nome do site — nada foi excluído.');
            Http::redirect('/sites/' . $site['id'] . '/edit');
            return;
        }

        Uploads::delete($site['logo_path'] ?? null);
        $this->sites->delete((int) $site['id']);

        Session::flash('success', 'Site "' . $site['name'] . '" excluído, com todo o conteúdo ligado a ele.');
        Http::redirect('/sites');
    }

    /**
     * Decide de onde vem a logo do site, nesta ordem — a primeira que
     * existir vence: (1) upload manual, (2) escolha explícita no seletor
     * visual da biblioteca (`sites/form.php`, achado real 2026-09-15: sem
     * seletor visível, o redator não sabia que a biblioteca existia), (3)
     * casamento automático pelo domínio, só quando `$allowAutoMatch` (nunca
     * substitui uma logo já definida sem o admin pedir explicitamente).
     * Sem nenhuma das três, devolve null silenciosamente.
     *
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $uploadedFile
     */
    private function resolveLogo(int $siteId, ?array $uploadedFile, string $libraryFilename, string $wordpressUrl, bool $allowAutoMatch): ?string
    {
        $logo = Uploads::image($uploadedFile, 'logos', $siteId);
        if ($logo !== null) {
            return $logo;
        }

        $library = new SiteLogoLibraryService();

        $chosen = $library->findByFilename($libraryFilename);
        if ($chosen !== null) {
            return Uploads::fromLocalFile($chosen, 'logos', $siteId);
        }

        if (!$allowAutoMatch) {
            return null;
        }

        $matched = $library->findForDomain($wordpressUrl);

        return $matched !== null ? Uploads::fromLocalFile($matched, 'logos', $siteId) : null;
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function validate(array $data): array
    {
        return (new Validator($data, [
            'name'          => ['required', 'max:191'],
            'language'      => ['required', 'max:20'],
            'wordpress_url' => ['max:255'],
            'niche'         => ['max:191'],
            'tone'          => ['max:100'],
            'editorial_identity' => ['max:5000'],
        ], [
            'name' => 'Nome', 'language' => 'Idioma', 'wordpress_url' => 'URL do WordPress',
            'niche' => 'Nicho', 'tone' => 'Tom', 'editorial_identity' => 'Identidade editorial',
        ]))->errors();
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\CategoryService;
use App\Services\EditorialRuleService;
use App\Services\GoalService;
use App\Services\SiteService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\Support\Validator;
use App\View;

final class SiteController extends Controller
{
    private SiteService $sites;
    private CategoryService $categories;
    private EditorialRuleService $rules;
    private GoalService $goals;

    public function __construct()
    {
        $this->sites = new SiteService();
        $this->categories = new CategoryService();
        $this->rules = new EditorialRuleService();
        $this->goals = new GoalService();
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

        View::render('sites/show', [
            'title'       => $site['name'],
            'site'        => $site,
            'categories'  => $this->categories->allForSite((int) $site['id']),
            'ruleCounts'  => $this->rules->countsForSite((int) $site['id']),
            'goalCount'   => $this->goals->countForSite((int) $site['id']),
            'canEditSite' => AuthService::isAdmin(),
        ]);
    }

    // --- CRUD da estrutura do site (somente ADMIN, via guard de rota) ---

    public function create(): void
    {
        View::render('sites/form', [
            'title'  => 'Novo site',
            'site'   => ['language' => 'pt-BR', 'is_active' => 1],
            'action' => '/sites',
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        Csrf::verify();

        $errors = $this->validate($_POST);
        if ($errors !== []) {
            http_response_code(422);
            View::render('sites/form', ['title' => 'Novo site', 'site' => $_POST, 'action' => '/sites', 'errors' => $errors]);
            return;
        }

        $newId = $this->sites->create($_POST);
        Session::flash('success', 'Site criado.');
        Http::redirect('/sites/' . $newId . '/edit');
    }

    public function edit(string $id): void
    {
        $site = $this->sites->find((int) $id) ?? $this->notFound();

        View::render('sites/form', [
            'title'  => 'Editar site',
            'site'   => $site,
            'action' => '/sites/' . $site['id'],
            'errors' => [],
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
            ]);
            return;
        }

        $this->sites->update((int) $site['id'], $_POST);
        Session::flash('success', 'Site atualizado.');
        Http::redirect('/sites/' . $site['id'] . '/edit');
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
        ], [
            'name' => 'Nome', 'language' => 'Idioma', 'wordpress_url' => 'URL do WordPress',
            'niche' => 'Nicho', 'tone' => 'Tom',
        ]))->errors();
    }
}

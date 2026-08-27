<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SiteService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\Support\Validator;
use App\View;

final class SiteController
{
    private SiteService $sites;

    public function __construct()
    {
        $this->sites = new SiteService();
    }

    public function index(): void
    {
        View::render('sites/index', [
            'title' => 'Sites',
            'sites' => $this->sites->all(),
        ]);
    }

    public function create(): void
    {
        $this->form('sites/form', [
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
            $this->form('sites/form', [
                'title'  => 'Novo site',
                'site'   => $_POST,
                'action' => '/sites',
                'errors' => $errors,
            ]);
            return;
        }

        $id = $this->sites->create($_POST);
        Session::flash('success', 'Site criado.');
        Http::redirect('/sites/' . $id . '/edit');
    }

    public function edit(string $id): void
    {
        $site = $this->sites->find((int) $id);
        if ($site === null) {
            (new ErrorController())->show(404);
            return;
        }

        $this->form('sites/form', [
            'title'  => 'Editar site',
            'site'   => $site,
            'action' => '/sites/' . $site['id'],
            'errors' => [],
        ]);
    }

    public function update(string $id): void
    {
        Csrf::verify();

        $site = $this->sites->find((int) $id);
        if ($site === null) {
            (new ErrorController())->show(404);
            return;
        }

        $errors = $this->validate($_POST);
        if ($errors !== []) {
            http_response_code(422);
            $this->form('sites/form', [
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
        $v = new Validator($data, [
            'name'          => ['required', 'max:191'],
            'language'      => ['required', 'max:20'],
            'wordpress_url' => ['max:255'],
            'niche'         => ['max:191'],
            'tone'          => ['max:100'],
        ], [
            'name'          => 'Nome',
            'language'      => 'Idioma',
            'wordpress_url' => 'URL do WordPress',
            'niche'         => 'Nicho',
            'tone'          => 'Tom',
        ]);

        return $v->errors();
    }

    /** @param array<string, mixed> $data */
    private function form(string $view, array $data): void
    {
        View::render($view, $data);
    }
}

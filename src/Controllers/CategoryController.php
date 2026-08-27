<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CategoryService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\Support\Validator;
use App\View;

final class CategoryController extends Controller
{
    private CategoryService $categories;

    public function __construct()
    {
        $this->categories = new CategoryService();
    }

    public function index(string $siteId): void
    {
        $site = $this->requireSite($siteId);

        View::render('sites/categories/index', [
            'title'      => 'Categorias · ' . $site['name'],
            'site'       => $site,
            'categories' => $this->categories->allForSite((int) $site['id']),
        ]);
    }

    public function create(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        $this->form($site, ['name' => '', 'guidelines' => ''], '/sites/' . $site['id'] . '/categories', []);
    }

    public function store(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        $errors = $this->validate($site, $_POST);
        if ($errors !== []) {
            http_response_code(422);
            $this->form($site, $_POST, '/sites/' . $site['id'] . '/categories', $errors);
            return;
        }

        $this->categories->create(
            (int) $site['id'],
            trim((string) $_POST['name']),
            self::nullable($_POST['guidelines'] ?? null),
        );
        Session::flash('success', 'Categoria criada.');
        Http::redirect('/sites/' . $site['id'] . '/categories');
    }

    public function edit(string $siteId, string $categoryId): void
    {
        $site = $this->requireSite($siteId);
        $category = $this->categories->find((int) $site['id'], (int) $categoryId) ?? $this->notFound();

        $this->form($site, $category, '/sites/' . $site['id'] . '/categories/' . $category['id'], []);
    }

    public function update(string $siteId, string $categoryId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $category = $this->categories->find((int) $site['id'], (int) $categoryId) ?? $this->notFound();

        $errors = $this->validate($site, $_POST, (int) $category['id']);
        if ($errors !== []) {
            http_response_code(422);
            $this->form($site, $_POST + ['id' => $category['id']], '/sites/' . $site['id'] . '/categories/' . $category['id'], $errors);
            return;
        }

        $this->categories->update(
            (int) $category['id'],
            trim((string) $_POST['name']),
            self::nullable($_POST['guidelines'] ?? null),
        );
        Session::flash('success', 'Categoria atualizada.');
        Http::redirect('/sites/' . $site['id'] . '/categories');
    }

    public function destroy(string $siteId, string $categoryId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $category = $this->categories->find((int) $site['id'], (int) $categoryId) ?? $this->notFound();

        $this->categories->delete((int) $category['id']);
        Session::flash('success', 'Categoria removida.');
        Http::redirect('/sites/' . $site['id'] . '/categories');
    }

    /** @param array<string,mixed> $site @param array<string,mixed> $data @return array<string,string> */
    private function validate(array $site, array $data, ?int $ignoreId = null): array
    {
        $errors = (new Validator($data, [
            'name'       => ['required', 'max:191'],
            'guidelines' => ['max:2000'],
        ], ['name' => 'Nome', 'guidelines' => 'Diretrizes']))->errors();

        if ($errors === [] && $this->categories->nameExists((int) $site['id'], trim((string) $data['name']), $ignoreId)) {
            $errors['name'] = 'Já existe uma categoria com este nome neste site.';
        }

        return $errors;
    }

    /** @param array<string,mixed> $site @param array<string,mixed> $data @param array<string,string> $errors */
    private function form(array $site, array $data, string $action, array $errors): void
    {
        View::render('sites/categories/form', [
            'title'    => (!empty($data['id']) ? 'Editar categoria · ' : 'Nova categoria · ') . $site['name'],
            'site'     => $site,
            'category' => $data,
            'action'   => $action,
            'errors'   => $errors,
        ]);
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

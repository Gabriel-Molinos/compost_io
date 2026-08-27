<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CategoryService;
use App\Services\GoalService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\Support\Validator;
use App\View;

final class GoalController extends Controller
{
    private GoalService $goals;
    private CategoryService $categories;

    public function __construct()
    {
        $this->goals = new GoalService();
        $this->categories = new CategoryService();
    }

    public function index(string $siteId): void
    {
        $site = $this->requireSite($siteId);

        View::render('sites/goals/index', [
            'title' => 'Metas · ' . $site['name'],
            'site'  => $site,
            'goals' => $this->goals->allForSite((int) $site['id']),
        ]);
    }

    public function create(string $siteId): void
    {
        $site = $this->requireSite($siteId);

        $this->form($site, [
            'period'             => date('Y-m'),
            'total_articles'     => 0,
            'general_guidelines' => '',
        ], [], '/sites/' . $site['id'] . '/goals', []);
    }

    public function store(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        $categories = $this->categories->allForSite((int) $site['id']);
        $targets = $this->parseTargets($categories, $_POST['targets'] ?? []);
        $errors = $this->validate($site, $_POST, $targets, $categories);

        if ($errors !== []) {
            http_response_code(422);
            $this->form($site, $_POST, $targets, '/sites/' . $site['id'] . '/goals', $errors);
            return;
        }

        $this->goals->create(
            (int) $site['id'],
            trim((string) $_POST['period']),
            (int) $_POST['total_articles'],
            self::nullable($_POST['general_guidelines'] ?? null),
            $targets,
        );
        Session::flash('success', 'Meta criada.');
        Http::redirect('/sites/' . $site['id'] . '/goals');
    }

    public function edit(string $siteId, string $goalId): void
    {
        $site = $this->requireSite($siteId);
        $goal = $this->goals->find((int) $site['id'], (int) $goalId) ?? $this->notFound();

        $this->form(
            $site,
            $goal,
            $this->goals->categoryTargets((int) $goal['id']),
            '/sites/' . $site['id'] . '/goals/' . $goal['id'],
            [],
        );
    }

    public function update(string $siteId, string $goalId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $goal = $this->goals->find((int) $site['id'], (int) $goalId) ?? $this->notFound();

        $categories = $this->categories->allForSite((int) $site['id']);
        $targets = $this->parseTargets($categories, $_POST['targets'] ?? []);
        $errors = $this->validate($site, $_POST, $targets, $categories, (int) $goal['id']);

        if ($errors !== []) {
            http_response_code(422);
            $this->form($site, $_POST + ['id' => $goal['id']], $targets, '/sites/' . $site['id'] . '/goals/' . $goal['id'], $errors);
            return;
        }

        $this->goals->update(
            (int) $goal['id'],
            trim((string) $_POST['period']),
            (int) $_POST['total_articles'],
            self::nullable($_POST['general_guidelines'] ?? null),
            $targets,
        );
        Session::flash('success', 'Meta atualizada.');
        Http::redirect('/sites/' . $site['id'] . '/goals');
    }

    public function destroy(string $siteId, string $goalId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $goal = $this->goals->find((int) $site['id'], (int) $goalId) ?? $this->notFound();

        $this->goals->delete((int) $goal['id']);
        Session::flash('success', 'Meta removida.');
        Http::redirect('/sites/' . $site['id'] . '/goals');
    }

    /**
     * Mantém só os alvos > 0 de categorias que pertencem ao site.
     *
     * @param list<array<string, mixed>> $categories
     * @param mixed                      $raw        targets[category_id] => valor
     * @return array<int, int>
     */
    private function parseTargets(array $categories, mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $allowed = array_map(static fn (array $c): int => (int) $c['id'], $categories);
        $targets = [];
        foreach ($raw as $categoryId => $value) {
            $categoryId = (int) $categoryId;
            $count = (int) $value;
            if ($count > 0 && in_array($categoryId, $allowed, true)) {
                $targets[$categoryId] = $count;
            }
        }

        return $targets;
    }

    /**
     * @param array<string, mixed>       $site
     * @param array<string, mixed>       $data
     * @param array<int, int>            $targets
     * @param list<array<string, mixed>> $categories
     * @return array<string, string>
     */
    private function validate(array $site, array $data, array $targets, array $categories, ?int $ignoreId = null): array
    {
        $errors = (new Validator($data, [
            'period'             => ['required', 'max:20'],
            'total_articles'     => ['required'],
            'general_guidelines' => ['max:5000'],
        ], [
            'period'             => 'Período',
            'total_articles'     => 'Total de artigos',
            'general_guidelines' => 'Diretrizes gerais',
        ]))->errors();

        $period = trim((string) ($data['period'] ?? ''));
        if (!isset($errors['period']) && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $errors['period'] = 'Use o formato AAAA-MM (ex.: 2026-09).';
        }

        if (!isset($errors['period']) && $this->goals->periodExists((int) $site['id'], $period, $ignoreId)) {
            $errors['period'] = 'Já existe uma meta para este período neste site.';
        }

        $total = (int) ($data['total_articles'] ?? 0);
        if (!isset($errors['total_articles']) && $total < 1) {
            $errors['total_articles'] = 'Informe um número maior que zero.';
        }

        $allocated = array_sum($targets);
        if ($categories !== [] && !isset($errors['total_articles']) && $allocated > $total) {
            $errors['targets'] = "A soma por categoria ({$allocated}) passa do total de artigos ({$total}).";
        }

        return $errors;
    }

    /**
     * @param array<string, mixed>       $site
     * @param array<string, mixed>       $data
     * @param array<int, int>            $targets
     * @param array<string, string>      $errors
     */
    private function form(array $site, array $data, array $targets, string $action, array $errors): void
    {
        View::render('sites/goals/form', [
            'title'      => (!empty($data['id']) ? 'Editar meta · ' : 'Nova meta · ') . $site['name'],
            'site'       => $site,
            'goal'       => $data,
            'targets'    => $targets,
            'categories' => $this->categories->allForSite((int) $site['id']),
            'action'     => $action,
            'errors'     => $errors,
        ]);
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

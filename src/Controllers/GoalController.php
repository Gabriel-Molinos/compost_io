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
use PDOException;

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
        $categories = $this->categories->allForSite((int) $site['id']);

        $this->form($site, [
            'period'             => date('Y-m'),
            'total_articles'     => 0,
            'general_guidelines' => '',
        ], [], $categories, '/sites/' . $site['id'] . '/goals', []);
    }

    public function store(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        $categories = $this->categories->allForSite((int) $site['id']);
        $targets = $this->parseTargets($categories, $_POST['targets'] ?? []);
        $action = '/sites/' . $site['id'] . '/goals';

        $errors = $this->validate($site, $_POST, $targets, $categories);
        if ($errors !== []) {
            http_response_code(422);
            $this->form($site, $_POST, $targets, $categories, $action, $errors);
            return;
        }

        try {
            $this->goals->create(
                (int) $site['id'],
                trim((string) $_POST['period']),
                (int) $_POST['total_articles'],
                self::nullable($_POST['general_guidelines'] ?? null),
                $targets,
            );
        } catch (PDOException $e) {
            if (!self::isDuplicate($e)) {
                throw $e;
            }
            http_response_code(422);
            $this->form($site, $_POST, $targets, $categories, $action, ['period' => 'Já existe uma meta para este período neste site.']);
            return;
        }
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
            $this->categories->allForSite((int) $site['id']),
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
        $action = '/sites/' . $site['id'] . '/goals/' . $goal['id'];

        $errors = $this->validate($site, $_POST, $targets, $categories, (int) $goal['id']);
        if ($errors !== []) {
            http_response_code(422);
            $this->form($site, $_POST + ['id' => $goal['id']], $targets, $categories, $action, $errors);
            return;
        }

        try {
            $this->goals->update(
                (int) $goal['id'],
                trim((string) $_POST['period']),
                (int) $_POST['total_articles'],
                self::nullable($_POST['general_guidelines'] ?? null),
                $targets,
            );
        } catch (PDOException $e) {
            if (!self::isDuplicate($e)) {
                throw $e;
            }
            http_response_code(422);
            $this->form($site, $_POST + ['id' => $goal['id']], $targets, $categories, $action, ['period' => 'Já existe uma meta para este período neste site.']);
            return;
        }
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
     * @param list<array<string, mixed>> $categories
     * @param array<string, string>      $errors
     */
    private function form(array $site, array $data, array $targets, array $categories, string $action, array $errors): void
    {
        View::render('sites/goals/form', [
            'title'      => (!empty($data['id']) ? 'Editar meta · ' : 'Nova meta · ') . $site['name'],
            'site'       => $site,
            'goal'       => $data,
            'targets'    => $targets,
            'categories' => $categories,
            'action'     => $action,
            'errors'     => $errors,
        ]);
    }

    /** Violação de UNIQUE (SQLSTATE 23000 / errno 1062). */
    private static function isDuplicate(PDOException $e): bool
    {
        return $e->getCode() === '23000' && (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

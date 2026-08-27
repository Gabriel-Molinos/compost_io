<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\EditorialRuleService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\Support\Validator;
use App\View;

final class EditorialRuleController extends Controller
{
    private EditorialRuleService $rules;

    public function __construct()
    {
        $this->rules = new EditorialRuleService();
    }

    public function index(string $siteId): void
    {
        $site = $this->requireSite($siteId);

        View::render('sites/rules/index', [
            'title'   => 'Interesses · ' . $site['name'],
            'site'    => $site,
            'grouped' => $this->rules->groupedForSite((int) $site['id']),
        ]);
    }

    public function create(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        $type = ($_GET['type'] ?? '') === 'NON_INTEREST' ? 'NON_INTEREST' : 'INTEREST';

        $this->form($site, ['type' => $type, 'description' => '', 'intensity' => 3],
            '/sites/' . $site['id'] . '/rules', []);
    }

    public function store(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        $errors = $this->validate($_POST);
        if ($errors !== []) {
            http_response_code(422);
            $this->form($site, $_POST, '/sites/' . $site['id'] . '/rules', $errors);
            return;
        }

        $this->rules->create(
            (int) $site['id'],
            $_POST['type'],
            trim((string) $_POST['description']),
            (int) $_POST['intensity'],
        );
        Session::flash('success', 'Regra adicionada.');
        Http::redirect('/sites/' . $site['id'] . '/rules');
    }

    public function edit(string $siteId, string $ruleId): void
    {
        $site = $this->requireSite($siteId);
        $rule = $this->rules->find((int) $site['id'], (int) $ruleId) ?? $this->notFound();

        $this->form($site, $rule, '/sites/' . $site['id'] . '/rules/' . $rule['id'], []);
    }

    public function update(string $siteId, string $ruleId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $rule = $this->rules->find((int) $site['id'], (int) $ruleId) ?? $this->notFound();

        $errors = $this->validate($_POST);
        if ($errors !== []) {
            http_response_code(422);
            $this->form($site, $_POST + ['id' => $rule['id']], '/sites/' . $site['id'] . '/rules/' . $rule['id'], $errors);
            return;
        }

        $this->rules->update(
            (int) $rule['id'],
            $_POST['type'],
            trim((string) $_POST['description']),
            (int) $_POST['intensity'],
        );
        Session::flash('success', 'Regra atualizada.');
        Http::redirect('/sites/' . $site['id'] . '/rules');
    }

    public function destroy(string $siteId, string $ruleId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $rule = $this->rules->find((int) $site['id'], (int) $ruleId) ?? $this->notFound();

        $this->rules->delete((int) $rule['id']);
        Session::flash('success', 'Regra removida.');
        Http::redirect('/sites/' . $site['id'] . '/rules');
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    private function validate(array $data): array
    {
        $errors = (new Validator($data, [
            'type'        => ['required', 'in:INTEREST,NON_INTEREST'],
            'description' => ['required', 'max:255'],
            'intensity'   => ['required', 'in:1,2,3,4,5'],
        ], [
            'type' => 'Tipo', 'description' => 'Descrição', 'intensity' => 'Intensidade',
        ]))->errors();

        return $errors;
    }

    /** @param array<string,mixed> $site @param array<string,mixed> $data @param array<string,string> $errors */
    private function form(array $site, array $data, string $action, array $errors): void
    {
        View::render('sites/rules/form', [
            'title'  => (!empty($data['id']) ? 'Editar regra · ' : 'Nova regra · ') . $site['name'],
            'site'   => $site,
            'rule'   => $data,
            'action' => $action,
            'errors' => $errors,
        ]);
    }
}

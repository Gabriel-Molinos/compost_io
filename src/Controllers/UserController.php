<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\SiteService;
use App\Services\UserService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\Support\Uploads;
use App\Support\Validator;
use App\View;

final class UserController
{
    private UserService $users;
    private SiteService $sites;
    private NotificationService $notifications;

    public function __construct()
    {
        $this->users = new UserService();
        $this->sites = new SiteService();
        $this->notifications = new NotificationService();
    }

    public function index(): void
    {
        View::render('users/index', [
            'title' => 'Usuários',
            'users' => $this->users->all(),
        ]);
    }

    public function create(): void
    {
        View::render('users/form', [
            'title'        => 'Novo usuário',
            'user'         => ['role' => 'REDATOR_CHEFE', 'is_active' => 1],
            'action'       => '/users',
            'errors'       => [],
            'sites'        => $this->sites->all(),
            'assignedIds'  => [],
            'requirePass'  => true,
        ]);
    }

    public function store(): void
    {
        Csrf::verify();

        $errors = $this->validate($_POST, requirePass: true);
        if ($errors === [] && $this->users->emailExists(trim((string) $_POST['email']))) {
            $errors['email'] = 'Já existe um usuário com este e-mail.';
        }

        if ($errors !== []) {
            $this->renderForm('/users', $_POST, $errors, true);
            return;
        }

        $id = $this->users->create($_POST, (string) $_POST['password']);
        $siteIds = $this->siteIds($_POST);
        $this->users->syncSites($id, $siteIds);
        $this->notifyNewAssignments($id, [], $siteIds);

        try {
            $avatar = Uploads::image($_FILES['avatar'] ?? null, 'avatars', $id);
            if ($avatar !== null) {
                $this->users->setAvatar($id, $avatar);
            }
        } catch (\RuntimeException $e) {
            // Usuário já foi criado — não desfaz o cadastro por causa da foto,
            // só avisa e deixa ele reenviar depois em "Editar".
            Session::flash('error', 'Usuário criado, mas a foto não foi salva: ' . $e->getMessage());
            Http::redirect('/users/' . $id . '/edit');
            return;
        }

        Session::flash('success', 'Usuário criado.');
        Http::redirect('/users/' . $id . '/edit');
    }

    public function edit(string $id): void
    {
        $user = $this->users->find((int) $id);
        if ($user === null) {
            (new ErrorController())->show(404);
            return;
        }

        View::render('users/form', [
            'title'        => 'Editar usuário',
            'user'         => $user,
            'action'       => '/users/' . $user['id'],
            'errors'       => [],
            'sites'        => $this->sites->all(),
            'assignedIds'  => $this->users->siteIdsFor((int) $user['id']),
            'requirePass'  => false,
        ]);
    }

    public function update(string $id): void
    {
        Csrf::verify();

        $user = $this->users->find((int) $id);
        if ($user === null) {
            (new ErrorController())->show(404);
            return;
        }

        $errors = $this->validate($_POST, requirePass: false);
        if ($errors === [] && $this->users->emailExists(trim((string) $_POST['email']), (int) $user['id'])) {
            $errors['email'] = 'Já existe um usuário com este e-mail.';
        }

        // Não deixar o próprio ADMIN se rebaixar / desativar e ficar sem admin.
        if ($errors === [] && (int) $user['id'] === AuthService::id()
            && ($_POST['role'] !== 'ADMIN' || empty($_POST['is_active']))) {
            $errors['role'] = 'Você não pode remover seu próprio acesso de administrador.';
        }

        if ($errors !== []) {
            $this->renderForm('/users/' . $user['id'], $_POST + ['id' => $user['id']], $errors, false);
            return;
        }

        $previousSiteIds = $this->users->siteIdsFor((int) $user['id']);
        $this->users->update((int) $user['id'], $_POST, $_POST['password'] ?? null);
        $siteIds = $this->siteIds($_POST);
        $this->users->syncSites((int) $user['id'], $siteIds);
        $this->notifyNewAssignments((int) $user['id'], $previousSiteIds, $siteIds);

        if (!empty($_POST['remove_avatar'])) {
            Uploads::delete($user['avatar_path'] ?? null);
            $this->users->setAvatar((int) $user['id'], null);
        } else {
            try {
                $avatar = Uploads::image($_FILES['avatar'] ?? null, 'avatars', (int) $user['id']);
                if ($avatar !== null) {
                    $this->users->setAvatar((int) $user['id'], $avatar);
                }
            } catch (\RuntimeException $e) {
                Session::flash('error', 'Usuário salvo, mas a foto não foi atualizada: ' . $e->getMessage());
                Http::redirect('/users/' . $user['id'] . '/edit');
                return;
            }
        }

        Session::flash('success', 'Usuário atualizado.');
        Http::redirect('/users/' . $user['id'] . '/edit');
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function validate(array $data, bool $requirePass): array
    {
        $rules = [
            'name'  => ['required', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'role'  => ['required', 'in:ADMIN,REDATOR_CHEFE'],
        ];
        if ($requirePass) {
            $rules['password'] = ['required', 'min:8'];
        } elseif (trim((string) ($data['password'] ?? '')) !== '') {
            $rules['password'] = ['min:8'];
        }

        return (new Validator($data, $rules, [
            'name' => 'Nome', 'email' => 'E-mail', 'role' => 'Perfil', 'password' => 'Senha',
        ]))->errors();
    }

    /**
     * Avisa o usuário quando um admin vincula ele a um site novo (pedido do
     * responsável, 2026-09-08) — só os IDs que não estavam na lista anterior,
     * pra não notificar de novo um vínculo que já existia.
     *
     * @param list<int> $before
     * @param list<int> $after
     */
    private function notifyNewAssignments(int $userId, array $before, array $after): void
    {
        foreach (array_diff($after, $before) as $siteId) {
            $site = $this->sites->find($siteId);
            if ($site === null) {
                continue;
            }
            $this->notifications->notify(
                $userId,
                NotificationService::TYPE_SITE_ASSIGNED,
                'Você foi vinculado a um site',
                'Um administrador te deu acesso a "' . $site['name'] . '".',
                $siteId,
                '/sites/' . $siteId,
            );
        }
    }

    /** @param array<string, mixed> $data @return list<int> */
    private function siteIds(array $data): array
    {
        if (($data['role'] ?? '') === 'ADMIN') {
            return []; // ADMIN enxerga todos os sites — não precisa de vínculo
        }

        return array_map('intval', (array) ($data['site_ids'] ?? []));
    }

    /** @param array<string, mixed> $data @param array<string, string> $errors */
    private function renderForm(string $action, array $data, array $errors, bool $requirePass): void
    {
        http_response_code(422);
        View::render('users/form', [
            'title'       => str_contains($action, '/users/') && $action !== '/users' ? 'Editar usuário' : 'Novo usuário',
            'user'        => $data,
            'action'      => $action,
            'errors'      => $errors,
            'sites'       => $this->sites->all(),
            'assignedIds' => $this->siteIds($data),
            'requirePass' => $requirePass,
        ]);
    }
}

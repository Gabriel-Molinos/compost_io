<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\UserService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\Support\Uploads;
use App\Support\Validator;
use App\View;

/**
 * "Meu perfil" — qualquer usuário autenticado, independente do papel (ADMIN
 * ou Redator-Chefe), pode trocar a própria foto e o próprio nome. Diferente
 * de UserController (só ADMIN, edita QUALQUER usuário via {id} da URL): aqui
 * o alvo é sempre o usuário da sessão — nunca lê um id de fora, então não
 * tem como um usuário mexer no nome/foto de outro por essa rota.
 *
 * Nome vs. e-mail/senha (pedido do responsável, 2026-09-08): nome é o único
 * dado de identificação que o PRÓPRIO usuário controla — e-mail e senha
 * ficam exclusivamente com o admin, em UserController/users/form.php.
 */
final class ProfileController
{
    private UserService $users;

    public function __construct()
    {
        $this->users = new UserService();
    }

    public function edit(): void
    {
        View::render('profile/edit', [
            'title'      => 'Meu perfil',
            'user'       => AuthService::user(),
            'error'      => null,
            'nameErrors' => [],
        ]);
    }

    public function updateName(): void
    {
        Csrf::verify();

        $user = AuthService::user();
        if ($user === null) {
            Http::redirect('/login');
            return;
        }

        $errors = (new Validator($_POST, ['name' => ['required', 'max:191']], ['name' => 'Nome']))->errors();
        if ($errors !== []) {
            http_response_code(422);
            View::render('profile/edit', [
                'title'      => 'Meu perfil',
                'user'       => ['id' => $user['id'], 'role' => $user['role'], 'avatar_path' => $user['avatar_path'], 'name' => $_POST['name'] ?? ''],
                'error'      => null,
                'nameErrors' => $errors,
            ]);
            return;
        }

        $this->users->updateName((int) $user['id'], (string) $_POST['name']);
        Session::flash('success', 'Nome atualizado.');
        Http::redirect('/profile');
    }

    public function updateAvatar(): void
    {
        Csrf::verify();

        $user = AuthService::user();
        if ($user === null) {
            Http::redirect('/login');
            return;
        }

        $id = (int) $user['id'];

        if (!empty($_POST['remove_avatar'])) {
            Uploads::delete($user['avatar_path'] ?? null);
            $this->users->setAvatar($id, null);
            Session::flash('success', 'Foto removida.');
            Http::redirect('/profile');
            return;
        }

        try {
            $avatar = Uploads::image($_FILES['avatar'] ?? null, 'avatars', $id);
        } catch (\RuntimeException $e) {
            View::render('profile/edit', [
                'title'      => 'Meu perfil',
                'user'       => $user,
                'error'      => $e->getMessage(),
                'nameErrors' => [],
            ]);
            return;
        }

        if ($avatar === null) {
            Session::flash('error', 'Escolha um arquivo antes de enviar.');
            Http::redirect('/profile');
            return;
        }

        $this->users->setAvatar($id, $avatar);
        Session::flash('success', 'Foto atualizada.');
        Http::redirect('/profile');
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\UserService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\Support\Uploads;
use App\View;

/**
 * "Meu perfil" — qualquer usuário autenticado, independente do papel (ADMIN
 * ou Redator-Chefe), pode trocar a própria foto. Diferente de UserController
 * (só ADMIN, edita QUALQUER usuário via {id} da URL): aqui o alvo é sempre
 * o usuário da sessão — nunca lê um id de fora, então não tem como um
 * usuário mexer na foto de outro por essa rota.
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
            'title' => 'Meu perfil',
            'user'  => AuthService::user(),
            'error' => null,
        ]);
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
                'title' => 'Meu perfil',
                'user'  => $user,
                'error' => $e->getMessage(),
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

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\View;

final class AuthController
{
    public function showLogin(): void
    {
        if (AuthService::check()) {
            Http::redirect('/');
        }

        View::render('auth/login', [
            'title'  => 'Entrar',
            'flash'  => Session::pullFlash('status'),
            'email'  => '',
            'error'  => null,
        ], 'layout/auth');
    }

    public function login(): void
    {
        if (AuthService::check()) {
            Http::redirect('/');
        }

        $email = Http::input('email');
        $password = $_POST['password'] ?? '';

        if (!Csrf::check($_POST['_token'] ?? null)) {
            $this->rejectLogin($email, 'Sessão expirada. Tente novamente.');
        }

        if ($email === '' || $password === '') {
            $this->rejectLogin($email, 'Informe e-mail e senha.');
        }

        $user = AuthService::attempt($email, (string) $password);

        if ($user === null) {
            $this->rejectLogin($email, 'E-mail ou senha incorretos.');
        }

        AuthService::login($user);
        Http::redirect('/');
    }

    public function logout(): void
    {
        if (Csrf::check($_POST['_token'] ?? null)) {
            AuthService::logout();
        }

        Http::redirect('/login');
    }

    private function rejectLogin(string $email, string $error): never
    {
        http_response_code(422);
        View::render('auth/login', [
            'title' => 'Entrar',
            'flash' => null,
            'email' => $email,
            'error' => $error,
        ], 'layout/auth');
        exit;
    }
}

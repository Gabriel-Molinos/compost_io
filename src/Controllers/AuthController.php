<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Env;
use App\Services\AuthService;
use App\Support\Csrf;
use App\Support\GoogleCsrf;
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

    /**
     * Recebe o POST do Google Identity Services (modo redirect,
     * `data-login_uri` em src/Views/auth/login.php) com o ID token do
     * usuário. Rota registrada em routes/web.php (POST /oauth/callback) —
     * caminho exigido pela URI de redirecionamento já cadastrada no Google
     * Cloud Console (achado real 2026-09-15). Antes vivia solto em
     * public/callback.php (exceção deliberada ao Router); voltou pro
     * padrão do projeto porque um caminho sem extensão (/oauth/callback)
     * não mapeia pra um arquivo .php de qualquer forma.
     */
    public function googleCallback(): void
    {
        if (($_POST['credential'] ?? null) === null) {
            http_response_code(400);
            exit('Bad request');
        }

        // CSRF do Google (double-submit cookie/body) — ANTES de confiar em
        // qualquer outra coisa do POST. Este POST não é nosso <form>, não
        // carrega o _token de App\Support\Csrf; o par g_csrf_token é a
        // única defesa CSRF disponível aqui, e quem garante que os dois
        // batem é o próprio navegador/GIS.
        if (!GoogleCsrf::tokensMatch($_COOKIE['g_csrf_token'] ?? null, $_POST['g_csrf_token'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }

        $clientId = Env::get('GOOGLE_CLIENT_ID', '');
        if ($clientId === '') {
            Session::flash('status', 'Login com Google não está configurado neste ambiente.');
            Http::redirect('/login');
        }

        $client = new \Google\Client();
        $client->setClientId($clientId);

        // verifyIdToken() nunca lança exceção pra token inválido — devolve
        // false. Checagem explícita, não é opcional.
        $payload = $client->verifyIdToken((string) $_POST['credential']);

        if ($payload === false) {
            Session::flash('status', 'Não foi possível verificar seu login com Google. Tente novamente.');
            Http::redirect('/login');
        }

        // Nunca confiar num e-mail não verificado pelo próprio Google.
        if (($payload['email_verified'] ?? false) !== true) {
            Session::flash('status', 'Seu e-mail Google não está verificado.');
            Http::redirect('/login');
        }

        $user = AuthService::attemptGoogle((string) $payload['sub'], (string) $payload['email']);

        if ($user === null) {
            Session::flash('status', 'Este e-mail não está cadastrado no COMPOST. Peça ao administrador para criar sua conta.');
            Http::redirect('/login');
        }

        // Mesma lógica de sessão do login por senha (Session::regenerate() +
        // chave user_id) — zero duplicação entre os dois fluxos.
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

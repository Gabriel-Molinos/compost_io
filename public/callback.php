<?php

declare(strict_types=1);

use App\Config\Env;
use App\Services\AuthService;
use App\Support\GoogleCsrf;
use App\Support\Http;
use App\Support\Session;

/**
 * Recebe o POST do Google Identity Services (modo redirect,
 * `data-login_uri` em src/Views/auth/login.php) com o ID token do usuário.
 *
 * Fica FORA do Router/AuthController de propósito (exceção deliberada ao
 * front-controller único do resto do projeto — ver plano/decisão registrada
 * em docs/technical/requisitos.md §64.2) — por isso o bootstrap abaixo
 * duplica manualmente o que public/index.php já faz.
 */

// --- Bootstrap (mesmas linhas de public/index.php) ---
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
date_default_timezone_set(Env::get('APP_TIMEZONE') ?: 'America/Sao_Paulo');
error_reporting(E_ALL);
ini_set('display_errors', Env::get('APP_ENV') === 'development' ? '1' : '0');

Session::start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['credential'])) {
    http_response_code(400);
    exit('Bad request');
}

// CSRF do Google (double-submit cookie/body) — ANTES de confiar em qualquer
// outra coisa do POST. Este POST não é nosso <form>, não carrega o _token
// de App\Support\Csrf; o par g_csrf_token é a única defesa CSRF disponível
// aqui, e quem garante que os dois batem é o próprio navegador/GIS.
if (!GoogleCsrf::tokensMatch($_COOKIE['g_csrf_token'] ?? null, $_POST['g_csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

$clientId = Env::get('GOOGLE_CLIENT_ID', '');
if ($clientId === '') {
    Session::flash('status', 'Login com Google não está configurado neste ambiente.');
    Http::redirect('/login');
}

$client = new Google\Client();
$client->setClientId($clientId);

// verifyIdToken() nunca lança exceção pra token inválido — devolve false.
// Checagem explícita, não é opcional.
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

// Mesma lógica de sessão do login por senha (Session::regenerate() + chave
// user_id) — zero duplicação entre os dois fluxos.
AuthService::login($user);

Http::redirect('/');

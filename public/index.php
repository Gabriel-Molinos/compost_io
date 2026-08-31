<?php

declare(strict_types=1);

use App\Config\Env;
use App\Router;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

Env::load($root . '/.env');

date_default_timezone_set(Env::get('APP_TIMEZONE') ?: 'America/Sao_Paulo');

error_reporting(E_ALL);
ini_set('display_errors', Env::get('APP_ENV') === 'development' ? '1' : '0');

// Servir arquivos estáticos diretamente quando rodando com `php -S`.
if (PHP_SAPI === 'cli-server') {
    $file = $root . '/public' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

$router = new Router();
(require $root . '/routes/web.php')($router);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

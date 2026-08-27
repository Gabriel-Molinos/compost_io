<?php

declare(strict_types=1);

/**
 * Cria (ou atualiza) um usuário ADMIN. A senha NUNCA é passada por argumento
 * nem fica em arquivo — só por variável de ambiente.
 *
 *   ADMIN_NAME="Fulano" ADMIN_EMAIL="fulano@ex.com" ADMIN_PASSWORD="..." \
 *     php database/seeds/create_admin.php
 *
 * Se o e-mail já existir, atualiza nome/senha e garante role=ADMIN, is_active=1.
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Config\Env;
use App\Database\Connection;

Env::load(dirname(__DIR__, 2) . '/.env');

$name = getenv('ADMIN_NAME') ?: 'Administrador';
$email = getenv('ADMIN_EMAIL') ?: '';
$password = getenv('ADMIN_PASSWORD') ?: '';

if ($email === '' || $password === '') {
    fwrite(STDERR, "Defina ADMIN_EMAIL e ADMIN_PASSWORD no ambiente.\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Senha muito curta (mínimo 8 caracteres).\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo = Connection::get();
$stmt = $pdo->prepare(
    "INSERT INTO users (name, email, password_hash, role, is_active)
     VALUES (:name, :email, :hash, 'ADMIN', 1)
     ON DUPLICATE KEY UPDATE
        name = VALUES(name), password_hash = VALUES(password_hash),
        role = 'ADMIN', is_active = 1"
);
$stmt->execute(['name' => $name, 'email' => $email, 'hash' => $hash]);

$created = $stmt->rowCount() === 1;
echo ($created ? 'Admin criado' : 'Admin atualizado') . ": {$email}\n";

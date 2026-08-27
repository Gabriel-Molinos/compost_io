<?php

declare(strict_types=1);

/**
 * Runner de migrations — sem ORM, sem framework.
 * Aplica os arquivos .sql de database/migrations/ em ordem alfabética e registra
 * os aplicados na tabela `schema_migrations`.
 *
 *   php database/migrate.php            # aplica as pendentes
 *   php database/migrate.php --status   # só lista o que falta
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Env;
use App\Database\Connection;

Env::load(dirname(__DIR__) . '/.env');

$statusOnly = in_array('--status', $argv, true);
$dir = __DIR__ . '/migrations';

$pdo = Connection::get();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `filename`   VARCHAR(191) NOT NULL,
        `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`filename`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
);

$applied = $pdo->query('SELECT filename FROM `schema_migrations`')->fetchAll(PDO::FETCH_COLUMN);
$files = glob($dir . '/*.sql') ?: [];
sort($files);

$pending = array_values(array_filter($files, fn ($f) => !in_array(basename($f), $applied, true)));

if ($pending === []) {
    echo "Nada pendente. " . count($applied) . " migration(s) já aplicada(s).\n";
    exit(0);
}

echo count($pending) . " migration(s) pendente(s):\n";
foreach ($pending as $f) {
    echo "  - " . basename($f) . "\n";
}

if ($statusOnly) {
    exit(0);
}

$insert = $pdo->prepare('INSERT INTO `schema_migrations` (`filename`) VALUES (?)');

foreach ($pending as $file) {
    $name = basename($file);
    echo "\n>> {$name}\n";

    $sql = preg_replace('/--[^\n]*/', '', file_get_contents($file));
    $statements = array_filter(array_map('trim', explode(';', $sql)), fn ($s) => $s !== '');

    foreach ($statements as $i => $stmt) {
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            fwrite(STDERR, "   FALHOU na sentença " . ($i + 1) . ": " . $e->getMessage() . "\n");
            fwrite(STDERR, "   ABORTADO.\n");
            exit(1);
        }
    }

    $insert->execute([$name]);
    echo "   OK\n";
}

echo "\nConcluído.\n";

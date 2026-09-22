<?php

declare(strict_types=1);

/**
 * Lint do projeto — `php -l` (checagem de sintaxe) em todo arquivo .php,
 * sem depender de `find`/`xargs` de shell (portátil entre o Windows de dev
 * e o runner Linux do CI — mesmo motivo de HttpServerTestCase usar
 * proc_open() em vez de um script de shell). Usado por `composer lint` e
 * pelo workflow de CI (.github/workflows/ci.yml).
 *
 *   php bin/lint.php
 */

$root = dirname(__DIR__);
$dirs = ['src', 'routes', 'database', 'bin', 'public', 'tests'];
$phpBinary = PHP_BINARY;

$checked = 0;
$failed = [];

foreach ($dirs as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) {
        continue;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $checked++;
        $output = [];
        $code = 0;
        exec($phpBinary . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);

        if ($code !== 0) {
            $failed[] = $file->getPathname() . "\n" . implode("\n", $output);
        }
    }
}

echo "Verificados {$checked} arquivo(s) PHP.\n";

if ($failed !== []) {
    echo "\n" . count($failed) . " arquivo(s) com erro de sintaxe:\n\n";
    foreach ($failed as $f) {
        echo $f . "\n\n";
    }
    exit(1);
}

echo "Nenhum erro de sintaxe.\n";
exit(0);

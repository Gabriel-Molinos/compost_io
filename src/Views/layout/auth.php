<?php

declare(strict_types=1);

use App\View;

/** @var string $content */
/** @var string $title */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title><?= View::e($title) ?> · COMPOST</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">

    <style>
        :focus-visible { outline: 2px solid #0AFFEF; outline-offset: 2px; border-radius: 2px; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body class="flex min-h-screen items-center justify-center bg-base px-6 py-12 font-sans text-text-primary antialiased">
    <main class="w-full max-w-sm">
        <div class="mb-8 flex items-center gap-3">
            <span aria-hidden="true"
                  class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-cyan/10 text-cyan">◆</span>
            <div>
                <p class="text-sm font-semibold tracking-[0.2em] text-cyan">COMPOST</p>
                <p class="text-xs text-text-muted">Dashboard Inteligente</p>
            </div>
        </div>

        <?= $content ?>
    </main>
</body>
</html>

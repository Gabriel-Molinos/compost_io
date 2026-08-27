<?php

declare(strict_types=1);

use App\View;

/** @var string $content  Conteúdo já renderizado pela View. */
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
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/assets/css/app.css">

    <style>
        /* Foco visível global — não remover sem substituto (ui-ux-frontend.md §88.4). */
        :focus-visible { outline: 2px solid #0AFFEF; outline-offset: 2px; border-radius: 2px; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
        }
        .skip-link {
            position: absolute; left: 0; top: 0; transform: translateY(-120%);
            background: #0AFFEF; color: #050B0F; padding: .5rem 1rem; font-weight: 600;
            transition: transform .15s ease;
        }
        .skip-link:focus { transform: translateY(0); }
    </style>
</head>
<body class="min-h-screen bg-base text-text-primary font-sans antialiased">
    <a href="#conteudo" class="skip-link">Pular para o conteúdo</a>

    <div class="mx-auto flex min-h-screen max-w-3xl flex-col px-6 py-10">
        <header class="mb-10 flex items-center gap-3">
            <span aria-hidden="true"
                  class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-cyan/10 text-cyan">◆</span>
            <div>
                <p class="text-sm font-semibold tracking-[0.2em] text-cyan">COMPOST</p>
                <p class="text-xs text-text-muted">Dashboard Inteligente</p>
            </div>
        </header>

        <main id="conteudo" class="flex-1">
            <?= $content ?>
        </main>

        <footer class="mt-12 border-t border-border pt-6 text-xs text-text-muted">
            Fase 1 — Fundação · <?= View::e(date('Y')) ?>
        </footer>
    </div>
</body>
</html>

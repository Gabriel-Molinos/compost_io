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

    <link rel="icon" type="image/png" sizes="32x32" href="/assets/brand/favicon-32.png">
    <link rel="apple-touch-icon" href="/assets/brand/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Orbitron:wght@500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= View::e(View::asset('assets/css/app.css')) ?>">
    <script src="<?= View::e(View::asset('assets/js/footer-clock.js')) ?>" defer></script>

    <style>
        :focus-visible { outline: 2px solid #7FE8FF; outline-offset: 2px; border-radius: 2px; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body class="app-bg flex min-h-screen flex-col items-center justify-center px-6 py-12 font-sans text-text-primary antialiased">
    <main id="conteudo" class="relative z-10 w-full max-w-sm">
        <img src="/assets/brand/logo-lockup.webp" alt="COMPOST — Editorial Dashboard"
             class="mx-auto mb-8 h-auto w-56 drop-shadow-[0_0_30px_rgba(0,208,240,0.25)]">

        <div class="rounded-lg border border-border bg-surface p-7 shadow-2xl shadow-black/40 sm:p-8">
            <?= $content ?>
        </div>
    </main>
    <footer class="relative z-10 mt-8 flex w-full max-w-sm items-center justify-between border-t border-border/60 pt-4 text-[11px] uppercase tracking-wide text-text-muted">
        <span class="font-mono tabular-nums text-text-secondary" data-clock>00:00:00</span>
        <span class="flex items-center gap-1.5 text-text-muted">
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-cyan shadow-[0_0_6px_rgba(0,208,240,.8)]"></span>
            COMPOST
        </span>
        <span class="font-mono text-text-secondary"><?= View::e(View::todayShort()) ?></span>
    </footer>
</body>
</html>

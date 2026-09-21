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
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="<?= View::e(View::asset('assets/js/btn-fx.js')) ?>" defer></script>
    <script src="<?= View::e(View::asset('assets/js/veil.js')) ?>" defer></script>
    <?php require __DIR__ . '/_veil_head.php'; ?>

    <style>
        /* Sem border-radius aqui: "outline-radius" não existe em CSS — essa
           propriedade cai no ELEMENTO em foco, não no contorno, sobrescrevendo
           qualquer rounded-* da própria View (achado real: os inputs em pill
           do /login viravam quadrados ao focar). */
        :focus-visible { outline: 2px solid #7FE8FF; outline-offset: 2px; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body class="app-bg relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-6 py-12 font-sans text-text-primary antialiased">
    <div class="page-veil" aria-hidden="true"></div>

    <?php // Brilho ambiente atrás do card — só opacity (compositor), duas manchas respirando fora de fase. ?>
    <span class="auth-glow -left-24 -top-32 h-80 w-80 bg-cyan/20" aria-hidden="true"></span>
    <span class="auth-glow -bottom-32 -right-20 h-96 w-96 bg-cyan/10" aria-hidden="true" style="animation-delay: 3.2s"></span>

    <main id="conteudo" class="relative z-10 w-full max-w-sm">
        <div class="relative mx-auto mb-8 w-56">
            <span class="pointer-events-none absolute inset-0 -z-10 animate-pulse rounded-full bg-cyan/20 blur-2xl" aria-hidden="true"></span>
            <img src="/assets/brand/logo-lockup.webp" alt="COMPOST — Editorial Dashboard"
                 class="h-auto w-full drop-shadow-[0_0_30px_rgba(0,208,240,0.25)]">
        </div>

        <div class="auth-card relative rounded-3xl border border-border bg-surface p-7 shadow-2xl shadow-black/40 sm:p-8">
            <?= $content ?>
        </div>
    </main>
</body>
</html>

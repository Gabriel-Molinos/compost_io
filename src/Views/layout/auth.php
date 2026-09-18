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
<body class="app-bg flex min-h-screen flex-col items-center justify-center px-6 py-12 font-sans text-text-primary antialiased">
    <main id="conteudo" class="relative z-10 w-full max-w-sm">
        <img src="/assets/brand/logo-lockup.webp" alt="COMPOST — Editorial Dashboard"
             class="mx-auto mb-8 h-auto w-56 drop-shadow-[0_0_30px_rgba(0,208,240,0.25)]">

        <div class="auth-card relative rounded-3xl border border-border bg-surface p-7 shadow-2xl shadow-black/40 sm:p-8">
            <?= $content ?>
        </div>
    </main>
</body>
</html>

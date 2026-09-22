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
    <script src="<?= View::e(View::asset('assets/js/footer-clock.js')) ?>" defer></script>
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
<body class="app-bg relative min-h-screen overflow-x-hidden font-sans text-text-primary antialiased">
    <div class="page-veil" aria-hidden="true"></div>

    <?php
    // Composição em duas colunas — "estágio" de identidade (o que a marca
    // É) + "painel" de acesso (o que o usuário FAZ) — em vez do padrão
    // genérico [logo em cima -> card centralizado]. Colunas 1.3fr/1fr:
    // o estágio domina um pouco mais, o painel de formulário fica compacto
    // e objetivo (largura de leitura confortável, nunca esticado). Abaixo
    // de lg o estágio inteiro some (não "encolhe" — ver o cabeçalho
    // compacto dentro do <main>, composição própria pro mobile) e o
    // painel vira a tela inteira.
    ?>
    <div class="relative z-10 grid min-h-screen lg:grid-cols-[1.3fr_1fr]">

        <aside class="relative hidden flex-col justify-between overflow-hidden border-r border-border/50 px-14 py-12 lg:flex" aria-hidden="true">
            <div>
                <div class="inline-flex items-center gap-2.5">
                    <img src="/assets/brand/icon.webp" alt="" class="h-7 w-7">
                    <span class="font-display text-lg font-bold tracking-[.1em] text-text-primary">COMPOST</span>
                </div>
                <p class="mt-1.5 font-mono text-[11px] uppercase tracking-[.32em] text-cyan">Editorial Dashboard</p>
            </div>

            <div class="flex flex-1 items-center justify-center py-8">
                <div class="auth-portal" style="--portal-size: 13rem">
                    <span class="auth-portal-orbit"><span class="auth-portal-dot"></span></span>
                    <img src="/assets/brand/icon.webp" alt="" class="h-16 w-16">
                </div>
            </div>

            <div class="space-y-6">
                <p class="max-w-xs font-display text-xl font-semibold leading-snug text-text-primary">
                    A IA pesquisa, escreve e organiza.
                    <span class="text-cyan">Você decide</span> o que vai ao ar.
                </p>
                <ul class="flex flex-wrap gap-2">
                    <li class="rounded-full border border-cyan/25 bg-cyan/5 px-3 py-1 font-mono text-[10px] uppercase tracking-wide text-cyan">Aprovação sempre humana</li>
                    <li class="rounded-full border border-cyan/25 bg-cyan/5 px-3 py-1 font-mono text-[10px] uppercase tracking-wide text-cyan">Pipeline de IA operacional</li>
                    <li class="rounded-full border border-cyan/25 bg-cyan/5 px-3 py-1 font-mono text-[10px] uppercase tracking-wide text-cyan">Publicação direta no WordPress</li>
                </ul>
                <div class="w-40">
                    <?php require __DIR__ . '/_clock.php'; ?>
                </div>
            </div>
        </aside>

        <main id="conteudo" class="relative flex flex-col justify-center px-6 py-10 sm:px-10 lg:px-16 lg:py-12">
            <div class="mx-auto w-full max-w-sm lg:mx-0">
                <?php // Cabeçalho compacto — só < lg. É a composição PRÓPRIA do mobile
                      // (não o estágio desktop encolhido): marca + descritor numa
                      // linha, versão pequena do mesmo portal (mesma classe,
                      // --portal-size menor), sem o texto/tendência/relógio (não
                      // cabe com propósito numa tela pequena — o formulário é a
                      // prioridade ali). ?>
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <div class="auth-portal shrink-0" style="--portal-size: 3.75rem">
                        <span class="auth-portal-orbit"><span class="auth-portal-dot"></span></span>
                        <img src="/assets/brand/icon.webp" alt="" class="h-7 w-7">
                    </div>
                    <div>
                        <p class="font-display text-base font-bold tracking-[.08em] text-text-primary">COMPOST</p>
                        <p class="font-mono text-[10px] uppercase tracking-[.28em] text-cyan">Editorial Dashboard</p>
                    </div>
                </div>

                <div class="auth-card relative rounded-3xl border border-border bg-gradient-to-b from-[#0C3B58] to-[#071E2E] p-7 shadow-2xl shadow-black/50 sm:p-8">
                    <?= $content ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

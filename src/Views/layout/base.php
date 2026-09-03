<?php

declare(strict_types=1);

use App\Services\AuthService;
use App\Support\Csrf;
use App\Support\Session;
use App\View;

/** @var string $content */
/** @var string $title */
/** @var int|null $metaRefresh */

$authUser = AuthService::user();
$currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$flashSuccess = Session::pullFlash('success');
$flashError = Session::pullFlash('error');

$nav = [['/', 'Início']];
if ($authUser !== null) {
    if ($authUser['role'] === 'ADMIN') {
        $nav[] = ['/users', 'Usuários'];
    }
    $nav[] = ['/sites', 'Sites'];
}

$navClass = static fn (string $href): string => $href === $currentPath || ($href !== '/' && str_starts_with($currentPath, $href))
    ? 'text-text-primary'
    : 'text-text-secondary hover:text-text-primary';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="csrf-token" content="<?= View::e(Csrf::token()) ?>">
    <title><?= View::e($title) ?> · COMPOST</title>
    <?php if (!empty($metaRefresh)): ?>
        <meta http-equiv="refresh" content="<?= (int) $metaRefresh ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">

    <style>
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

    <div class="mx-auto flex min-h-screen max-w-4xl flex-col px-6 py-8">
        <header class="mb-8 flex flex-wrap items-center justify-between gap-4 border-b border-border pb-4">
            <div class="flex items-center gap-6">
                <a href="/" class="flex items-center gap-3">
                    <span aria-hidden="true"
                          class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-cyan/10 text-cyan">◆</span>
                    <span class="text-sm font-semibold tracking-[0.2em] text-cyan">COMPOST</span>
                </a>
                <?php if ($authUser !== null): ?>
                    <nav aria-label="Principal" class="flex gap-4 text-sm">
                        <?php foreach ($nav as [$href, $label]): ?>
                            <a href="<?= View::e($href) ?>" class="<?= $navClass($href) ?>"><?= View::e($label) ?></a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </div>

            <?php if ($authUser !== null): ?>
                <div class="flex items-center gap-3 text-sm">
                    <span class="hidden text-text-secondary sm:inline"><?= View::e($authUser['email']) ?></span>
                    <form method="post" action="/logout">
                        <?= Csrf::field() ?>
                        <button type="submit"
                                class="rounded-md border border-border px-3 py-1.5 text-text-secondary hover:border-cyan hover:text-text-primary">
                            Sair
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </header>

        <?php if ($flashSuccess !== null): ?>
            <p role="status" class="mb-6 rounded-md border border-success/40 bg-success/10 px-3 py-2 text-sm text-success">
                <?= View::e($flashSuccess) ?>
            </p>
        <?php endif; ?>
        <?php if ($flashError !== null): ?>
            <p role="alert" class="mb-6 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
                <?= View::e($flashError) ?>
            </p>
        <?php endif; ?>

        <main id="conteudo" class="flex-1">
            <?= $content ?>
        </main>

        <footer class="mt-12 border-t border-border pt-6 text-xs text-text-muted">
            COMPOST · <?= View::e(date('Y')) ?>
        </footer>
    </div>
</body>
</html>

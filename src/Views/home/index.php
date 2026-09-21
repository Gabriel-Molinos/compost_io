<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string, mixed>|null $user */
/** @var list<array<string, mixed>> $sites */
/** @var int $sitesTotal */
/** @var list<array<string, mixed>> $notifications Até 4, mais recentes primeiro */
/** @var int $unreadNotifications */
/** @var int $pendingFeedback Só conta pro ADMIN (Services\SidebarService) */

$isAdmin = $user !== null && $user['role'] === 'ADMIN';

// Saudação por horário — mais humana que um "Olá" genérico em qualquer hora do dia.
$hour = (int) date('G');
$greeting = match (true) {
    $hour < 6   => 'Boa madrugada',
    $hour < 12  => 'Bom dia',
    $hour < 18  => 'Boa tarde',
    default     => 'Boa noite',
};
$firstName = $user !== null ? explode(' ', trim((string) $user['name']))[0] : null;

// Mesmo mapeamento tipo → ícone da tela de notificações (Views/notifications/index.php) — só o
// necessário pra prévia aqui, sem puxar toda a interação (filtros, marcar lida) que é da tela cheia.
$typeIcon = static fn (string $type): string => match ($type) {
    'PUBLISH_SUCCESS' => 'check',
    'PUBLISH_FAILED', 'ATTENTION' => 'alert',
    'SITE_ASSIGNED'   => 'sites',
    'ARTICLE_READY'   => 'production',
    'FEEDBACK'        => 'feedback',
    default           => 'bell',
};
$ago = static function (int $ts): string {
    $d = max(0, time() - $ts);
    if ($d < 60) {
        return 'agora';
    }
    if ($d < 3600) {
        return 'há ' . intdiv($d, 60) . ' min';
    }
    if ($d < 86400) {
        return 'há ' . intdiv($d, 3600) . ' h';
    }

    return 'há ' . intdiv($d, 86400) . ' d';
};
?>
<?php // ── Boas-vindas: a marca em destaque no centro, o texto embaixo (pedido do responsável, 2026-09-21) ── ?>
<section class="article-card article-card--cyan hover-card rounded-3xl" style="--card-phase: -1.4s">
    <span class="article-card-fx" aria-hidden="true"></span>
    <?php if ($user !== null): ?>
        <span class="article-card-tags">
            <span class="article-card-tag article-card-tag--cyan">
                <span aria-hidden="true" class="status-dot h-1.5 w-1.5 rounded-full bg-current"></span>
                <?= View::e(Labels::role($user['role'])) ?>
            </span>
        </span>
    <?php endif; ?>

    <div class="relative flex flex-col items-center gap-5 px-6 py-12 text-center sm:py-16">
        <img src="/assets/brand/icon-512.png" alt="COMPOST" aria-hidden="true"
             class="h-28 w-28 rounded-full shadow-[0_0_0_2px_rgba(0,208,240,.5),0_0_60px_-10px_rgba(0,208,240,.65)] sm:h-36 sm:w-36">
        <div class="max-w-lg">
            <div class="flex items-center justify-center gap-3">
                <?php if ($user !== null): ?>
                    <a href="/profile" title="Trocar sua foto" class="hover-card block h-12 w-12 shrink-0 overflow-hidden rounded-full sm:h-14 sm:w-14">
                        <?= Avatar::html($user['avatar_path'] ?? null, $user['name'], size: 'h-full w-full', radius: 'rounded-full', textSize: 'text-lg') ?>
                    </a>
                <?php endif; ?>
                <h1 class="font-display text-3xl font-bold text-text-primary sm:text-4xl">
                    <?= View::e($greeting) ?><?= $firstName !== null ? ', ' . View::e($firstName) : '' ?>
                </h1>
            </div>
            <p class="mt-3 text-sm text-text-secondary sm:text-base">
                <?php if ($user !== null): ?>
                    Você está conectado(a) como <span class="text-text-primary"><?= View::e(Labels::role($user['role'])) ?></span>.
                    <?= $isAdmin
                        ? 'Por aqui você gerencia usuários, sites e acompanha a operação inteira.'
                        : 'Por aqui você acompanha e produz conteúdo pros sites vinculados a você.' ?>
                <?php else: ?>
                    Bem-vindo(a) ao COMPOST.
                <?php endif; ?>
            </p>
        </div>
    </div>
</section>

<?php if ($isAdmin): ?>
    <div class="mt-8 grid gap-4 sm:grid-cols-2">
        <a href="/users" class="hover-card group flex items-start gap-4 rounded-xl border border-border bg-surface p-5 hover:border-cyan">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('users') ?></span>
            <span class="min-w-0 flex-1">
                <span class="flex items-center justify-between gap-2">
                    <span class="block text-base font-semibold text-text-primary">Usuários</span>
                    <span aria-hidden="true" class="text-text-muted transition-transform group-hover:translate-x-0.5 group-hover:text-cyan">→</span>
                </span>
                <span class="mt-1 block text-sm text-text-secondary">Administradores e Redatores-Chefe, com vínculo por site.</span>
            </span>
        </a>
        <a href="/sites" class="hover-card group flex items-start gap-4 rounded-xl border border-border bg-surface p-5 hover:border-cyan">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('sites') ?></span>
            <span class="min-w-0 flex-1">
                <span class="flex items-center justify-between gap-2">
                    <span class="block text-base font-semibold text-text-primary">Sites</span>
                    <span aria-hidden="true" class="text-text-muted transition-transform group-hover:translate-x-0.5 group-hover:text-cyan">→</span>
                </span>
                <span class="mt-1 block text-sm text-text-secondary">Cadastro dos sites WordPress geridos pela plataforma.</span>
            </span>
        </a>
    </div>
<?php endif; ?>

<?php if ($user !== null): ?>
    <section aria-labelledby="meus-sites"
             class="relative mt-8 overflow-hidden rounded-xl border border-border bg-surface-2/30 p-6">
        <!-- Painel com identidade própria (não é mais um card genérico igual aos atalhos
             acima): brilho radial fixo no canto, avatar em degradê de marca por site,
             barra de acento embaixo do card que acende no hover — em vez de borda inteira. -->
        <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-cyan/10 blur-3xl"></div>

        <div class="relative flex flex-wrap items-center justify-between gap-3">
            <h2 id="meus-sites" class="font-display text-lg font-semibold text-text-primary">
                <?= $isAdmin ? 'Sites' : 'Meus sites' ?>
            </h2>
            <?php if ($sitesTotal > count($sites)): ?>
                <a href="/sites" class="text-sm text-cyan hover:text-cyan-bright">Ver todos (<?= $sitesTotal ?>) →</a>
            <?php endif; ?>
        </div>

        <?php if ($sites === []): ?>
            <p class="relative mt-3 text-sm text-text-secondary">
                <?= $isAdmin
                    ? 'Nenhum site cadastrado ainda.'
                    : 'Nenhum site vinculado ao seu usuário ainda. Peça ao administrador para te vincular a um site.' ?>
            </p>
        <?php else: ?>
            <?php // Header igual ao de /sites (pedido do responsável, 2026-09-21): mesmo `.article-card` com
                   // placa de logo, mas com o nicho na tag do canto — aqui é uma prévia, sem os fatos extras
                   // (idioma, WordPress, estatísticas) que só cabem na tela cheia de /sites. ?>
            <ul class="relative mt-5 space-y-4">
                <?php foreach ($sites as $i => $site): ?>
                    <?php
                    $active = (int) $site['is_active'] === 1;
                    $niche = trim((string) ($site['niche'] ?? ''));
                    $cardStyle = sprintf('--card-enter: %dms', min($i, 8) * 35);
                    ?>
                    <li class="article-card hover-card <?= Labels::articleCardTone($active ? 'success' : 'muted') ?> rounded-2xl" style="<?= $cardStyle ?>">
                        <span class="article-card-fx" aria-hidden="true"></span>
                        <span class="article-card-tags">
                            <?php if ($niche !== ''): ?>
                                <span class="article-card-tag article-card-tag--cyan">
                                    <?= Icon::nav('categories') ?>
                                    <?= View::e($niche) ?>
                                </span>
                            <?php endif; ?>
                            <span class="article-card-tag <?= $active ? 'article-card-tag--success' : 'article-card-tag--muted' ?>">
                                <?php if ($active): ?><span aria-hidden="true" class="status-dot h-1.5 w-1.5 rounded-full bg-current"></span><?php endif; ?>
                                <?= $active ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </span>

                        <div class="flex flex-col gap-4 p-4 pt-6 sm:flex-row sm:items-center">
                            <div class="flex h-20 w-full shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-3 shadow-[0_10px_30px_-14px_rgba(0,0,0,.8)] sm:w-40">
                                <span class="block w-full transition-transform duration-500 ease-out [.hover-card:hover_&]:scale-105">
                                    <?= Avatar::html($site['logo_path'] ?? null, (string) $site['name'], size: 'h-12 w-full', radius: 'rounded-lg', textSize: 'text-2xl', fit: 'contain', bg: 'bg-white') ?>
                                </span>
                            </div>

                            <h3 class="min-w-0 flex-1">
                                <a href="/sites/<?= View::e($site['id']) ?>"
                                   class="article-card-title block truncate font-display text-xl font-bold after:absolute after:inset-0 after:z-[1] after:rounded-2xl after:content-['']">
                                    <?= View::e($site['name']) ?>
                                </a>
                            </h3>

                            <a href="/sites/<?= View::e($site['id']) ?>" aria-label="Abrir <?= View::e($site['name']) ?>"
                               class="btn btn-primary group/open relative z-10 shrink-0 px-5 py-2.5 text-sm">
                                Abrir site
                                <span class="transition-transform duration-200 group-hover/open:translate-x-1 [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('arrow') ?></span>
                            </a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$isAdmin): ?>
                <p class="relative mt-4 text-sm text-text-muted">
                    Abra um site para configurar categorias, interesses e metas editoriais.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($user !== null): ?>
    <?php // ── Feedback: sempre visível, pra qualquer um avaliar a plataforma em 1 clique ── ?>
    <section class="hover-card mt-8 flex flex-col gap-4 rounded-2xl border border-border bg-surface p-6 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan/10 text-cyan"><?= Icon::nav('feedback') ?></span>
            <div>
                <h2 class="font-display text-base font-semibold text-text-primary">O que achou do COMPOST?</h2>
                <p class="mt-1 max-w-md text-sm text-text-secondary">
                    Conte o que trava, o que está bom ou o que sentiu falta — é sobre a plataforma em si,
                    pra melhorar ela pra todo mundo.
                </p>
            </div>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <?php if ($pendingFeedback > 0): ?>
                <span class="rounded-full bg-warning/15 px-2.5 py-1 text-xs font-semibold text-warning">
                    <?= $pendingFeedback ?> aguardando análise
                </span>
            <?php endif; ?>
            <a href="/feedback" class="btn btn-secondary px-4 py-2 text-sm">
                <?= $isAdmin ? 'Ver feedback recebido' : 'Ver meu histórico' ?>
            </a>
            <a href="/feedback" class="btn btn-primary px-4 py-2 text-sm">
                <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('plus') ?></span>
                Dar feedback
            </a>
        </div>
    </section>

    <?php // ── Notificações: prévia das mais recentes, a lista cheia é em /notifications ── ?>
    <section class="mt-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="flex items-center gap-2 font-display text-lg font-semibold text-text-primary">
                Notificações
                <?php if ($unreadNotifications > 0): ?>
                    <span class="rounded-full bg-cyan/15 px-2 py-0.5 text-xs font-bold text-cyan">
                        <?= $unreadNotifications ?> nova<?= $unreadNotifications > 1 ? 's' : '' ?>
                    </span>
                <?php endif; ?>
            </h2>
            <a href="/notifications" class="text-sm text-cyan hover:text-cyan-bright">Ver todas →</a>
        </div>

        <?php if ($notifications === []): ?>
            <p class="mt-3 text-sm text-text-secondary">Nenhuma notificação ainda.</p>
        <?php else: ?>
            <ul class="mt-4 space-y-3">
                <?php foreach ($notifications as $i => $n): ?>
                    <?php
                    $isUnread = $n['read_at'] === null;
                    $type = (string) $n['type'];
                    $tone = Labels::notificationTone($type);
                    $toneClass = Labels::articleCardTone($tone);
                    $mutedClass = Labels::articleCardTone('muted');
                    $ts = (int) strtotime((string) $n['created_at']);
                    ?>
                    <li class="article-card hover-card notif-card <?= $isUnread ? $toneClass : $mutedClass ?> rounded-xl"
                        style="--card-enter: <?= $i * 35 ?>ms">
                        <span class="article-card-fx" aria-hidden="true"></span>
                        <form method="post" action="/notifications/<?= View::e($n['id']) ?>/open" class="contents">
                            <?= Csrf::field() ?>
                            <button type="submit" class="relative flex w-full cursor-pointer items-center gap-3 p-3.5 text-left">
                                <span class="notif-icon flex h-9 w-9 shrink-0 items-center justify-center rounded-full [&>svg]:h-4 [&>svg]:w-4">
                                    <?= Icon::nav($typeIcon($type)) ?>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-2">
                                        <?php if ($isUnread): ?>
                                            <span aria-hidden="true" class="notif-dot status-dot h-1.5 w-1.5 shrink-0 rounded-full bg-current"></span>
                                        <?php endif; ?>
                                        <span class="notif-title truncate font-display text-sm font-semibold"><?= View::e($n['title']) ?></span>
                                    </span>
                                    <span class="mt-0.5 block truncate text-xs text-text-secondary"><?= View::e($n['message']) ?></span>
                                </span>
                                <span class="shrink-0 text-xs text-text-muted"><?= View::e($ago($ts)) ?></span>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>

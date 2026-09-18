<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var list<array<string, mixed>> $notifications */

$unread = array_filter($notifications, static fn (array $n): bool => $n['read_at'] === null);
$toneBorder = static fn (string $tone): string => match ($tone) {
    'success' => 'border-l-success', 'warning' => 'border-l-warning', 'danger' => 'border-l-danger',
    'cyan'    => 'border-l-cyan', default => 'border-l-border',
};
?>
<div class="flex flex-wrap items-start justify-between gap-3">
    <div class="flex items-start gap-3">
        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('bell') ?></span>
        <div>
            <h1 class="font-display text-2xl font-bold text-text-primary">Notificações</h1>
            <p class="mt-1 text-sm text-text-secondary">
                <?= count($unread) > 0 ? count($unread) . ' não lida(s) de ' . count($notifications) : count($notifications) . ' no total' ?>.
            </p>
        </div>
    </div>
    <?php if ($unread !== []): ?>
        <form method="post" action="/notifications/read-all">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-secondary px-3 py-1.5 text-sm">
                Marcar todas como lidas
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if ($notifications === []): ?>
    <p class="mt-8 text-text-secondary">Nenhuma notificação ainda.</p>
<?php else: ?>
    <ul class="mt-6 space-y-2">
        <?php foreach ($notifications as $n): ?>
            <?php
            $isUnread = $n['read_at'] === null;
            $tone = Labels::notificationTone((string) $n['type']);
            ?>
            <li class="overflow-hidden rounded-lg border-l-2 <?= $toneBorder($tone) ?> border-y border-r border-border <?= $isUnread ? 'bg-surface' : 'bg-surface/40' ?>">
                <form method="post" action="/notifications/<?= View::e($n['id']) ?>/open">
                    <?= Csrf::field() ?>
                    <button type="submit" class="flex w-full flex-wrap items-start justify-between gap-3 px-4 py-3.5 text-left transition-colors hover:bg-surface-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium <?= Labels::toneClasses($tone) ?>">
                                    <?= View::e(Labels::notificationTypeLabel((string) $n['type'])) ?>
                                </span>
                                <?php if (!empty($n['site_name'])): ?>
                                    <span class="text-xs text-text-muted"><?= View::e($n['site_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($isUnread): ?>
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-cyan" aria-hidden="true"></span>
                                <?php endif; ?>
                            </div>
                            <p class="mt-1.5 font-medium <?= $isUnread ? 'text-text-primary' : 'text-text-secondary' ?>">
                                <?= View::e($n['title']) ?>
                            </p>
                            <p class="mt-0.5 text-sm text-text-secondary"><?= View::e($n['message']) ?></p>
                        </div>
                        <span class="shrink-0 text-xs text-text-muted">
                            <?= View::e(date('d/m/Y H:i', strtotime((string) $n['created_at']))) ?>
                        </span>
                    </button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

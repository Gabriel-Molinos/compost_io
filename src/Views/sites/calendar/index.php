<?php

declare(strict_types=1);

use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string,mixed> $site */
/** @var DateTimeImmutable $month */
/** @var string $prevMonth */
/** @var string $nextMonth */
/** @var list<list<DateTimeImmutable>> $weeks */
/** @var array<string, list<array<string,mixed>>> $byDay */
/** @var string $todayYmd */

$activeTab = 'calendar';
require __DIR__ . '/../_tabs.php';

$meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$monthLabel = $meses[(int) $month->format('n')] . ' ' . $month->format('Y');
$monthNum = $month->format('m');
$currentMonthYmd = $month->format('Y-m');
$isCurrentMonth = $currentMonthYmd === (new DateTimeImmutable($todayYmd))->format('Y-m');

// Cor do evento vem de Labels::scheduleStatusTone() — mesma fonte de verdade
// da badge usada em Produção, pra nunca ter dois mapas de cor divergentes
// pro mesmo status.
$toneDot = static fn (string $tone): string => match ($tone) {
    'success' => 'bg-success', 'danger' => 'bg-danger', default => 'bg-cyan', // cyan = PENDING
};
$toneText = static fn (string $tone): string => match ($tone) {
    'success' => 'text-success', 'danger' => 'text-danger', default => 'text-cyan',
};
$toneRing = static fn (string $tone): string => match ($tone) {
    'success' => 'ring-success/60', 'danger' => 'ring-danger/60', default => 'ring-cyan/60',
};
$statusLabel = static fn (string $status): string => mb_strtolower(Labels::scheduleStatus($status));

// Total do mês, pra dar um número de resumo no cabeçalho sem precisar contar
// cards na grade — soma direto de $byDay (já filtrado pro mês pelo controller).
$monthTotal = 0;
$monthPublished = 0;
foreach ($byDay as $dayItems) {
    foreach ($dayItems as $it) {
        $monthTotal++;
        if ($it['status'] === 'PUBLISHED') {
            $monthPublished++;
        }
    }
}

$diasSemana = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

/**
 * Um dia costuma ter só 1 publicação agendada (pedido 2026-09-22) — nesse
 * caso a imagem destacada vira o card inteiro do dia, título por cima, bem
 * mais fácil de reconhecer o post de relance do que uma pilulazinha de
 * texto. Quando têm 2+ (raro — vários agendamentos remarcados pro mesmo
 * dia), a célula volta pra uma lista compacta, com miniatura. Extraído em
 * closure porque a mesma pílula/miniatura serve os dois casos.
 */
$dayCard = static function (array $it, string $siteId, bool $compact) use ($toneDot, $toneRing, $statusLabel): string {
    $tone = Labels::scheduleStatusTone((string) $it['status']);
    $pending = $it['status'] === 'PENDING';
    $title = (string) ($it['title'] ?: 'Rascunho #' . $it['article_id']);
    $time = substr((string) $it['scheduled_date'], 11, 5);
    $tooltip = $title . ' — ' . $time . ' · ' . $statusLabel((string) $it['status'])
        . ($it['author_name'] ? ' · ' . $it['author_name'] : '')
        . ($pending ? ' · arraste pra outro dia pra reagendar' : '');
    $dragAttr = $pending ? ' data-schedule-article="' . View::e($it['article_id']) . '"' : '';
    $dragCls = $pending ? ' cursor-grab active:cursor-grabbing' : '';
    $href = '/sites/' . View::e($siteId) . '/production/' . View::e($it['article_id']) . '#agendar';

    if ($compact) {
        // Miniatura quadrada + título — mesma ideia do card grande, só que
        // pequena o bastante pra empilhar quando o dia tem mais de um item.
        $thumb = !empty($it['image_url'])
            ? '<img src="' . View::e($it['image_url']) . '" alt="" loading="lazy" class="h-8 w-8 shrink-0 rounded object-cover ring-1 ' . $toneRing($tone) . '">'
            : '<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-surface-2 text-text-muted ring-1 ' . $toneRing($tone) . '">' . Icon::nav('production') . '</span>';

        return '<a href="' . $href . '"' . $dragAttr
            . ' class="group flex items-center gap-2 rounded-md px-1 py-1 transition-colors hover:bg-surface-2' . $dragCls . '"'
            . ' title="' . View::e($tooltip) . '">'
            . $thumb
            . '<span class="min-w-0 flex-1">'
            . '<span class="block truncate text-[11px] font-medium text-text-secondary group-hover:text-text-primary">' . View::e($title) . '</span>'
            . '<span class="flex items-center gap-1 font-mono text-[10px] text-text-muted"><span class="h-1.5 w-1.5 shrink-0 rounded-full ' . $toneDot($tone) . '"></span>' . View::e($time) . '</span>'
            . '</span></a>';
    }

    // Card grande: imagem destacada preenchendo a célula. Overlay em DUAS
    // camadas entre a imagem e o título — um escurecimento parelho por cima
    // da foto inteira (pra nenhuma imagem clara "estourar" perto do texto),
    // mais o gradiente concentrado embaixo (onde o título/hora ficam) pra
    // legibilidade garantida em qualquer imagem, clara ou escura. Sem
    // imagem (raro: agendamento antigo sem destacada), cai num bloco só com
    // ícone + título, na cor do status.
    $media = !empty($it['image_url'])
        ? '<img src="' . View::e($it['image_url']) . '" alt="" loading="lazy" '
            . 'class="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">'
            . '<span class="absolute inset-0 bg-[#050B0F]/30"></span>'
            . '<span class="absolute inset-0 bg-gradient-to-t from-[#050B0F]/95 via-[#050B0F]/25 to-transparent"></span>'
        : '<span class="absolute inset-0 flex items-center justify-center bg-surface-2 text-text-muted [&>svg]:h-6 [&>svg]:w-6">' . Icon::nav('production') . '</span>'
            . '<span class="absolute inset-0 bg-gradient-to-t from-[#050B0F]/85 via-[#050B0F]/10 to-transparent"></span>';

    return '<a href="' . $href . '"' . $dragAttr
        . ' class="group relative block h-full w-full overflow-hidden rounded-md' . $dragCls . '"'
        . ' title="' . View::e($tooltip) . '">'
        . $media
        . '<span class="absolute inset-x-0 bottom-0 p-1.5">'
        . '<span class="line-clamp-2 text-[11px] font-semibold leading-tight text-white drop-shadow">' . View::e($title) . '</span>'
        . '<span class="mt-0.5 flex items-center gap-1 font-mono text-[10px] text-white/80"><span class="h-1.5 w-1.5 shrink-0 rounded-full ' . $toneDot($tone) . '"></span>' . View::e($time) . '</span>'
        . '</span></a>';
};
?>
<div class="flex items-start gap-3">
    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan/10 text-cyan"><?= Icon::nav('calendar') ?></span>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-bold text-text-primary">Calendário editorial</h1>
                <p class="mt-1 text-sm text-text-secondary">
                    <?= $monthTotal ?> agendamento<?= $monthTotal === 1 ? '' : 's' ?> em <?= View::e($monthLabel) ?>
                    <?php if ($monthPublished > 0): ?>
                        · <span class="text-success"><?= $monthPublished ?> publicado<?= $monthPublished === 1 ? '' : 's' ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <?php if (!$isCurrentMonth): ?>
                    <a href="?month=<?= View::e((new DateTimeImmutable($todayYmd))->format('Y-m')) ?>"
                       class="btn btn-secondary px-3 py-1.5 text-sm">
                        Hoje
                    </a>
                <?php endif; ?>
                <div class="flex items-center overflow-hidden rounded-md border border-border">
                    <a href="?month=<?= View::e($prevMonth) ?>" aria-label="Mês anterior"
                       class="flex h-8 w-8 items-center justify-center text-text-secondary hover:bg-surface-2 hover:text-text-primary">‹</a>
                    <span class="min-w-[8.5rem] border-x border-border px-3 py-1.5 text-center font-display text-sm font-semibold text-text-primary">
                        <?= View::e($monthLabel) ?>
                    </span>
                    <a href="?month=<?= View::e($nextMonth) ?>" aria-label="Próximo mês"
                       class="flex h-8 w-8 items-center justify-center text-text-secondary hover:bg-surface-2 hover:text-text-primary">›</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grade mensal — telas lg+. Em telas menores vira agenda (abaixo): uma
     grade de 7 colunas apertadas com card de imagem não cabe legível
     num celular, então a página troca de layout em vez de só encolher. -->
<div data-tour="calendar-grid" class="mt-5 hidden overflow-hidden rounded-xl border border-border bg-surface lg:block">
    <div class="grid grid-cols-7 border-b border-border bg-surface-2/40">
        <?php foreach ($diasSemana as $d): ?>
            <div class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-text-muted"><?= $d ?></div>
        <?php endforeach; ?>
    </div>
    <div class="grid grid-cols-7">
        <?php foreach ($weeks as $wi => $week): ?>
            <?php foreach ($week as $day): ?>
                <?php
                $ymd = $day->format('Y-m-d');
                $inMonth = $day->format('m') === $monthNum;
                $items = $byDay[$ymd] ?? [];
                $isToday = $ymd === $todayYmd;
                $compact = count($items) > 1;
                ?>
                <div data-calendar-day="<?= View::e($ymd) ?>"
                     class="flex h-36 flex-col gap-1 border-b border-r border-border p-1.5 transition-colors last:border-r-0
                            <?= $isToday ? 'bg-cyan/[.04]' : '' ?>
                            <?= $inMonth ? 'hover:bg-surface-2/40' : 'bg-void/80' ?>">
                    <div class="flex shrink-0 items-center justify-between">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full text-[11px]
                                     <?= $isToday ? 'bg-cyan font-semibold text-[#050B0F]' : ($inMonth ? 'text-text-secondary' : 'text-text-muted/50') ?>">
                            <?= (int) $day->format('j') ?>
                        </span>
                        <?php if ($compact): ?>
                            <span class="rounded-full bg-surface-2 px-1.5 text-[10px] font-mono text-text-muted"><?= count($items) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="min-h-0 flex-1 <?= $compact ? 'space-y-0.5 overflow-y-auto pr-0.5' : '' ?> <?= $inMonth ? '' : 'opacity-50' ?>">
                        <?php foreach ($items as $it): ?>
                            <?= $dayCard($it, (string) $site['id'], $compact) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>

<!-- Agenda — telas < lg. Só os dias com agendamento aparecem (evita rolar
     uma grade vazia de 5-6 semanas no celular). -->
<div class="mt-5 space-y-3 lg:hidden">
    <?php
    $hasAny = false;
    foreach ($weeks as $week) {
        foreach ($week as $day) {
            $ymd = $day->format('Y-m-d');
            if ($day->format('m') !== $monthNum || ($byDay[$ymd] ?? []) === []) {
                continue;
            }
            $hasAny = true;
            $items = $byDay[$ymd];
            $isToday = $ymd === $todayYmd;
            ?>
            <div class="overflow-hidden rounded-xl border <?= $isToday ? 'border-cyan/50' : 'border-border' ?> bg-surface">
                <div class="flex items-center gap-2 border-b border-border bg-surface-2/40 px-4 py-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs <?= $isToday ? 'bg-cyan font-semibold text-[#050B0F]' : 'text-text-secondary' ?>">
                        <?= (int) $day->format('j') ?>
                    </span>
                    <span class="text-sm font-medium text-text-primary"><?= $diasSemana[(int) $day->format('N') - 1] ?></span>
                    <?php if ($isToday): ?><span class="text-xs text-cyan">hoje</span><?php endif; ?>
                </div>
                <ul class="divide-y divide-border">
                    <?php foreach ($items as $it): ?>
                        <?php
                        $tone = Labels::scheduleStatusTone((string) $it['status']);
                        $title = (string) ($it['title'] ?: 'Rascunho #' . $it['article_id']);
                        ?>
                        <li>
                            <a href="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($it['article_id']) ?>#agendar"
                               class="flex items-center gap-3 px-4 py-3 hover:bg-surface-2/40">
                                <?php if (!empty($it['image_url'])): ?>
                                    <img src="<?= View::e($it['image_url']) ?>" alt="" loading="lazy"
                                         class="h-11 w-11 shrink-0 rounded-md object-cover ring-1 <?= $toneRing($tone) ?>">
                                <?php else: ?>
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-surface-2 text-text-muted [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('production') ?></span>
                                <?php endif; ?>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-text-primary"><?= View::e($title) ?></span>
                                    <span class="mt-0.5 flex items-center gap-1.5 text-xs text-text-muted">
                                        <span class="font-mono"><?= View::e(substr((string) $it['scheduled_date'], 11, 5)) ?></span>
                                        <span aria-hidden="true">·</span>
                                        <span class="flex items-center gap-1 <?= $toneText($tone) ?>">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full <?= $toneDot($tone) ?>"></span><?= View::e($statusLabel((string) $it['status'])) ?>
                                        </span>
                                    </span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        }
    }
    if (!$hasAny): ?>
        <p class="rounded-xl border border-border bg-surface p-6 text-center text-sm text-text-secondary">
            Nenhum agendamento em <?= View::e($monthLabel) ?>.
        </p>
    <?php endif; ?>
</div>

<div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-text-muted">
    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-cyan"></span> agendado</span>
    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-success"></span> publicado</span>
    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-danger"></span> falhou</span>
    <span class="hidden text-text-muted lg:inline">· arraste um agendamento pendente pra outro dia pra reagendar</span>
</div>

<script src="<?= View::e(View::asset('assets/js/calendar.js')) ?>" defer></script>

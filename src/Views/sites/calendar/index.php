<?php

declare(strict_types=1);

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
$statusLabel = static fn (string $status): string => mb_strtolower(Labels::scheduleStatus($status));

// Total do mês, pra dar um número de resumo no cabeçalho sem precisar contar
// pílulas na grade — soma direto de $byDay (já filtrado pro mês pelo controller).
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
?>
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

<!-- Grade mensal — telas lg+. Em telas menores vira agenda (abaixo): uma
     grade de 7 colunas apertadas com pílulas de evento não cabe legível
     num celular, então a página troca de layout em vez de só encolher. -->
<div data-tour="calendar-grid" class="mt-5 hidden overflow-hidden rounded-lg border border-border bg-surface lg:block">
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
                ?>
                <div data-calendar-day="<?= View::e($ymd) ?>"
                     class="flex h-32 flex-col border-b border-r border-border p-1.5 transition-colors last:border-r-0
                            <?= $inMonth ? 'hover:bg-surface-2/40' : 'bg-void/80' ?>">
                    <div class="flex shrink-0 items-center justify-between">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs
                                     <?= $isToday ? 'bg-cyan font-semibold text-[#050B0F]' : ($inMonth ? 'text-text-secondary' : 'text-text-muted/50') ?>">
                            <?= (int) $day->format('j') ?>
                        </span>
                        <?php if (count($items) > 0): ?>
                            <span class="text-[10px] font-mono text-text-muted"><?= count($items) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-1 min-h-0 flex-1 space-y-1 overflow-y-auto pr-0.5 <?= $inMonth ? '' : 'opacity-50' ?>">
                        <?php foreach ($items as $it): ?>
                            <?php
                            $tone = Labels::scheduleStatusTone((string) $it['status']);
                            $pending = $it['status'] === 'PENDING';
                            ?>
                            <a href="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($it['article_id']) ?>#agendar"
                               <?php if ($pending): ?>data-schedule-article="<?= View::e($it['article_id']) ?>"<?php endif; ?>
                               class="group flex items-center gap-1.5 rounded px-1 py-0.5 transition-colors hover:bg-surface-2 <?= $pending ? 'cursor-grab active:cursor-grabbing' : '' ?>"
                               title="<?= View::e($it['title'] ?: 'Rascunho #' . $it['article_id']) ?> — <?= View::e(substr((string) $it['scheduled_date'], 11, 5)) ?> · <?= View::e($statusLabel((string) $it['status'])) ?><?= $it['author_name'] ? ' · ' . View::e($it['author_name']) : '' ?><?= $pending ? ' · arraste pra outro dia pra reagendar' : '' ?>">
                                <span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full <?= $toneDot($tone) ?>"></span>
                                <span class="shrink-0 font-mono text-[10px] text-text-muted"><?= View::e(substr((string) $it['scheduled_date'], 11, 5)) ?></span>
                                <span class="truncate text-xs text-text-secondary group-hover:text-text-primary"><?= View::e($it['title'] ?: 'Rascunho #' . $it['article_id']) ?></span>
                            </a>
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
            <div class="overflow-hidden rounded-lg border <?= $isToday ? 'border-cyan/50' : 'border-border' ?> bg-surface">
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
                        ?>
                        <li>
                            <a href="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($it['article_id']) ?>#agendar"
                               class="flex items-center gap-3 px-4 py-3 hover:bg-surface-2/40">
                                <span aria-hidden="true" class="h-2 w-2 shrink-0 rounded-full <?= $toneDot($tone) ?>"></span>
                                <span class="w-11 shrink-0 font-mono text-xs text-text-muted"><?= View::e(substr((string) $it['scheduled_date'], 11, 5)) ?></span>
                                <span class="min-w-0 flex-1 truncate text-sm text-text-primary"><?= View::e($it['title'] ?: 'Rascunho #' . $it['article_id']) ?></span>
                                <span class="shrink-0 text-xs font-medium <?= $toneText($tone) ?>"><?= View::e($statusLabel((string) $it['status'])) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        }
    }
    if (!$hasAny): ?>
        <p class="rounded-lg border border-border bg-surface p-6 text-center text-sm text-text-secondary">
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

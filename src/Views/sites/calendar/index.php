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

// Cada item do calendário é um link compacto colorido, não a badge padrão
// (não cabe pílula+ponto numa célula de ~4rem) — mas o tom vem de
// Labels::scheduleStatusTone(), mesma fonte de verdade da badge usada em
// Produção, pra não ter dois mapas de cor divergentes pro mesmo status.
$badge = static fn (string $status): string => Labels::toneClasses(Labels::scheduleStatusTone($status));
$statusLabel = static fn (string $status): string => mb_strtolower(Labels::scheduleStatus($status));
?>
<div class="flex flex-wrap items-center justify-between gap-3">
    <h2 class="font-display text-lg font-semibold text-text-primary">Calendário editorial</h2>
    <div class="flex items-center gap-2 text-sm">
        <a href="?month=<?= View::e($prevMonth) ?>"
           class="rounded-md border border-border px-2 py-1 text-text-secondary hover:text-text-primary">←</a>
        <span class="min-w-[9rem] text-center font-medium text-text-primary"><?= View::e($monthLabel) ?></span>
        <a href="?month=<?= View::e($nextMonth) ?>"
           class="rounded-md border border-border px-2 py-1 text-text-secondary hover:text-text-primary">→</a>
    </div>
</div>
<p class="mt-1 text-sm text-text-secondary">
    Agendamentos deste site. Arraste um item agendado pra outro dia pra reagendar (mantém autor,
    imagem e horário) — ou abra o artigo pra mudar tudo, ou cancelar.
</p>

<div class="mt-5 overflow-x-auto">
    <table class="w-full min-w-[52rem] table-fixed border-collapse text-sm">
        <thead>
            <tr class="text-xs uppercase tracking-wide text-text-muted">
                <?php foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $d): ?>
                    <th class="border border-border px-2 py-1 font-medium"><?= $d ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($weeks as $week): ?>
                <tr>
                    <?php foreach ($week as $day): ?>
                        <?php
                        $ymd = $day->format('Y-m-d');
                        $inMonth = $day->format('m') === $monthNum;
                        $items = $byDay[$ymd] ?? [];
                        ?>
                        <td data-calendar-day="<?= View::e($ymd) ?>"
                            class="h-28 border border-border p-1 align-top transition-colors <?= $inMonth ? 'hover:bg-surface-2/50' : 'bg-surface/40 text-text-muted' ?>">
                            <div class="flex items-center justify-between">
                                <span class="text-xs <?= $ymd === $todayYmd ? 'rounded bg-cyan px-1.5 font-semibold text-[#050B0F]' : 'text-text-muted' ?>">
                                    <?= (int) $day->format('j') ?>
                                </span>
                            </div>
                            <div class="mt-1 space-y-1">
                                <?php foreach ($items as $it): ?>
                                    <?php $pending = $it['status'] === 'PENDING'; ?>
                                    <a href="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($it['article_id']) ?>#agendar"
                                       <?php if ($pending): ?>data-schedule-article="<?= View::e($it['article_id']) ?>"<?php endif; ?>
                                       class="block truncate rounded px-1 py-0.5 text-xs transition-all hover:brightness-125 hover:shadow-[0_0_0_1px_currentColor] <?= $badge((string) $it['status']) ?> <?= $pending ? 'cursor-grab active:cursor-grabbing' : '' ?>"
                                       title="<?= View::e($it['title'] ?: 'Rascunho #' . $it['article_id']) ?> — <?= View::e(substr((string) $it['scheduled_date'], 11, 5)) ?> · <?= View::e($statusLabel((string) $it['status'])) ?><?= $it['author_name'] ? ' · ' . View::e($it['author_name']) : '' ?><?= $pending ? ' · arraste pra outro dia pra reagendar' : '' ?>">
                                        <?= View::e(substr((string) $it['scheduled_date'], 11, 5)) ?>
                                        <?= View::e($it['title'] ?: 'Rascunho #' . $it['article_id']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="mt-4 flex flex-wrap gap-4 text-xs text-text-muted">
    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-cyan/15"></span> agendado</span>
    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-success/15"></span> publicado</span>
</div>

<script src="<?= View::e(View::asset('assets/js/calendar.js')) ?>" defer></script>

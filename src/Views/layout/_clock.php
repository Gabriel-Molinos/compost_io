<?php

declare(strict_types=1);

use App\View;

// Cartão de relógio/data do disco da sidebar (layout/_dial.php). Só data-attributes,
// nunca id. O servidor já entrega a hora certa (sem flash de "00:00"); assets/js/footer-clock.js
// mantém ao vivo (hora de Brasília, ponto piscando, barra dos segundos, data à meia-noite).
$dias = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB'];
?>
<div class="clock-card" data-clock-root>
    <div class="relative flex items-center justify-between text-[9px] font-bold uppercase tracking-[.16em]">
        <span class="text-cyan" data-clock-wd><?= $dias[(int) date('w')] ?></span>
        <span class="inline-flex items-center gap-1 text-success">
            <span aria-hidden="true" class="status-dot h-1 w-1 rounded-full bg-current"></span>BRT
        </span>
    </div>

    <div class="relative mt-1.5 flex items-baseline font-display tabular-nums leading-none" role="timer" aria-label="Hora atual">
        <span class="text-[1.55rem] font-bold text-text-primary" data-clock-h><?= date('H') ?></span>
        <span aria-hidden="true" class="clock-colon mx-px text-xl font-bold text-cyan">:</span>
        <span class="text-[1.55rem] font-bold text-text-primary" data-clock-m><?= date('i') ?></span>
        <span class="ml-1 text-[.7rem] font-semibold text-cyan" data-clock-s><?= date('s') ?></span>
    </div>

    <div class="clock-bar relative mt-2" aria-hidden="true">
        <span data-clock-bar style="--sec: <?= round((int) date('s') / 60, 3) ?>"></span>
    </div>

    <p class="relative mt-2 font-mono text-[10px] tracking-wide text-text-secondary" data-clock-date><?= View::e(View::todayShort()) ?></p>
</div>

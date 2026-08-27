<?php

declare(strict_types=1);

use App\View;

/** @var array{connected: bool, tables?: int, users?: int, error?: string} $db */
/** @var array<string, mixed>|null $user */
?>
<h1 class="text-2xl font-bold text-text-primary">
    Olá<?= $user !== null ? ', ' . View::e($user['name']) : '' ?>
</h1>
<p class="mt-2 text-text-secondary">
    <?php if ($user !== null): ?>
        Você está autenticado como <span class="font-mono text-text-primary"><?= View::e($user['role']) ?></span>.
    <?php endif; ?>
</p>

<section aria-labelledby="status-banco" class="mt-8 rounded-lg border border-border bg-surface p-5">
    <h2 id="status-banco" class="text-sm font-semibold uppercase tracking-wide text-text-muted">
        Banco de dados
    </h2>

    <?php if ($db['connected']): ?>
        <p class="mt-3 flex items-center gap-2 text-success">
            <span aria-hidden="true">●</span>
            <span>Conectado ao MySQL (SSL)</span>
        </p>
        <dl class="mt-4 grid grid-cols-2 gap-4 font-mono text-sm">
            <div>
                <dt class="text-text-muted">Tabelas</dt>
                <dd class="text-lg text-text-primary"><?= View::e($db['tables'] ?? 0) ?></dd>
            </div>
            <div>
                <dt class="text-text-muted">Usuários</dt>
                <dd class="text-lg text-text-primary"><?= View::e($db['users'] ?? 0) ?></dd>
            </div>
        </dl>
    <?php else: ?>
        <p class="mt-3 flex items-center gap-2 text-danger">
            <span aria-hidden="true">●</span>
            <span>Sem conexão — <?= View::e($db['error'] ?? 'erro desconhecido') ?></span>
        </p>
    <?php endif; ?>
</section>

<section aria-labelledby="proximos" class="mt-8">
    <h2 id="proximos" class="text-sm font-semibold uppercase tracking-wide text-text-muted">
        Em construção — Fase 2
    </h2>
    <ul class="mt-3 space-y-1 text-text-secondary">
        <li>CRUD de usuários e sites</li>
        <li>Sistema de permissões por site (<span class="font-mono">user_site</span>)</li>
    </ul>
</section>

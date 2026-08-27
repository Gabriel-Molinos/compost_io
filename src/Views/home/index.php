<?php

declare(strict_types=1);

use App\View;

/** @var array{connected: bool, tables?: int, users?: int, error?: string} $db */
/** @var array<string, mixed>|null $user */

$isAdmin = $user !== null && $user['role'] === 'ADMIN';
?>
<h1 class="text-2xl font-bold text-text-primary">
    Olá<?= $user !== null ? ', ' . View::e($user['name']) : '' ?>
</h1>
<p class="mt-2 text-text-secondary">
    <?php if ($user !== null): ?>
        Perfil <span class="font-mono text-text-primary"><?= View::e($user['role']) ?></span>.
    <?php endif; ?>
</p>

<?php if ($isAdmin): ?>
    <div class="mt-8 grid gap-4 sm:grid-cols-2">
        <a href="/users" class="rounded-lg border border-border bg-surface p-5 hover:border-cyan">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Usuários</h2>
            <p class="mt-2 text-text-secondary">Administradores e Redatores-Chefe, com vínculo por site.</p>
        </a>
        <a href="/sites" class="rounded-lg border border-border bg-surface p-5 hover:border-cyan">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Sites</h2>
            <p class="mt-2 text-text-secondary">Cadastro dos sites WordPress geridos pela plataforma.</p>
        </a>
    </div>
<?php endif; ?>

<section aria-labelledby="status-banco" class="mt-8 rounded-lg border border-border bg-surface p-5">
    <h2 id="status-banco" class="text-sm font-semibold uppercase tracking-wide text-text-muted">Banco de dados</h2>
    <?php if ($db['connected']): ?>
        <p class="mt-3 flex items-center gap-2 text-success">
            <span aria-hidden="true">●</span><span>Conectado ao MySQL (SSL)</span>
        </p>
        <dl class="mt-4 grid grid-cols-2 gap-4 font-mono text-sm">
            <div><dt class="text-text-muted">Tabelas</dt><dd class="text-lg text-text-primary"><?= View::e($db['tables'] ?? 0) ?></dd></div>
            <div><dt class="text-text-muted">Usuários</dt><dd class="text-lg text-text-primary"><?= View::e($db['users'] ?? 0) ?></dd></div>
        </dl>
    <?php else: ?>
        <p class="mt-3 flex items-center gap-2 text-danger">
            <span aria-hidden="true">●</span><span>Sem conexão — <?= View::e($db['error'] ?? 'erro desconhecido') ?></span>
        </p>
    <?php endif; ?>
</section>

<section aria-labelledby="proximos" class="mt-8">
    <h2 id="proximos" class="text-sm font-semibold uppercase tracking-wide text-text-muted">Próximo</h2>
    <p class="mt-3 text-text-secondary">Fase 3 — configuração editorial por site: categorias, metas, interesses e não-interesses.</p>
</section>

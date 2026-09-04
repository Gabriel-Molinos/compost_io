<?php

declare(strict_types=1);

use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array{connected: bool, tables?: int, users?: int, error?: string} $db */
/** @var array<string, mixed>|null $user */
/** @var list<array<string, mixed>> $mySites */

$isAdmin = $user !== null && $user['role'] === 'ADMIN';
?>
<h1 class="font-display text-2xl font-bold text-text-primary">
    Olá<?= $user !== null ? ', ' . View::e($user['name']) : '' ?>
</h1>
<p class="mt-2 text-sm text-text-secondary">
    <?php if ($user !== null): ?>
        Perfil: <span class="text-text-primary"><?= View::e(Labels::role($user['role'])) ?></span>.
    <?php endif; ?>
</p>

<?php if ($isAdmin): ?>
    <div class="mt-8 grid gap-4 sm:grid-cols-2">
        <a href="/users" class="hover-card flex items-start gap-3 rounded-lg border border-border bg-surface p-5 hover:border-cyan">
            <span class="mt-0.5 text-text-muted"><?= Icon::nav('users') ?></span>
            <span>
                <span class="block text-base font-semibold text-text-primary">Usuários</span>
                <span class="mt-1 block text-sm text-text-secondary">Administradores e Redatores-Chefe, com vínculo por site.</span>
            </span>
        </a>
        <a href="/sites" class="hover-card flex items-start gap-3 rounded-lg border border-border bg-surface p-5 hover:border-cyan">
            <span class="mt-0.5 text-text-muted"><?= Icon::nav('sites') ?></span>
            <span>
                <span class="block text-base font-semibold text-text-primary">Sites</span>
                <span class="mt-1 block text-sm text-text-secondary">Cadastro dos sites WordPress geridos pela plataforma.</span>
            </span>
        </a>
    </div>
<?php else: ?>
    <section aria-labelledby="meus-sites" class="mt-8">
        <h2 id="meus-sites" class="text-xs font-semibold uppercase tracking-wide text-text-muted">Meus sites</h2>
        <?php if ($mySites === []): ?>
            <p class="mt-3 text-sm text-text-secondary">
                Nenhum site vinculado ao seu usuário ainda. Peça ao administrador para te vincular a um site.
            </p>
        <?php else: ?>
            <ul class="mt-3 divide-y divide-border rounded-lg border border-border bg-surface">
                <?php foreach ($mySites as $site): ?>
                    <li>
                        <a href="/sites/<?= View::e($site['id']) ?>"
                           class="flex items-center justify-between px-4 py-3 hover:bg-surface">
                            <span class="text-text-primary"><?= View::e($site['name']) ?></span>
                            <span class="text-sm text-text-muted">
                                <?= View::e($site['niche'] ?? '—') ?>
                                <?php if ((int) $site['is_active'] !== 1): ?>· inativo<?php endif; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-3 text-sm text-text-muted">
                Abra um site para configurar categorias, interesses e metas editoriais.
            </p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($isAdmin): ?>
    <section aria-labelledby="status-banco" class="mt-8 rounded-lg border border-border bg-surface p-5">
        <h2 id="status-banco" class="text-xs font-semibold uppercase tracking-wide text-text-muted">Banco de dados</h2>
        <?php if ($db['connected']): ?>
            <p class="mt-3 flex items-center gap-2 text-sm text-success">
                <span aria-hidden="true" class="status-dot">●</span><span>Conectado ao MySQL (SSL)</span>
            </p>
            <dl class="mt-4 grid grid-cols-2 gap-4">
                <div><dt class="text-xs text-text-muted">Tabelas</dt><dd class="font-display text-2xl font-semibold text-text-primary"><?= View::e($db['tables'] ?? 0) ?></dd></div>
                <div><dt class="text-xs text-text-muted">Usuários</dt><dd class="font-display text-2xl font-semibold text-text-primary"><?= View::e($db['users'] ?? 0) ?></dd></div>
            </dl>
        <?php else: ?>
            <p class="mt-3 flex items-center gap-2 text-sm text-danger">
                <span aria-hidden="true">●</span><span>Sem conexão — <?= View::e($db['error'] ?? 'erro desconhecido') ?></span>
            </p>
        <?php endif; ?>
    </section>
<?php endif; ?>

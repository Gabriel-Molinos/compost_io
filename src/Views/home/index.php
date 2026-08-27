<?php

declare(strict_types=1);

use App\Support\Labels;
use App\View;

/** @var array{connected: bool, tables?: int, users?: int, error?: string} $db */
/** @var array<string, mixed>|null $user */
/** @var list<array<string, mixed>> $mySites */

$isAdmin = $user !== null && $user['role'] === 'ADMIN';
?>
<h1 class="text-2xl font-bold text-text-primary">
    Olá<?= $user !== null ? ', ' . View::e($user['name']) : '' ?>
</h1>
<p class="mt-2 text-text-secondary">
    <?php if ($user !== null): ?>
        Perfil: <span class="text-text-primary"><?= View::e(Labels::role($user['role'])) ?></span>.
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
<?php else: ?>
    <section aria-labelledby="meus-sites" class="mt-8">
        <h2 id="meus-sites" class="text-sm font-semibold uppercase tracking-wide text-text-muted">Meus sites</h2>
        <?php if ($mySites === []): ?>
            <p class="mt-3 text-text-secondary">
                Nenhum site vinculado ao seu usuário ainda. Peça ao administrador para te vincular a um site.
            </p>
        <?php else: ?>
            <ul class="mt-3 divide-y divide-border rounded-lg border border-border">
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
<?php endif; ?>

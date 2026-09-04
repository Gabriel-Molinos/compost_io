<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Form;
use App\View;

/** @var array<string, mixed> $site */
/** @var array{url:?string, username:?string, status:string, last_verified_at:?string, configured:bool} $connection */
/** @var array<string, string> $errors */
/** @var array{authors_active:int, authors_inactive:int, categories_total:int, categories_linked:int}|null $syncCounts */

$activeTab = 'wordpress';
require __DIR__ . '/../_tabs.php';

$statusMap = [
    'OK'         => ['Conectado', 'text-success border-success/40 bg-success/10'],
    'FAILED'     => ['Última verificação falhou', 'text-danger border-danger/40 bg-danger/10'],
    'UNVERIFIED' => ['Não verificado', 'text-text-secondary border-border bg-surface'],
];
[$statusLabel, $statusClass] = $statusMap[$connection['status']] ?? $statusMap['UNVERIFIED'];

$formData = [
    'wordpress_url' => $connection['url'] ?? '',
    'username'      => $connection['username'] ?? '',
];
?>
<h2 class="font-display text-lg font-semibold text-text-primary">Conexão WordPress</h2>
<p class="mt-1 text-sm text-text-secondary">
    Credencial usada para publicar os artigos deste site. Fica isolada por site e a senha é
    armazenada cifrada (nunca em texto puro).
</p>

<div class="mt-4 inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm <?= $statusClass ?>">
    <span><?= View::e($statusLabel) ?></span>
    <?php if ($connection['last_verified_at'] !== null): ?>
        <span class="text-text-muted">· <?= View::e($connection['last_verified_at']) ?></span>
    <?php endif; ?>
</div>

<form method="post" action="/sites/<?= View::e($site['id']) ?>/wordpress" class="mt-6 max-w-xl space-y-5" novalidate>
    <?= Csrf::field() ?>
    <?= Form::text('wordpress_url', 'URL do WordPress', $formData, $errors, required: true) ?>
    <p class="-mt-3 text-xs text-text-muted">Endereço da instalação, ex.: <code>https://gavsy.com</code>.</p>

    <?= Form::text('username', 'Usuário', $formData, $errors, required: true) ?>

    <?= Form::text('app_password', 'Application Password', [], $errors) ?>
    <p class="-mt-3 text-xs text-text-muted">
        Em <em>Usuários → Perfil → Application Passwords</em> no WordPress.
        <?php if ($connection['configured']): ?>
            Já existe uma credencial salva — deixe em branco para mantê-la.
        <?php else: ?>
            Os espaços podem ser colados; são ignorados.
        <?php endif; ?>
    </p>

    <div class="flex flex-wrap gap-3 pt-2">
        <button type="submit" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            Salvar
        </button>
        <a href="/sites/<?= View::e($site['id']) ?>" class="rounded-md border border-border px-4 py-2 text-sm text-text-secondary hover:border-border-strong hover:text-text-primary">
            Voltar
        </a>
    </div>
</form>

<?php if ($connection['configured']): ?>
    <div class="mt-8 flex flex-wrap items-center gap-3 border-t border-border pt-6">
        <form method="post" action="/sites/<?= View::e($site['id']) ?>/wordpress/test">
            <?= Csrf::field() ?>
            <button type="submit" class="rounded-md border border-cyan px-4 py-2 text-sm font-semibold text-cyan hover:bg-cyan/10">
                Testar conexão
            </button>
        </form>
        <form method="post" action="/sites/<?= View::e($site['id']) ?>/wordpress/delete"
              onsubmit="return confirm('Remover a credencial WordPress deste site?');">
            <?= Csrf::field() ?>
            <button type="submit" class="text-sm text-text-muted hover:text-danger">Remover credencial</button>
        </form>
    </div>
    <p class="mt-2 text-xs text-text-muted">
        O teste faz uma chamada real (<code>GET /wp-json/wp/v2/users/me</code>) com a credencial salva.
    </p>

    <div class="mt-8 border-t border-border pt-6">
        <h3 class="font-display text-base font-semibold text-text-primary">Autores e categorias</h3>
        <p class="mt-1 text-sm text-text-secondary">
            Na primeira conexão bem-sucedida, as categorias do WordPress são importadas
            automaticamente (casadas por nome com as que já existirem). Depois disso você
            gerencia as categorias na aba <a href="/sites/<?= View::e($site['id']) ?>/categories"
            class="text-cyan hover:text-cyan-bright">Categorias</a> — excluir e adicionar é manual.
            Os autores são um espelho do WordPress; ressincronize quando mudarem lá.
        </p>

        <?php if ($syncCounts !== null): ?>
            <dl class="mt-4 grid grid-cols-1 gap-px overflow-hidden rounded-lg border border-border bg-border sm:grid-cols-2">
                <div class="bg-surface p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Autores</dt>
                    <dd class="mt-1 text-sm text-text-primary">
                        <?= View::e($syncCounts['authors_active']) ?> ativo(s)<?php
                        if ($syncCounts['authors_inactive'] > 0): ?>,
                        <span class="text-text-muted"><?= View::e($syncCounts['authors_inactive']) ?> inativo(s)</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="bg-surface p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Categorias vinculadas ao WP</dt>
                    <dd class="mt-1 text-sm text-text-primary">
                        <?= View::e($syncCounts['categories_linked']) ?> de <?= View::e($syncCounts['categories_total']) ?>
                    </dd>
                </div>
            </dl>
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap gap-3">
            <form method="post" action="/sites/<?= View::e($site['id']) ?>/wordpress/sync-authors">
                <?= Csrf::field() ?>
                <button type="submit" class="rounded-md border border-border px-4 py-2 text-sm text-text-secondary hover:border-border-strong hover:text-text-primary">
                    Sincronizar autores
                </button>
            </form>
            <form method="post" action="/sites/<?= View::e($site['id']) ?>/wordpress/sync-categories">
                <?= Csrf::field() ?>
                <button type="submit" class="rounded-md border border-border px-4 py-2 text-sm text-text-secondary hover:border-border-strong hover:text-text-primary">
                    Sincronizar categorias agora
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

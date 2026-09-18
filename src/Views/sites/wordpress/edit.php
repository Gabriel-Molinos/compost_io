<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Form;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string, mixed> $site */
/** @var array{url:?string, username:?string, status:string, last_verified_at:?string, configured:bool} $connection */
/** @var array<string, string> $errors */
/** @var array{authors_active:int, authors_inactive:int, categories_total:int, categories_linked:int}|null $syncCounts */

$activeTab = 'wordpress';
require __DIR__ . '/../_tabs.php';

$statusMap = [
    'OK'         => ['Conectado', 'success'],
    'FAILED'     => ['Última verificação falhou', 'danger'],
    'UNVERIFIED' => ['Não verificado', 'muted'],
];
[$statusLabel, $statusTone] = $statusMap[$connection['status']] ?? $statusMap['UNVERIFIED'];

$formData = [
    'wordpress_url' => $connection['url'] ?? '',
    'username'      => $connection['username'] ?? '',
];
?>
<div class="flex items-start gap-3">
    <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('wordpress') ?></span>
    <div>
        <h1 class="font-display text-2xl font-bold text-text-primary">WordPress</h1>
        <p class="mt-1 max-w-xl text-sm text-text-secondary">
            Credencial usada para publicar os artigos deste site. Fica isolada por site e a senha é
            armazenada cifrada (nunca em texto puro).
        </p>
    </div>
</div>

<section class="mt-6 rounded-lg border border-border bg-surface p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-display text-base font-semibold text-text-primary">Credenciais</h2>
        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium <?= Labels::toneClasses($statusTone) ?>">
            <span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full bg-current"></span>
            <?= View::e($statusLabel) ?>
            <?php if ($connection['last_verified_at'] !== null): ?>
                <span class="font-normal opacity-75">· <?= View::e($connection['last_verified_at']) ?></span>
            <?php endif; ?>
        </span>
    </div>

    <form method="post" action="/sites/<?= View::e($site['id']) ?>/wordpress" class="mt-4 max-w-xl space-y-5" novalidate>
        <?= Csrf::field() ?>
        <div>
            <?= Form::text('wordpress_url', 'URL do WordPress', $formData, $errors, required: true) ?>
            <p class="mt-1 text-xs text-text-muted">Endereço da instalação, ex.: <code>https://gavsy.com</code>.</p>
        </div>

        <?= Form::text('username', 'Usuário', $formData, $errors, required: true) ?>

        <div>
            <?= Form::text('app_password', 'Application Password', [], $errors) ?>
            <p class="mt-1 text-xs text-text-muted">
                Em <em>Usuários → Perfil → Application Passwords</em> no WordPress.
                <?php if ($connection['configured']): ?>
                    Já existe uma credencial salva — deixe em branco para mantê-la.
                <?php else: ?>
                    Os espaços podem ser colados; são ignorados.
                <?php endif; ?>
            </p>
        </div>

        <div class="flex flex-wrap gap-3 pt-1">
            <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
                Salvar
            </button>
            <a href="/sites/<?= View::e($site['id']) ?>" class="btn btn-secondary px-4 py-2 text-sm">
                Voltar
            </a>
        </div>
    </form>

    <?php if ($connection['configured']): ?>
        <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-border pt-5">
            <form method="post" action="/sites/<?= View::e($site['id']) ?>/wordpress/test">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
                    Testar conexão
                </button>
            </form>
            <form method="post" action="/sites/<?= View::e($site['id']) ?>/wordpress/delete"
                  data-confirm="Remover a credencial WordPress deste site?">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-hover-danger px-4 py-2 text-sm">
                    Remover credencial
                </button>
            </form>
            <p class="text-xs text-text-muted">Testar faz uma chamada real (<code>GET /wp-json/wp/v2/users/me</code>).</p>
        </div>
    <?php endif; ?>
</section>

<?php if ($connection['configured']): ?>
    <section class="mt-6 rounded-lg border border-border bg-surface p-5">
        <h2 class="font-display text-base font-semibold text-text-primary">Autores e categorias</h2>
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
                    <dd class="mt-1 font-mono text-2xl font-semibold text-text-primary">
                        <?= View::e($syncCounts['authors_active']) ?>
                        <span class="text-sm font-sans font-normal text-text-muted">ativo(s)<?php
                            if ($syncCounts['authors_inactive'] > 0): ?>, <?= View::e($syncCounts['authors_inactive']) ?> inativo(s)<?php endif; ?></span>
                    </dd>
                </div>
                <div class="bg-surface p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Categorias vinculadas ao WP</dt>
                    <dd class="mt-1 font-mono text-2xl font-semibold text-text-primary">
                        <?= View::e($syncCounts['categories_linked']) ?>
                        <span class="text-sm font-sans font-normal text-text-muted">de <?= View::e($syncCounts['categories_total']) ?></span>
                    </dd>
                </div>
            </dl>
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap gap-3">
            <form method="post" action="/sites/<?= View::e($site['id']) ?>/wordpress/sync-authors">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-secondary px-4 py-2 text-sm">
                    Sincronizar autores
                </button>
            </form>
            <form method="post" action="/sites/<?= View::e($site['id']) ?>/wordpress/sync-categories">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-secondary px-4 py-2 text-sm">
                    Sincronizar categorias agora
                </button>
            </form>
        </div>
    </section>
<?php endif; ?>

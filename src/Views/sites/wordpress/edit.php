<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Form;
use App\View;

/** @var array<string, mixed> $site */
/** @var array{url:?string, username:?string, status:string, last_verified_at:?string, configured:bool} $connection */
/** @var array<string, string> $errors */

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
<h2 class="text-lg font-semibold text-text-primary">Conexão WordPress</h2>
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
        <button type="submit" class="rounded-md bg-cyan px-4 py-2 font-semibold text-[#050B0F] hover:bg-cyan-light">
            Salvar
        </button>
        <a href="/sites/<?= View::e($site['id']) ?>" class="rounded-md border border-border px-4 py-2 text-text-secondary hover:text-text-primary">
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
<?php endif; ?>

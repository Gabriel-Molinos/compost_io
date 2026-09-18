<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Csrf;
use App\Support\Form;
use App\Support\Icon;
use App\View;

/** @var array<string, mixed> $user */
/** @var string $action */
/** @var array<string, string> $errors */
/** @var list<array<string, mixed>> $sites */
/** @var list<int> $assignedIds */
/** @var bool $requirePass */

$isEdit = !empty($user['id']);
$role = $user['role'] ?? 'REDATOR_CHEFE';
$isAdmin = $role === 'ADMIN';
$name = trim((string) ($user['name'] ?? ''));
// Re-render de um POST com erro: checkbox desmarcado não vem no POST, então
// "chave ausente" quer dizer desmarcado — só na primeira abertura vale o padrão (ativo).
$isActive = $errors !== [] ? !empty($user['is_active']) : (array_key_exists('is_active', $user) ? !empty($user['is_active']) : true);
$totalSites = count($sites);
$linkedCount = count(array_filter($sites, static fn (array $s): bool => in_array((int) $s['id'], $assignedIds, true)));
$imgSize = 'h-36 w-36';

// Tags (papel e situação) — todas as variantes ficam no DOM e o script só
// alterna `hidden`, então o servidor já entrega a certa e ela muda ao vivo.
$tagBase = 'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider';
$roleTags = [
    'ADMIN'         => ['Administrador', 'shield', 'border-cyan/40 bg-cyan/15 text-cyan'],
    'REDATOR_CHEFE' => ['Redator-Chefe', 'production', 'border-border-strong bg-white/5 text-text-secondary'],
];
$activeTags = [
    '1' => ['Ativo', 'border-success/40 bg-success/15 text-success', 'status-dot bg-current'],
    '0' => ['Inativo', 'border-border-strong bg-white/5 text-text-muted', 'bg-current'],
];
$roleCards = [
    'REDATOR_CHEFE' => ['Redator-Chefe', 'Vê e produz só nos sites vinculados a ele.', 'production'],
    'ADMIN'         => ['Administrador', 'Acesso total: todos os sites, usuários e configurações.', 'shield'],
];
?>
<a href="/users" class="text-sm text-text-secondary hover:text-text-primary">← Usuários</a>

<?php if ($errors !== []): ?>
    <p role="alert" class="mt-4 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        Corrija os campos destacados abaixo.
    </p>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" enctype="multipart/form-data" class="mt-4 max-w-5xl" data-user-form novalidate>
    <?= Csrf::field() ?>

    <?php // ── Cabeçalho: foto grande + nome ao lado + tags de papel e situação ── ?>
    <section data-avatar-scope class="rounded-xl border border-border bg-surface p-6">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
            <span data-avatar-preview data-avatar-img-class="<?= View::e(Avatar::imgClass($imgSize, 'rounded-full')) ?>"
                  class="block shrink-0 self-start rounded-full p-1 ring-2 ring-cyan/40 shadow-[0_0_36px_-6px_rgba(0,208,240,.55)]">
                <?= Avatar::html($user['avatar_path'] ?? null, $name !== '' ? $name : '?', size: $imgSize, radius: 'rounded-full', textSize: 'text-5xl') ?>
            </span>

            <div class="min-w-0 flex-1">
                <?php if ($isEdit): ?>
                    <p class="text-xs font-semibold uppercase tracking-widest text-text-muted">Editar usuário</p>
                    <h1 class="mt-1 break-words font-display text-3xl font-bold text-text-primary"><?= View::e($name) ?></h1>
                    <p class="mt-1 break-all font-mono text-sm text-text-secondary"><?= View::e($user['email'] ?? '') ?></p>
                    <p class="mt-1 text-xs text-text-muted">Só o próprio usuário troca o nome, em "Meu perfil".</p>
                <?php else: ?>
                    <h1 class="text-xs font-semibold uppercase tracking-widest text-text-muted">Novo usuário</h1>
                    <label for="f_name" class="sr-only">Nome <span aria-hidden="true">*</span></label>
                    <input type="text" id="f_name" name="name" value="<?= View::e($user['name'] ?? '') ?>" required data-name-input
                           placeholder="Nome da pessoa"
                           <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="f_name_err"' : '' ?>
                           class="mt-2 w-full rounded-lg border bg-surface-2 px-4 py-2.5 font-display text-2xl font-bold text-text-primary placeholder:text-text-muted focus:outline-none <?= isset($errors['name']) ? 'border-danger' : 'border-border focus:border-cyan' ?>">
                    <?php if (isset($errors['name'])): ?>
                        <p id="f_name_err" class="mt-1 text-sm text-danger"><?= View::e($errors['name']) ?></p>
                    <?php endif; ?>
                    <p class="mt-1 text-xs text-text-muted">Cadastre um administrador ou redator-chefe e vincule aos sites certos.</p>
                <?php endif; ?>

                <div class="mt-4 flex flex-wrap items-center gap-2" aria-label="Papel e situação">
                    <?php foreach ($roleTags as $key => [$label, $icon, $tone]): ?>
                        <span data-role-tag="<?= $key ?>" class="<?= $tagBase ?> <?= $tone ?> <?= $role === $key ? '' : 'hidden' ?>">
                            <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav($icon) ?></span>
                            <?= View::e($label) ?>
                        </span>
                    <?php endforeach; ?>
                    <?php foreach ($activeTags as $key => [$label, $tone, $dot]): ?>
                        <span data-active-tag="<?= $key ?>" class="<?= $tagBase ?> <?= $tone ?> <?= ($isActive ? '1' : '0') === $key ? '' : 'hidden' ?>">
                            <span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full <?= $dot ?>"></span>
                            <?= View::e($label) ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <p class="mt-3 text-sm text-text-secondary" data-access-summary aria-live="polite"
                   data-total="<?= $totalSites ?>">
                    <?php if ($isAdmin): ?>
                        Acessa <strong class="text-text-primary">todos os <?= $totalSites ?> sites</strong>
                    <?php else: ?>
                        Vinculado a <strong class="text-text-primary"><?= $linkedCount ?> de <?= $totalSites ?> sites</strong>
                    <?php endif; ?>
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <input type="file" id="f_avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif"
                           class="peer sr-only" <?= isset($errors['avatar']) ? 'aria-invalid="true" aria-describedby="f_avatar_err"' : '' ?>>
                    <label for="f_avatar"
                           class="btn btn-secondary cursor-pointer px-3 py-1.5 text-xs peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-cyan">
                        <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('camera') ?></span>
                        <span data-avatar-filename><?= $isEdit ? 'Trocar foto' : 'Escolher foto' ?></span>
                    </label>
                    <?php if ($isEdit && !empty($user['avatar_path'])): ?>
                        <label class="flex items-center gap-2 text-xs text-text-secondary">
                            <input type="checkbox" name="remove_avatar" value="1"
                                   class="h-4 w-4 rounded border-border bg-surface-2 text-cyan focus:ring-cyan">
                            Remover foto atual
                        </label>
                    <?php endif; ?>
                    <span class="text-xs text-text-muted">JPG, PNG, WebP ou GIF, até 5 MB.</span>
                </div>
                <?php if (isset($errors['avatar'])): ?>
                    <p id="f_avatar_err" class="mt-1 text-sm text-danger"><?= View::e($errors['avatar']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        <?php // ── Acesso: e-mail, perfil (papel), senha, situação ── ?>
        <section class="rounded-xl border border-border bg-surface p-5 lg:col-span-2">
            <h2 class="font-display text-base font-semibold text-text-primary">Acesso</h2>

            <div class="mt-4">
                <?= Form::text('email', 'E-mail', $user, $errors, type: 'email', required: true) ?>
            </div>

            <fieldset class="mt-5">
                <legend class="block text-sm font-medium text-text-secondary">
                    Perfil <span class="text-danger" aria-hidden="true">*</span>
                </legend>
                <div class="mt-2 grid gap-2.5">
                    <?php foreach ($roleCards as $key => [$label, $desc, $icon]): ?>
                        <label class="relative block cursor-pointer">
                            <input type="radio" name="role" value="<?= $key ?>" data-role-radio class="peer sr-only"
                                   <?= $role === $key ? 'checked' : '' ?>
                                   <?= isset($errors['role']) ? 'aria-invalid="true" aria-describedby="f_role_err"' : '' ?>>
                            <span class="pick flex items-start gap-3 rounded-lg p-3 pr-10">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav($icon) ?></span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-text-primary"><?= View::e($label) ?></span>
                                    <span class="mt-0.5 block text-xs text-text-secondary"><?= View::e($desc) ?></span>
                                </span>
                            </span>
                            <span aria-hidden="true" class="pointer-events-none absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full border border-border-strong text-transparent transition-colors peer-checked:border-cyan peer-checked:bg-cyan peer-checked:text-void">
                                <span class="[&>svg]:h-3 [&>svg]:w-3"><?= Icon::nav('check') ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['role'])): ?>
                    <p id="f_role_err" class="mt-2 text-sm text-danger"><?= View::e($errors['role']) ?></p>
                <?php endif; ?>
            </fieldset>

            <div class="mt-5">
                <?= Form::text('password', $requirePass ? 'Senha' : 'Nova senha (deixe em branco para manter)', [], $errors, type: 'password', required: $requirePass) ?>
            </div>

            <label class="group/act relative mt-5 block cursor-pointer">
                <input type="checkbox" name="is_active" value="1" data-active-toggle class="peer sr-only" <?= $isActive ? 'checked' : '' ?>>
                <span class="pick pick-success flex items-center justify-between gap-4 rounded-lg p-3">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-text-primary">Usuário ativo</span>
                        <span class="mt-0.5 block text-xs text-text-secondary">Inativo não consegue entrar no COMPOST.</span>
                    </span>
                    <span aria-hidden="true"
                          class="relative h-6 w-11 shrink-0 rounded-full bg-border-strong transition-colors group-has-[input:checked]/act:bg-success after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform group-has-[input:checked]/act:after:translate-x-5"></span>
                </span>
            </label>
        </section>

        <?php // ── Sites vinculados (Redator-Chefe) / aviso de acesso total (Administrador) ── ?>
        <div class="lg:col-span-3">
            <fieldset data-sites-fieldset class="rounded-xl border border-border bg-surface p-5 <?= $isAdmin ? 'hidden' : '' ?>">
                <legend class="sr-only">Sites vinculados</legend>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-display text-base font-semibold text-text-primary">Sites vinculados</h2>
                        <p class="mt-1 text-sm text-text-secondary">Redator-Chefe só vê e produz nos sites marcados.</p>
                    </div>
                    <span class="rounded-full border border-cyan/40 bg-cyan/10 px-3 py-1 font-mono text-xs font-semibold text-cyan" aria-live="polite">
                        <span data-sites-count><?= $linkedCount ?></span> / <?= $totalSites ?>
                    </span>
                </div>

                <?php if ($sites === []): ?>
                    <p class="mt-4 text-sm text-text-muted">Nenhum site cadastrado. Crie um site antes de vincular.</p>
                <?php else: ?>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <div class="relative min-w-[12rem] flex-1">
                            <span aria-hidden="true" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-muted [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('search') ?></span>
                            <label for="f_site_filter" class="sr-only">Filtrar sites</label>
                            <input type="search" id="f_site_filter" data-sites-filter autocomplete="off" placeholder="Filtrar sites…"
                                   class="w-full rounded-md border border-border bg-surface-2 py-1.5 pl-9 pr-3 text-sm text-text-primary placeholder:text-text-muted focus:border-cyan focus:outline-none">
                        </div>
                        <button type="button" data-sites-all class="btn btn-secondary px-3 py-1.5 text-xs">Marcar todos</button>
                        <button type="button" data-sites-none class="btn btn-secondary px-3 py-1.5 text-xs">Limpar</button>
                    </div>

                    <div data-sites-grid class="mt-3 grid max-h-[30rem] gap-2.5 overflow-y-auto p-1 sm:grid-cols-2">
                        <?php foreach ($sites as $site): ?>
                            <?php $siteActive = (int) ($site['is_active'] ?? 1) === 1; ?>
                            <label data-site-item data-site-name="<?= View::e(mb_strtolower((string) $site['name'])) ?>" class="relative block cursor-pointer">
                                <input type="checkbox" name="site_ids[]" value="<?= View::e($site['id']) ?>" class="peer sr-only"
                                       <?= in_array((int) $site['id'], $assignedIds, true) ? 'checked' : '' ?>>
                                <span class="pick flex items-center gap-3 rounded-lg p-3 pr-11">
                                    <?= Avatar::html($site['logo_path'] ?? null, (string) $site['name'], size: 'h-11 w-11', radius: 'rounded-md', textSize: 'text-base', fit: 'contain', bg: 'bg-white') ?>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-text-primary"><?= View::e($site['name']) ?></span>
                                        <span class="mt-0.5 flex items-center gap-1.5 text-xs text-text-muted">
                                            <span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full <?= $siteActive ? 'bg-success' : 'bg-text-muted' ?>"></span>
                                            <span class="truncate"><?= View::e(($site['niche'] ?? '') !== '' ? $site['niche'] : 'Sem nicho') ?></span>
                                            <?php if (!$siteActive): ?><span class="shrink-0">· inativo</span><?php endif; ?>
                                        </span>
                                    </span>
                                </span>
                                <span aria-hidden="true" class="pointer-events-none absolute right-4 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full border border-border-strong text-transparent transition-colors peer-checked:border-cyan peer-checked:bg-cyan peer-checked:text-void">
                                    <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav('check') ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p data-sites-empty class="mt-3 hidden text-sm text-text-muted">Nenhum site com esse nome.</p>
                <?php endif; ?>
            </fieldset>

            <div data-admin-note class="rounded-xl border border-cyan/30 bg-cyan/5 p-5 <?= $isAdmin ? '' : 'hidden' ?>">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('shield') ?></span>
                    <div>
                        <h2 class="font-display text-base font-semibold text-text-primary">Acesso a todos os sites</h2>
                        <p class="mt-1 text-sm text-text-secondary">
                            Administrador enxerga e produz em qualquer site — não precisa vincular nenhum.
                            Pra restringir a sites específicos, mude o perfil pra Redator-Chefe.
                        </p>
                    </div>
                </div>
                <?php if ($sites !== []): ?>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <?php foreach (array_slice($sites, 0, 12) as $site): ?>
                            <span title="<?= View::e($site['name']) ?>">
                                <?= Avatar::html($site['logo_path'] ?? null, (string) $site['name'], size: 'h-9 w-9', radius: 'rounded-md', textSize: 'text-sm', fit: 'contain', bg: 'bg-white') ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if ($totalSites > 12): ?>
                            <span class="font-mono text-xs text-text-secondary">+<?= $totalSites - 12 ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mt-6 flex gap-3">
        <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
            <?= $isEdit ? 'Salvar' : 'Criar usuário' ?>
        </button>
        <a href="/users" class="btn btn-secondary px-4 py-2 text-sm">
            Cancelar
        </a>
    </div>
</form>

<script>
    // Melhoria progressiva: sem JS o formulário continua completo (envia os mesmos
    // campos) — o script só deixa a tela responder ao vivo: tags de papel/situação,
    // resumo de acesso, contador de sites, filtro/"marcar todos" e a letra do avatar.
    (function () {
        var form = document.querySelector('[data-user-form]');
        if (!form) return;

        var tagsRole = form.querySelectorAll('[data-role-tag]');
        var tagsActive = form.querySelectorAll('[data-active-tag]');
        var sitesBox = form.querySelector('[data-sites-fieldset]');
        var adminNote = form.querySelector('[data-admin-note]');
        var summary = form.querySelector('[data-access-summary]');
        var counter = form.querySelector('[data-sites-count]');
        var toggle = form.querySelector('[data-active-toggle]');
        var siteBoxes = form.querySelectorAll('[data-site-item] input[type="checkbox"]');
        var total = summary ? parseInt(summary.getAttribute('data-total'), 10) || 0 : 0;

        function currentRole() {
            var checked = form.querySelector('[data-role-radio]:checked');
            return checked ? checked.value : 'REDATOR_CHEFE';
        }

        function linked() {
            var n = 0;
            siteBoxes.forEach(function (box) { if (box.checked) n += 1; });
            return n;
        }

        function sync() {
            var role = currentRole();
            var isAdmin = role === 'ADMIN';
            if (sitesBox) sitesBox.classList.toggle('hidden', isAdmin);
            if (adminNote) adminNote.classList.toggle('hidden', !isAdmin);
            tagsRole.forEach(function (el) { el.classList.toggle('hidden', el.getAttribute('data-role-tag') !== role); });
            var active = toggle ? toggle.checked : true;
            tagsActive.forEach(function (el) { el.classList.toggle('hidden', el.getAttribute('data-active-tag') !== (active ? '1' : '0')); });
            var n = linked();
            if (counter) counter.textContent = String(n);
            if (summary) {
                summary.innerHTML = isAdmin
                    ? 'Acessa <strong class="text-text-primary">todos os ' + total + ' sites</strong>'
                    : 'Vinculado a <strong class="text-text-primary">' + n + ' de ' + total + ' sites</strong>';
            }
        }

        form.querySelectorAll('[data-role-radio]').forEach(function (r) { r.addEventListener('change', sync); });
        if (toggle) toggle.addEventListener('change', sync);
        siteBoxes.forEach(function (box) { box.addEventListener('change', sync); });

        // Filtro e ações em massa valem só pros sites visíveis (mesma ideia de "marcar os que estou vendo").
        var items = form.querySelectorAll('[data-site-item]');
        var filter = form.querySelector('[data-sites-filter]');
        var empty = form.querySelector('[data-sites-empty]');
        function visibleItems() {
            return Array.prototype.filter.call(items, function (el) { return !el.classList.contains('hidden'); });
        }
        if (filter) {
            filter.addEventListener('input', function () {
                var q = filter.value.trim().toLowerCase();
                items.forEach(function (el) {
                    el.classList.toggle('hidden', q !== '' && el.getAttribute('data-site-name').indexOf(q) === -1);
                });
                if (empty) empty.classList.toggle('hidden', visibleItems().length > 0);
            });
            // Enter no filtro não pode enviar o formulário inteiro.
            filter.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
        }
        function setVisible(value) {
            visibleItems().forEach(function (el) { el.querySelector('input[type="checkbox"]').checked = value; });
            sync();
        }
        var all = form.querySelector('[data-sites-all]');
        var none = form.querySelector('[data-sites-none]');
        if (all) all.addEventListener('click', function () { setVisible(true); });
        if (none) none.addEventListener('click', function () { setVisible(false); });

        // Nome novo (criação): a inicial do avatar acompanha o que está sendo digitado.
        var nameInput = form.querySelector('[data-name-input]');
        var preview = form.querySelector('[data-avatar-preview]');
        if (nameInput && preview) {
            nameInput.addEventListener('input', function () {
                var chip = preview.querySelector('span[aria-hidden="true"]');
                if (!chip) return; // já tem foto escolhida — não mexe
                var first = nameInput.value.trim().charAt(0);
                chip.textContent = first !== '' ? first.toUpperCase() : '?';
            });
        }

        // Nome do arquivo escolhido no botão de foto (a pré-visualização é do avatar-preview.js).
        var file = form.querySelector('input[type="file"]');
        var fileLabel = form.querySelector('[data-avatar-filename]');
        if (file && fileLabel) {
            var original = fileLabel.textContent;
            file.addEventListener('change', function () {
                fileLabel.textContent = file.files && file.files[0] ? file.files[0].name : original;
            });
        }

        sync();
    })();
</script>

<?php

declare(strict_types=1);

use App\Services\AuthService;
use App\Support\Avatar;
use App\Support\Csrf;
use App\Support\Flag;
use App\Support\Form;
use App\Support\Icon;
use App\Support\Languages;
use App\View;

/** @var array<string, mixed> $site */
/** @var string $action */
/** @var array<string, string> $errors */
/** @var list<array{domain: string, filename: string, url: string}> $logoLibrary */
/** @var array{url:?string, username:?string, status:string, last_verified_at:?string, configured:bool}|null $connection */
/** @var list<array<string, mixed>> $editors Redatores-Chefe cadastrados */
/** @var list<int> $assignedUserIds */

$isEdit = !empty($site['id']);
$backHref = $isEdit ? '/sites/' . $site['id'] : '/sites';
$siteName = trim((string) ($site['name'] ?? ''));
// Re-render de POST com erro: checkbox desmarcado não vem no POST ("ausente" = desmarcado);
// só na primeira abertura vale o padrão (ativo).
$isActive = $errors !== [] ? !empty($site['is_active']) : (array_key_exists('is_active', $site) ? !empty($site['is_active']) : true);

if ($isEdit) {
    $activeTab = 'config';
    require __DIR__ . '/_tabs.php';
}

// ── Logo ──────────────────────────────────────────────────────────────────
$logoPath = $site['logo_path'] ?? null;
$hasLogo = Avatar::hasImage($logoPath);
$currentLibrary = (string) ($site['logo_library_filename'] ?? '');
$logoSize = 'h-44 w-full';
// Estado "sem logo" — usado no HTML inicial e como <template> pro JS (remover logo).
$emptyLogo = '<span class="flex h-44 w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 text-slate-400">'
    . '<span class="[&>svg]:h-8 [&>svg]:w-8">' . Icon::nav('camera') . '</span>'
    . '<span class="text-sm font-semibold">Sem logo ainda</span>'
    . '<span class="text-xs">Escolha uma abaixo ou envie a sua</span></span>';

// ── Conexão WordPress: selo na placa + detalhe embaixo ────────────────────
$wp = $connection ?? null;
$wpState = $wp === null ? 'NEW'
    : ($wp['status'] === 'OK' ? 'OK'
        : ($wp['status'] === 'FAILED' ? 'FAILED'
            : ($wp['configured'] ? 'UNVERIFIED' : 'NONE')));
$wpTags = [
    'OK'         => ['WordPress conectado', 'check', 'bg-success text-[#04210F]'],
    'FAILED'     => ['Falha na conexão', 'alert', 'bg-danger text-[#2A0610]'],
    'UNVERIFIED' => ['Conexão não verificada', 'alert', 'bg-warning text-[#2B1D00]'],
    'NONE'       => ['WordPress não conectado', 'wordpress', 'bg-slate-500 text-white'],
    'NEW'        => ['WordPress: após criar', 'wordpress', 'bg-slate-500 text-white'],
];
[$wpLabel, $wpIcon, $wpTone] = $wpTags[$wpState];
$verifiedAt = ($wp['last_verified_at'] ?? null) !== null ? date('d/m/Y \à\s H:i', strtotime((string) $wp['last_verified_at'])) : null;

// Tag "Ativo/Inativo" da placa: só existe com o WordPress de fato conectado (pedido do
// responsável, 2026-09-18) — na criação, ou sem conexão, "Ativo" não diria nada verdadeiro.
$showActiveTag = $wpState === 'OK';

// ── Voz editorial ─────────────────────────────────────────────────────────
$language = trim((string) ($site['language'] ?? ''));
$langPresets = Languages::presets();
$langKey = Languages::presetFor($language);
// "Outro": idioma que não é nenhum preset — ou o campo "Outro" enviado em branco (re-render de erro).
$langOther = $langKey === null && ($language !== '' || array_key_exists('language_custom', $site));
$langCustom = $langOther ? ($language !== '' ? $language : (string) ($site['language_custom'] ?? '')) : '';
$splitTokens = static fn (string $v): array => array_values(array_filter(array_map('trim', explode(',', $v)), static fn (string $t): bool => $t !== ''));
$lowerTokens = static fn (string $v): array => array_map('mb_strtolower', $splitTokens($v));
$toneValue = (string) ($site['tone'] ?? '');
$audienceValue = (string) ($site['target_audience'] ?? '');
$identityValue = (string) ($site['editorial_identity'] ?? '');
$toneTokens = $lowerTokens($toneValue);
$audienceTokens = $lowerTokens($audienceValue);
$toneChips = ['Amigável', 'Profissional', 'Descontraído', 'Autoritativo', 'Didático', 'Inspirador', 'Técnico', 'Empático', 'Direto', 'Bem-humorado'];
$audienceChips = ['Iniciantes', 'Profissionais', 'Empresas (B2B)', 'Consumidores (B2C)', 'Estudantes', 'Público geral'];
$langLabel = $langKey !== null
    ? $langPresets[$langKey]['label'] . ($langKey === 'pt' ? ' (Brasil)' : '')
    : $langCustom;
$voiceDone = count(array_filter([$language !== '' || $langOther && $langCustom !== '', $toneValue !== '', $audienceValue !== '', trim($identityValue) !== '']));
// Pro resumo: sem ponto final (o texto do usuário já pode terminar em ponto) e cortado se muito longo.
$clip = static function (string $t, int $n): string {
    $t = rtrim(trim($t), ". ");

    return mb_strlen($t) > $n ? mb_substr($t, 0, $n - 1) . '…' : $t;
};

// ── Redatores ─────────────────────────────────────────────────────────────
$assignedCount = count(array_filter($editors, static fn (array $u): bool => in_array((int) $u['id'], $assignedUserIds, true)));
$editorsTotal = count($editors);

// Redator-Chefe agora edita as configurações do site (pedido do responsável,
// 2026-09-22) — mas só ADMIN cria/exclui site e decide QUEM é Redator-Chefe
// dele. As duas seções abaixo (Redatores vinculados, Zona de perigo) ficam
// escondidas pro Redator-Chefe — não é só visual: SiteController::update()
// também ignora `user_ids[]` de quem não é admin (essencial: se a seção
// sumisse do HTML mas o controller continuasse lendo o POST, um redator
// salvando o form desvincularia todo mundo do site sem querer, já que
// checkbox ausente = desmarcado).
$isAdminUser = AuthService::isAdmin();
?>
<a href="<?= View::e($backHref) ?>" class="text-sm text-text-secondary hover:text-text-primary">← <?= $isEdit ? View::e($site['name']) : 'Sites' ?></a>

<?php if ($errors !== []): ?>
    <p role="alert" class="mx-auto mt-4 max-w-3xl rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        Corrija os campos destacados abaixo.
    </p>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" enctype="multipart/form-data" class="mt-4" data-site-form novalidate>
    <?= Csrf::field() ?>

    <div data-avatar-scope>
        <h1 class="text-center text-xs font-semibold uppercase tracking-widest text-text-muted">
            <?= $isEdit ? 'Configuração do site' : 'Novo site' ?>
        </h1>

        <?php // ── Cabeçalho: placa branca (não ocupa a largura toda) com a logo escolhida ── ?>
        <div class="mx-auto mt-4 max-w-xl">
            <div data-swap-pulse class="relative rounded-3xl bg-white px-8 pb-10 pt-9 shadow-[0_24px_60px_-24px_rgba(0,208,240,.5)]">
                <span class="absolute left-5 top-4 text-[10px] font-bold uppercase tracking-[.2em] text-slate-400">Logo do site</span>

                <?php if ($showActiveTag): ?>
                <span class="absolute right-4 top-3.5 flex gap-1.5" aria-label="Situação do site">
                    <span data-active-tag="1" class="inline-flex items-center gap-1.5 rounded-md bg-emerald-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-700 <?= $isActive ? '' : 'hidden' ?>">
                        <span aria-hidden="true" class="status-dot h-1.5 w-1.5 rounded-full bg-current"></span>Ativo
                    </span>
                    <span data-active-tag="0" class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 <?= $isActive ? 'hidden' : '' ?>">
                        <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-current"></span>Inativo
                    </span>
                </span>
                <?php endif; ?>

                <span data-avatar-preview data-avatar-transition
                      data-avatar-img-class="<?= View::e(Avatar::imgClass($logoSize, 'rounded-xl', 'contain', 'bg-white')) ?>"
                      class="relative mt-2 block h-44 w-full">
                    <?php if ($hasLogo): ?>
                        <?= Avatar::html($logoPath, $siteName !== '' ? $siteName : '?', size: $logoSize, radius: 'rounded-xl', fit: 'contain', bg: 'bg-white') ?>
                    <?php else: ?>
                        <?= $emptyLogo ?>
                    <?php endif; ?>
                </span>

                <?php // Selo da conexão com o WordPress — meio pra fora da borda de baixo. ?>
                <span class="absolute -bottom-3.5 left-1/2 inline-flex -translate-x-1/2 items-center gap-1.5 whitespace-nowrap rounded-full px-3.5 py-1.5 text-[11px] font-bold uppercase tracking-wider shadow-[0_0_0_4px_#081720,0_8px_20px_-6px_rgba(0,0,0,.5)] <?= $wpTone ?>">
                    <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav($wpIcon) ?></span>
                    <?= View::e($wpLabel) ?>
                </span>
            </div>

            <p class="mt-6 text-center text-xs text-text-muted">
                <?php if ($wpState === 'OK'): ?>
                    Verificado em <?= View::e((string) $verifiedAt) ?><?= !empty($wp['username']) ? ' · usuário <span class="font-mono text-text-secondary">' . View::e($wp['username']) . '</span>' : '' ?>
                    · <a href="/sites/<?= View::e($site['id']) ?>/wordpress" class="text-cyan hover:text-cyan-bright">gerenciar conexão →</a>
                <?php elseif ($wpState === 'FAILED'): ?>
                    A última verificação falhou — a publicação neste site não vai funcionar até corrigir.
                    <a href="/sites/<?= View::e($site['id']) ?>/wordpress" class="text-cyan hover:text-cyan-bright">Revisar conexão →</a>
                <?php elseif ($wpState === 'UNVERIFIED'): ?>
                    Credencial salva, mas ainda não testada.
                    <a href="/sites/<?= View::e($site['id']) ?>/wordpress" class="text-cyan hover:text-cyan-bright">Testar conexão →</a>
                <?php elseif ($wpState === 'NONE'): ?>
                    Nenhuma credencial cadastrada.
                    <a href="/sites/<?= View::e($site['id']) ?>/wordpress" class="text-cyan hover:text-cyan-bright">Conectar o WordPress →</a>
                <?php else: ?>
                    A conexão com o WordPress é configurada na aba WordPress, depois que o site for criado.
                <?php endif; ?>
            </p>

            <div class="mt-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-2">
                <input type="file" id="f_logo" name="logo" accept="image/jpeg,image/png,image/webp,image/gif" class="peer sr-only"
                       <?= isset($errors['logo']) ? 'aria-invalid="true" aria-describedby="f_logo_err"' : '' ?>>
                <label for="f_logo"
                       class="btn btn-secondary cursor-pointer px-3 py-1.5 text-xs peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-cyan">
                    <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('camera') ?></span>
                    <span data-logo-filename>Enviar minha logo</span>
                </label>
                <button type="button" data-library-clear class="btn btn-secondary hidden px-3 py-1.5 text-xs">Voltar à logo atual</button>
                <?php if ($isEdit && $hasLogo): ?>
                    <label class="flex cursor-pointer items-center gap-2 text-xs text-text-secondary">
                        <input type="checkbox" name="remove_logo" value="1" class="h-4 w-4 rounded border-border bg-surface-2 text-cyan focus:ring-cyan">
                        Remover logo atual
                    </label>
                <?php endif; ?>
                <span class="text-xs text-text-muted">JPG, PNG, WebP ou GIF, até 5 MB.</span>
            </div>
            <?php if (isset($errors['logo'])): ?>
                <p id="f_logo_err" class="mt-2 text-center text-sm text-danger"><?= View::e($errors['logo']) ?></p>
            <?php endif; ?>
        </div>

        <template data-avatar-empty><?= $emptyLogo ?></template>

        <?php // ── Biblioteca de logos: clicar troca a logo da placa com transição ── ?>
        <?php if ($logoLibrary !== []): ?>
            <section class="mx-auto mt-8 max-w-3xl rounded-2xl border border-border bg-surface p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-base font-semibold text-text-primary">Biblioteca de logos</h2>
                        <p class="mt-1 text-xs text-text-muted">
                            <?= count($logoLibrary) ?> disponíveis — cada domínio tem uma logo só. O envio manual acima tem prioridade sobre a escolha daqui.
                        </p>
                    </div>
                    <div class="relative w-full min-w-[12rem] sm:w-56">
                        <span aria-hidden="true" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-muted [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('search') ?></span>
                        <label for="f_logo_filter" class="sr-only">Filtrar logos por domínio</label>
                        <input type="search" id="f_logo_filter" data-logo-filter autocomplete="off" placeholder="Filtrar por domínio…"
                               class="w-full rounded-md border border-border bg-surface-2 py-1.5 pl-9 pr-3 text-sm text-text-primary placeholder:text-text-muted focus:border-cyan focus:outline-none">
                    </div>
                </div>

                <div data-logo-grid class="mt-4 grid max-h-[22rem] grid-cols-3 gap-x-3 gap-y-4 overflow-y-auto p-2 sm:grid-cols-4 lg:grid-cols-6">
                    <?php foreach ($logoLibrary as $i => $l): ?>
                        <label data-logo-item data-logo-domain="<?= View::e(mb_strtolower($l['domain'])) ?>" class="group relative block cursor-pointer" title="<?= View::e($l['domain']) ?>">
                            <input type="radio" name="library_logo" value="<?= View::e($l['filename']) ?>" class="peer sr-only"
                                   <?= ($site['library_logo'] ?? '') === $l['filename'] ? 'checked' : '' ?>>
                            <span class="pick pick-light flex aspect-square items-center justify-center rounded-xl p-2">
                                <?php // Só as primeiras trazem src; o resto (data-src) carrega conforme a grade rola — são ~90 imagens de até 350 KB. ?>
                                <img <?= $i < 18 ? 'src' : 'data-src' ?>="<?= View::e($l['url']) ?>" alt="" class="h-full w-full object-contain">
                            </span>
                            <span aria-hidden="true" class="pointer-events-none absolute -right-1.5 -top-1.5 flex h-6 w-6 scale-0 items-center justify-center rounded-full bg-cyan text-void shadow transition-transform duration-300 ease-[cubic-bezier(.34,1.56,.64,1)] peer-checked:scale-100">
                                <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav('check') ?></span>
                            </span>
                            <?php if ($currentLibrary !== '' && $currentLibrary === $l['filename']): ?>
                                <span class="pointer-events-none absolute left-1 top-1 rounded bg-void/85 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-cyan">Atual</span>
                            <?php endif; ?>
                            <span class="mt-1.5 block truncate text-center text-[11px] text-text-muted transition-colors peer-checked:font-semibold peer-checked:text-cyan"><?= View::e($l['domain']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p data-logo-empty class="mt-3 hidden text-center text-sm text-text-muted">Nenhuma logo com esse domínio.</p>
            </section>
        <?php endif; ?>
    </div>

    <div class="mx-auto mt-8 max-w-3xl space-y-6">
        <?php // Sugestão automática ao conectar o WordPress (pedido do responsável 2026-09-28):
        // nicho/tom/público/identidade abaixo foram preenchidos pela IA a partir dos posts
        // publicados do site — some sozinho assim que o formulário for salvo (SiteService::update()
        // limpa a marca), confirmado ou não os valores tenham mudado. ?>
        <?php if (!empty($site['editorial_identity_suggested_at'])): ?>
            <div class="flex items-start gap-3 rounded-xl border border-cyan/25 bg-cyan/5 p-4">
                <span class="mt-0.5 shrink-0 text-cyan [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('ai') ?></span>
                <p class="text-sm leading-relaxed text-text-primary">
                    <strong class="font-semibold">Nicho, tom, público e identidade editorial abaixo foram sugeridos automaticamente</strong>
                    a partir dos posts publicados no WordPress conectado. Revise e ajuste o que quiser — salvar esta página confirma.
                </p>
            </div>
        <?php endif; ?>

        <?php // ── Identidade: nome, nicho e situação, com prévia de como o site aparece no COMPOST ── ?>
        <?php
        $nicheValue = trim((string) ($site['niche'] ?? ''));
        $nicheChips = ['Viagens', 'Finanças', 'Tecnologia', 'Saúde e bem-estar', 'Educação', 'Marketing', 'Negócios', 'Gastronomia'];
        $identityDone = count(array_filter([$siteName !== '', $nicheValue !== '']));
        ?>
        <section class="rounded-2xl border border-border bg-surface p-6" data-identity>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan/10 text-cyan"><?= Icon::nav('sites') ?></span>
                    <div>
                        <h2 class="font-display text-lg font-semibold text-text-primary">Identidade</h2>
                        <p class="mt-0.5 text-sm text-text-secondary">Como o site aparece no COMPOST e como a IA o enxerga.</p>
                    </div>
                </div>
                <div class="text-right" aria-live="polite">
                    <div class="flex justify-end gap-1" aria-hidden="true">
                        <?php for ($i = 0; $i < 2; $i++): ?>
                            <span data-identity-seg class="h-1.5 w-7 rounded-full transition-colors duration-300 <?= $i < $identityDone ? 'bg-cyan' : 'bg-border-strong' ?>"></span>
                        <?php endfor; ?>
                    </div>
                    <p class="mt-1.5 text-xs text-text-muted"><span data-identity-done><?= $identityDone ?></span> de 2 preenchidos</p>
                </div>
            </div>

            <?php // 1 — Nome ?>
            <div class="mt-7">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-cyan/15 font-mono text-xs font-bold text-cyan">1</span>
                    <label for="f_name" class="text-sm font-semibold text-text-primary">Nome do site <span class="text-danger" aria-hidden="true">*</span></label>
                    <span class="ml-auto font-mono text-xs text-text-muted"><span data-count-for="#f_name"><?= mb_strlen((string) ($site['name'] ?? '')) ?></span> / 191</span>
                </div>
                <input type="text" id="f_name" name="name" value="<?= View::e($site['name'] ?? '') ?>" required maxlength="191" placeholder="Ex.: Gavsy"
                       <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="f_name_err"' : '' ?>
                       class="mt-3 w-full rounded-xl border bg-surface-2 px-4 py-3 font-display text-2xl font-bold text-text-primary placeholder:text-text-muted focus:outline-none <?= isset($errors['name']) ? 'border-danger' : 'border-border focus:border-cyan' ?>">
                <?php if (isset($errors['name'])): ?>
                    <p id="f_name_err" class="mt-1 text-sm text-danger"><?= View::e($errors['name']) ?></p>
                <?php endif; ?>
            </div>

            <?php // 2 — Nicho ?>
            <div class="mt-8">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-cyan/15 font-mono text-xs font-bold text-cyan">2</span>
                    <label for="f_niche" class="text-sm font-semibold text-text-primary">Nicho</label>
                    <span class="ml-auto font-mono text-xs text-text-muted"><span data-count-for="#f_niche"><?= mb_strlen($nicheValue) ?></span> / 191</span>
                </div>
                <div class="relative mt-3">
                    <span aria-hidden="true" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-cyan [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('categories') ?></span>
                    <input type="text" id="f_niche" name="niche" value="<?= View::e($nicheValue) ?>" maxlength="191" placeholder="Ex.: Digital nomadism"
                           <?= isset($errors['niche']) ? 'aria-invalid="true" aria-describedby="f_niche_err"' : '' ?>
                           class="w-full rounded-xl border bg-surface-2 py-2.5 pl-10 pr-4 text-text-primary placeholder:text-text-muted focus:outline-none <?= isset($errors['niche']) ? 'border-danger' : 'border-border focus:border-cyan' ?>">
                </div>
                <?php if (isset($errors['niche'])): ?>
                    <p id="f_niche_err" class="mt-1 text-sm text-danger"><?= View::e($errors['niche']) ?></p>
                <?php endif; ?>
                <div class="mt-3 flex flex-wrap gap-2" data-niche-chips role="group" aria-label="Sugestões de nicho">
                    <?php foreach ($nicheChips as $chip): ?>
                        <button type="button" data-niche-chip="<?= View::e($chip) ?>" aria-pressed="<?= mb_strtolower($nicheValue) === mb_strtolower($chip) ? 'true' : 'false' ?>"
                                class="pick pick-chip">
                            <?= View::e($chip) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php // 3 — Situação: interruptor em cartão, acende em verde ?>
            <div class="mt-8">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-cyan/15 font-mono text-xs font-bold text-cyan">3</span>
                    <h3 class="text-sm font-semibold text-text-primary">Situação</h3>
                </div>
                <label class="group/act relative mt-3 block cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" data-active-toggle class="peer sr-only" <?= $isActive ? 'checked' : '' ?>>
                    <span class="pick pick-success flex items-center gap-4 rounded-xl p-4">
                        <span aria-hidden="true" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-white/5 text-text-muted transition-colors group-has-[input:checked]/act:bg-success/15 group-has-[input:checked]/act:text-success [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('production') ?></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-text-primary">
                                <span class="group-has-[input:checked]/act:hidden">Site inativo</span>
                                <span class="hidden group-has-[input:checked]/act:inline">Site ativo</span>
                            </span>
                            <span class="mt-0.5 block text-xs text-text-secondary">
                                <span class="group-has-[input:checked]/act:hidden">Parado: não gera rascunhos novos até você ativar.</span>
                                <span class="hidden group-has-[input:checked]/act:inline">Gera 1 rascunho novo por dia automaticamente.</span>
                            </span>
                        </span>
                        <span aria-hidden="true"
                              class="relative h-6 w-11 shrink-0 rounded-full bg-border-strong transition-colors group-has-[input:checked]/act:bg-success after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform group-has-[input:checked]/act:after:translate-x-5"></span>
                    </span>
                </label>
            </div>
        </section>

        <?php // ── Redatores-Chefe vinculados — só ADMIN decide quem tem acesso a qual site ── ?>
        <?php if ($isAdminUser): ?>
        <section class="rounded-2xl border border-border bg-surface p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan/10 text-cyan"><?= Icon::nav('users') ?></span>
                    <div>
                        <h2 class="font-display text-lg font-semibold text-text-primary">Redatores</h2>
                        <p class="mt-0.5 text-sm text-text-secondary">Quem vê e produz neste site. Administradores acessam todos os sites, sem vínculo.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3" aria-live="polite">
                    <span data-editors-stack class="flex -space-x-2">
                        <?php foreach ($editors as $u): ?>
                            <span data-editor-avatar="<?= View::e($u['id']) ?>" title="<?= View::e($u['name']) ?>"
                                  class="rounded-full ring-2 ring-surface <?= in_array((int) $u['id'], $assignedUserIds, true) ? '' : 'hidden' ?>">
                                <?= Avatar::html($u['avatar_path'] ?? null, (string) $u['name'], size: 'h-8 w-8', radius: 'rounded-full', textSize: 'text-xs') ?>
                            </span>
                        <?php endforeach; ?>
                    </span>
                    <span class="rounded-full border border-cyan/40 bg-cyan/10 px-3 py-1 font-mono text-xs font-semibold text-cyan">
                        <span data-editors-count><?= $assignedCount ?></span> / <?= $editorsTotal ?>
                    </span>
                </div>
            </div>

            <?php if ($editors === []): ?>
                <p class="mt-4 text-sm text-text-muted">
                    Nenhum Redator-Chefe cadastrado ainda. <a href="/users/new" class="text-cyan hover:text-cyan-bright">Criar um usuário →</a>
                </p>
            <?php else: ?>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" data-editors-all class="btn btn-secondary px-3 py-1.5 text-xs">Marcar todos</button>
                    <button type="button" data-editors-none class="btn btn-secondary px-3 py-1.5 text-xs">Limpar</button>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <?php foreach ($editors as $u): ?>
                        <?php $userActive = (int) ($u['is_active'] ?? 1) === 1; ?>
                        <label class="relative block cursor-pointer">
                            <input type="checkbox" name="user_ids[]" value="<?= View::e($u['id']) ?>" data-editor-box class="peer sr-only"
                                   <?= in_array((int) $u['id'], $assignedUserIds, true) ? 'checked' : '' ?>>
                            <span class="pick flex items-center gap-3 rounded-xl p-3 pr-12">
                                <?= Avatar::html($u['avatar_path'] ?? null, (string) $u['name'], size: 'h-12 w-12', radius: 'rounded-full', textSize: 'text-lg') ?>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-text-primary"><?= View::e($u['name']) ?></span>
                                    <span class="block truncate font-mono text-[11px] text-text-muted"><?= View::e($u['email']) ?></span>
                                    <?php if (!$userActive): ?>
                                        <span class="mt-1 inline-block rounded bg-white/5 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-text-muted">Inativo</span>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <span aria-hidden="true" class="pointer-events-none absolute right-4 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full border border-border-strong text-transparent transition-colors peer-checked:border-cyan peer-checked:bg-cyan peer-checked:text-void">
                                <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav('check') ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php // ── Voz editorial: idioma (bandeiras), tom, público e identidade, com resumo ao vivo ── ?>
        <section class="rounded-2xl border border-border bg-surface p-6" data-voice>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan/10 text-cyan"><?= Icon::nav('intelligence') ?></span>
                    <div>
                        <h2 class="font-display text-lg font-semibold text-text-primary">Voz editorial</h2>
                        <p class="mt-0.5 text-sm text-text-secondary">Orienta a IA em toda geração — quanto mais específico, mais os artigos soam com a cara do site.</p>
                    </div>
                </div>
                <div class="text-right" aria-live="polite">
                    <div class="flex justify-end gap-1" aria-hidden="true">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                            <span data-voice-seg class="h-1.5 w-7 rounded-full transition-colors duration-300 <?= $i < $voiceDone ? 'bg-cyan' : 'bg-border-strong' ?>"></span>
                        <?php endfor; ?>
                    </div>
                    <p class="mt-1.5 text-xs text-text-muted"><span data-voice-done><?= $voiceDone ?></span> de 4 preenchidos</p>
                </div>
            </div>

            <?php // Resumo: mostra, em uma frase, o que a IA vai receber (o servidor já entrega; o script atualiza ao vivo). ?>
            <div class="mt-5 flex items-start gap-3 rounded-xl border border-cyan/25 bg-cyan/5 p-4">
                <span class="mt-0.5 shrink-0 text-cyan [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('intelligence') ?></span>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-cyan">Como a IA vai escrever</p>
                    <p data-voice-summary class="mt-1 break-words text-sm leading-relaxed text-text-primary">Escreve em <strong class="text-cyan-bright"><?= View::e($langLabel !== '' ? $langLabel : '…') ?></strong><?php if ($toneValue !== ''): ?>, com tom <strong class="text-cyan-bright"><?= View::e($clip($toneValue, 80)) ?></strong><?php endif; ?><?php if ($audienceValue !== ''): ?>, para <strong class="text-cyan-bright"><?= View::e($clip($audienceValue, 80)) ?></strong><?php endif; ?>.</p>
                </div>
            </div>

            <?php // 1 — Idioma ?>
            <div class="mt-7">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-cyan/15 font-mono text-xs font-bold text-cyan">1</span>
                    <h3 class="text-sm font-semibold text-text-primary">Idioma de publicação <span class="text-danger" aria-hidden="true">*</span></h3>
                </div>
                <fieldset class="group/lang mt-3">
                    <legend class="sr-only">Idioma de publicação</legend>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <?php foreach ($langPresets as $key => $preset): ?>
                            <?php
                            $isThis = $langKey === $key;
                            // Valor já salvo que é apelido do preset (ex.: "English") continua igual — abrir e salvar nunca reescreve a grafia.
                            $radioValue = $isThis && $language !== '' ? $language : $preset['value'];
                            $radioLabel = $preset['label'] . ($key === 'pt' ? ' (Brasil)' : '');
                            ?>
                            <label class="relative block cursor-pointer">
                                <input type="radio" name="language" value="<?= View::e($radioValue) ?>" data-lang-radio data-label="<?= View::e($radioLabel) ?>"
                                       class="peer sr-only" <?= $isThis ? 'checked' : '' ?>>
                                <span class="pick flex h-full flex-col items-center gap-2.5 rounded-xl px-3 pb-3.5 pt-4 text-center">
                                    <span class="block h-8 w-12 overflow-hidden rounded-[5px] shadow-[0_0_0_1px_rgba(255,255,255,.2),0_6px_12px_-4px_rgba(0,0,0,.6)]"><?= Flag::svg($preset['flag']) ?></span>
                                    <span>
                                        <span class="block text-sm font-semibold text-text-primary"><?= View::e($preset['label']) ?></span>
                                        <span class="block text-xs text-text-muted"><?= View::e($preset['country']) ?></span>
                                    </span>
                                </span>
                                <span aria-hidden="true" class="pointer-events-none absolute right-2 top-2 flex h-5 w-5 scale-0 items-center justify-center rounded-full bg-cyan text-void transition-transform duration-300 ease-[cubic-bezier(.34,1.56,.64,1)] peer-checked:scale-100">
                                    <span class="[&>svg]:h-3 [&>svg]:w-3"><?= Icon::nav('check') ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>

                        <label class="relative block cursor-pointer">
                            <input type="radio" name="language" value="other" data-lang-radio data-label="" class="peer sr-only" <?= $langOther ? 'checked' : '' ?>>
                            <span class="pick flex h-full flex-col items-center gap-2.5 rounded-xl border-dashed px-3 pb-3.5 pt-4 text-center peer-checked:border-solid">
                                <span class="flex h-8 w-12 items-center justify-center rounded-[5px] bg-white/5 text-cyan [&>svg]:h-6 [&>svg]:w-6"><?= Icon::nav('globe') ?></span>
                                <span>
                                    <span class="block text-sm font-semibold text-text-primary">Outro</span>
                                    <span class="block text-xs text-text-muted">Escrever o idioma</span>
                                </span>
                            </span>
                            <span aria-hidden="true" class="pointer-events-none absolute right-2 top-2 flex h-5 w-5 scale-0 items-center justify-center rounded-full bg-cyan text-void transition-transform duration-300 ease-[cubic-bezier(.34,1.56,.64,1)] peer-checked:scale-100">
                                <span class="[&>svg]:h-3 [&>svg]:w-3"><?= Icon::nav('check') ?></span>
                            </span>
                        </label>
                    </div>

                    <?php // Campo do idioma "Outro": aparece só com "Outro" marcado (CSS puro; o script só reforça). ?>
                    <div data-lang-other class="mt-3 <?= $langOther ? '' : 'hidden' ?> group-has-[input[value=other]:checked]/lang:block">
                        <label for="f_language_custom" class="block text-sm font-medium text-text-secondary">Qual idioma?</label>
                        <div class="relative mt-1">
                            <span aria-hidden="true" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-cyan [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('globe') ?></span>
                            <input type="text" id="f_language_custom" name="language_custom" value="<?= View::e($langCustom) ?>" maxlength="20"
                                   placeholder="Ex.: Français, Deutsch, Italiano"
                                   <?= isset($errors['language']) ? 'aria-invalid="true" aria-describedby="f_language_err"' : '' ?>
                                   class="w-full rounded-xl border bg-surface-2 py-2.5 pl-10 pr-4 text-text-primary placeholder:text-text-muted focus:outline-none <?= isset($errors['language']) ? 'border-danger' : 'border-border focus:border-cyan' ?>">
                        </div>
                        <p class="mt-1 text-xs text-text-muted">Até 20 caracteres — o texto vai como está pra IA.</p>
                    </div>
                    <?php if (isset($errors['language'])): ?>
                        <p id="f_language_err" class="mt-2 text-sm text-danger"><?= View::e($errors['language']) ?></p>
                    <?php endif; ?>
                </fieldset>
            </div>

            <?php // 2 — Tom ?>
            <div class="mt-8">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-cyan/15 font-mono text-xs font-bold text-cyan">2</span>
                    <h3 class="text-sm font-semibold text-text-primary">Tom de voz</h3>
                    <span class="ml-auto font-mono text-xs text-text-muted"><span data-count-for="#f_tone"><?= mb_strlen($toneValue) ?></span> / 100</span>
                </div>
                <label for="f_tone" class="sr-only">Tom de voz</label>
                <input type="text" id="f_tone" name="tone" value="<?= View::e($toneValue) ?>" placeholder="Ex.: amigável, didático, direto"
                       <?= isset($errors['tone']) ? 'aria-invalid="true" aria-describedby="f_tone_err"' : '' ?>
                       class="mt-3 w-full rounded-xl border bg-surface-2 px-4 py-2.5 text-text-primary placeholder:text-text-muted focus:outline-none <?= isset($errors['tone']) ? 'border-danger' : 'border-border focus:border-cyan' ?>">
                <?php if (isset($errors['tone'])): ?>
                    <p id="f_tone_err" class="mt-1 text-sm text-danger"><?= View::e($errors['tone']) ?></p>
                <?php endif; ?>
                <div class="mt-3 flex flex-wrap gap-2" data-chips="#f_tone" data-max="100" role="group" aria-label="Sugestões de tom">
                    <?php foreach ($toneChips as $chip): ?>
                        <button type="button" data-chip="<?= View::e($chip) ?>" aria-pressed="<?= in_array(mb_strtolower($chip), $toneTokens, true) ? 'true' : 'false' ?>"
                                class="pick pick-chip">
                            <?= View::e($chip) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php // 3 — Público ?>
            <div class="mt-8">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-cyan/15 font-mono text-xs font-bold text-cyan">3</span>
                    <h3 class="text-sm font-semibold text-text-primary">Público</h3>
                    <span class="ml-auto font-mono text-xs text-text-muted"><span data-count-for="#f_target_audience"><?= mb_strlen($audienceValue) ?></span> / 255</span>
                </div>
                <label for="f_target_audience" class="sr-only">Público</label>
                <input type="text" id="f_target_audience" name="target_audience" value="<?= View::e($audienceValue) ?>" placeholder="Ex.: pessoas que trabalham remoto e querem morar fora"
                       <?= isset($errors['target_audience']) ? 'aria-invalid="true" aria-describedby="f_target_audience_err"' : '' ?>
                       class="mt-3 w-full rounded-xl border bg-surface-2 px-4 py-2.5 text-text-primary placeholder:text-text-muted focus:outline-none <?= isset($errors['target_audience']) ? 'border-danger' : 'border-border focus:border-cyan' ?>">
                <?php if (isset($errors['target_audience'])): ?>
                    <p id="f_target_audience_err" class="mt-1 text-sm text-danger"><?= View::e($errors['target_audience']) ?></p>
                <?php endif; ?>
                <div class="mt-3 flex flex-wrap gap-2" data-chips="#f_target_audience" data-max="255" role="group" aria-label="Sugestões de público">
                    <?php foreach ($audienceChips as $chip): ?>
                        <button type="button" data-chip="<?= View::e($chip) ?>" aria-pressed="<?= in_array(mb_strtolower($chip), $audienceTokens, true) ? 'true' : 'false' ?>"
                                class="pick pick-chip">
                            <?= View::e($chip) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php // 4 — Identidade editorial ?>
            <div class="mt-8">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-cyan/15 font-mono text-xs font-bold text-cyan">4</span>
                    <h3 class="text-sm font-semibold text-text-primary">Identidade editorial</h3>
                    <span class="ml-auto font-mono text-xs text-text-muted"><span data-count-for="#f_editorial_identity"><?= mb_strlen($identityValue) ?></span> / 5000</span>
                </div>
                <label for="f_editorial_identity" class="sr-only">Identidade editorial</label>
                <textarea id="f_editorial_identity" name="editorial_identity" rows="6"
                          placeholder="Voz e estilo do site em texto livre…"
                          <?= isset($errors['editorial_identity']) ? 'aria-invalid="true" aria-describedby="f_editorial_identity_err"' : '' ?>
                          class="mt-3 w-full rounded-xl border bg-surface-2 px-4 py-3 text-text-primary placeholder:text-text-muted focus:outline-none <?= isset($errors['editorial_identity']) ? 'border-danger' : 'border-border focus:border-cyan' ?>"><?= View::e($identityValue) ?></textarea>
                <?php if (isset($errors['editorial_identity'])): ?>
                    <p id="f_editorial_identity_err" class="mt-1 text-sm text-danger"><?= View::e($errors['editorial_identity']) ?></p>
                <?php endif; ?>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-text-muted">Ex.: “explica como para um amigo, usa exemplos reais, evita jargão”.</p>
                    <button type="button" data-identity-template class="btn btn-secondary px-3 py-1.5 text-xs">
                        <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav('intelligence') ?></span>
                        Inserir modelo
                    </button>
                </div>
            </div>
        </section>

        <?php // ── Publicação ── ?>
        <section class="rounded-2xl border border-border bg-surface p-6">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan/10 text-cyan"><?= Icon::nav('wordpress') ?></span>
                <div>
                    <h2 class="font-display text-lg font-semibold text-text-primary">Publicação</h2>
                    <p class="mt-0.5 text-sm text-text-secondary">Endereço do WordPress onde os artigos aprovados são publicados.</p>
                </div>
            </div>
            <div class="mt-5">
                <?= Form::text('wordpress_url', 'URL do WordPress', $site, $errors, type: 'url') ?>
                <p class="mt-1 text-xs text-text-muted">Ex.: https://gavsy.com — usuário e senha de aplicação ficam na aba WordPress.</p>
            </div>
        </section>

        <?php // Barra de ação fixa no rodapé: com a página longa, salvar nunca fica escondido lá embaixo. ?>
        <div class="sticky bottom-4 z-20 flex items-center justify-between gap-3 rounded-2xl border border-border bg-surface/95 p-3 shadow-[0_12px_40px_-12px_rgba(0,0,0,.7)] backdrop-blur">
            <span class="hidden pl-2 text-sm text-text-secondary sm:block"><?= $isEdit ? 'Alterações só valem depois de salvar.' : 'O site é criado com o que está preenchido acima.' ?></span>
            <div class="flex gap-3">
                <a href="<?= View::e($backHref) ?>" class="btn btn-secondary px-4 py-2 text-sm">Cancelar</a>
                <button type="submit" class="btn btn-primary px-5 py-2 text-sm">
                    <?= $isEdit ? 'Salvar alterações' : 'Criar site' ?>
                </button>
            </div>
        </div>
    </div>
</form>

<?php if ($isEdit && $isAdminUser): ?>
    <section class="mx-auto mt-8 max-w-3xl rounded-2xl border border-danger/40 bg-danger/5 p-6">
        <h2 class="font-display text-base font-semibold text-danger">Zona de perigo</h2>
        <p class="mt-1 text-sm text-text-secondary">
            Exclui o site e <strong>tudo</strong> que está ligado a ele — artigos, categorias, metas,
            histórico de custo de IA, conexão com o WordPress. Não tem como desfazer.
        </p>
        <form method="post" action="/sites/<?= View::e($site['id']) ?>/delete" class="mt-4"
              data-confirm="Excluir &quot;<?= View::e($site['name']) ?>&quot; de vez? Não tem como desfazer.">
            <?= Csrf::field() ?>
            <label class="block text-sm">
                <span class="text-text-secondary">Digite <strong class="text-text-primary"><?= View::e($site['name']) ?></strong> pra confirmar</span>
                <input type="text" name="confirm_name" required autocomplete="off"
                       data-delete-confirm-input="<?= View::e($site['name']) ?>"
                       class="mt-1.5 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary focus:border-danger focus:outline-none">
            </label>
            <button type="submit" disabled data-delete-confirm-button
                    class="btn btn-danger mt-3 px-4 py-2 text-sm">
                Excluir site permanentemente
            </button>
        </form>
    </section>
<?php endif; ?>

<script>
    // Melhoria progressiva: sem JS o formulário continua completo. Aqui: selo
    // Ativo/Inativo ao vivo na placa, contador e pilha de avatares dos redatores,
    // filtro da biblioteca de logos e nome do arquivo escolhido. A troca da logo
    // (com transição) é do avatar-preview.js.
    (function () {
        var form = document.querySelector('[data-site-form]');
        if (!form) return;

        var toggle = form.querySelector('[data-active-toggle]');
        var tags = form.querySelectorAll('[data-active-tag]');
        if (toggle) {
            toggle.addEventListener('change', function () {
                tags.forEach(function (el) {
                    el.classList.toggle('hidden', el.getAttribute('data-active-tag') !== (toggle.checked ? '1' : '0'));
                });
            });
        }

        // Redatores
        var boxes = form.querySelectorAll('[data-editor-box]');
        var count = form.querySelector('[data-editors-count]');
        function syncEditors() {
            var n = 0;
            boxes.forEach(function (box) {
                if (box.checked) n += 1;
                var chip = form.querySelector('[data-editor-avatar="' + box.value + '"]');
                if (chip) chip.classList.toggle('hidden', !box.checked);
            });
            if (count) count.textContent = String(n);
        }
        boxes.forEach(function (box) { box.addEventListener('change', syncEditors); });
        var all = form.querySelector('[data-editors-all]');
        var none = form.querySelector('[data-editors-none]');
        if (all) all.addEventListener('click', function () { boxes.forEach(function (b) { b.checked = true; }); syncEditors(); });
        if (none) none.addEventListener('click', function () { boxes.forEach(function (b) { b.checked = false; }); syncEditors(); });

        // Biblioteca de logos: carrega as imagens conforme a grade rola (ver data-src no HTML)
        var lazyImgs = form.querySelectorAll('img[data-src]');
        function loadImg(img) { img.src = img.getAttribute('data-src'); img.removeAttribute('data-src'); }
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (!en.isIntersecting) return;
                    loadImg(en.target);
                    io.unobserve(en.target);
                });
            }, { root: form.querySelector('[data-logo-grid]'), rootMargin: '240px' });
            lazyImgs.forEach(function (img) { io.observe(img); });
        } else {
            lazyImgs.forEach(loadImg);
        }

        // Biblioteca de logos: filtro por domínio
        var items = form.querySelectorAll('[data-logo-item]');
        var filter = form.querySelector('[data-logo-filter]');
        var empty = form.querySelector('[data-logo-empty]');
        if (filter) {
            filter.addEventListener('input', function () {
                var q = filter.value.trim().toLowerCase();
                var shown = 0;
                items.forEach(function (el) {
                    var hide = q !== '' && el.getAttribute('data-logo-domain').indexOf(q) === -1;
                    el.classList.toggle('hidden', hide);
                    if (!hide) shown += 1;
                });
                if (empty) empty.classList.toggle('hidden', shown > 0);
            });
            filter.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
        }

        // Nome do arquivo escolhido no botão de envio
        var file = form.querySelector('#f_logo');
        var label = form.querySelector('[data-logo-filename]');
        if (file && label) {
            var original = label.textContent;
            file.addEventListener('change', function () {
                label.textContent = file.files && file.files[0] ? file.files[0].name : original;
            });
        }
    })();
</script>

<script>
    // Voz editorial (melhoria progressiva — sem JS o formulário envia os mesmos campos):
    // resumo e barra de preenchimento ao vivo, contadores, chips que montam o texto de
    // tom/público (lista separada por vírgula) e o botão de modelo da identidade.
    (function () {
        var root = document.querySelector('[data-voice]');
        if (!root) return;

        var tone = root.querySelector('#f_tone');
        var audience = root.querySelector('#f_target_audience');
        var identity = root.querySelector('#f_editorial_identity');
        var custom = root.querySelector('#f_language_custom');
        var otherBlock = root.querySelector('[data-lang-other]');
        var summary = root.querySelector('[data-voice-summary]');
        var segs = root.querySelectorAll('[data-voice-seg]');
        var done = root.querySelector('[data-voice-done]');

        // Sem ponto final (o texto do usuário já pode terminar em ponto) e cortado se muito longo.
        function clip(text, n) { text = text.replace(/[.\s]+$/, ''); return text.length > n ? text.slice(0, n - 1) + '…' : text; }

        function selectedLanguage() {
            var checked = root.querySelector('[data-lang-radio]:checked');
            if (!checked) return '';
            return checked.value === 'other' ? custom.value.trim() : checked.getAttribute('data-label');
        }

        function strong(text) {
            var el = document.createElement('strong');
            el.className = 'text-cyan-bright';
            el.textContent = text;
            return el;
        }

        function sync() {
            var lang = selectedLanguage();
            var t = tone.value.trim();
            var a = audience.value.trim();

            if (otherBlock) {
                var other = root.querySelector('[data-lang-radio][value="other"]');
                otherBlock.classList.toggle('hidden', !(other && other.checked));
            }

            // Texto vindo do usuário entra por textContent (nunca innerHTML).
            summary.textContent = '';
            summary.appendChild(document.createTextNode('Escreve em '));
            summary.appendChild(strong(lang !== '' ? lang : '…'));
            if (t !== '') { summary.appendChild(document.createTextNode(', com tom ')); summary.appendChild(strong(clip(t, 80))); }
            if (a !== '') { summary.appendChild(document.createTextNode(', para ')); summary.appendChild(strong(clip(a, 80))); }
            summary.appendChild(document.createTextNode('.'));

            var filled = [lang !== '', t !== '', a !== '', identity.value.trim() !== ''].filter(Boolean).length;
            if (done) done.textContent = String(filled);
            segs.forEach(function (seg, i) {
                seg.classList.toggle('bg-cyan', i < filled);
                seg.classList.toggle('bg-border-strong', i >= filled);
            });

            root.querySelectorAll('[data-count-for]').forEach(function (el) {
                var field = root.querySelector(el.getAttribute('data-count-for'));
                if (field) el.textContent = String(field.value.length);
            });
        }

        function tokens(value) {
            return value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        }

        // Chips: liga/desliga a palavra na lista do campo (respeita o limite de caracteres).
        root.querySelectorAll('[data-chips]').forEach(function (group) {
            var field = root.querySelector(group.getAttribute('data-chips'));
            var max = parseInt(group.getAttribute('data-max'), 10) || 0;
            if (!field) return;

            function paint() {
                var current = tokens(field.value).map(function (s) { return s.toLowerCase(); });
                group.querySelectorAll('[data-chip]').forEach(function (chip) {
                    chip.setAttribute('aria-pressed', current.indexOf(chip.getAttribute('data-chip').toLowerCase()) !== -1 ? 'true' : 'false');
                });
            }

            group.querySelectorAll('[data-chip]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    var word = chip.getAttribute('data-chip');
                    var list = tokens(field.value);
                    var at = list.map(function (s) { return s.toLowerCase(); }).indexOf(word.toLowerCase());
                    if (at !== -1) {
                        list.splice(at, 1);
                    } else {
                        var next = list.concat(word).join(', ');
                        if (max && next.length > max) return; // não cabe: deixa como está
                        list.push(word);
                    }
                    field.value = list.join(', ');
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                });
            });
            field.addEventListener('input', paint);
        });

        var TEMPLATE = 'Voz: como o site fala com o leitor (ex.: explica como para um amigo).\n'
            + 'Estilo: frases curtas, exemplos reais e listas quando ajudam.\n'
            + 'Evitar: jargão, promessas exageradas e clickbait.';
        var templateBtn = root.querySelector('[data-identity-template]');
        if (templateBtn) {
            templateBtn.addEventListener('click', function () {
                identity.value = identity.value.trim() === '' ? TEMPLATE : identity.value.replace(/\s+$/, '') + '\n\n' + TEMPLATE;
                identity.focus();
                identity.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }

        [tone, audience, identity, custom].forEach(function (el) { if (el) el.addEventListener('input', sync); });
        root.querySelectorAll('[data-lang-radio]').forEach(function (r) {
            r.addEventListener('change', function () {
                sync();
                if (r.value === 'other' && custom) custom.focus();
            });
        });
        sync();
    })();
</script>

<script>
    // Identidade (melhoria progressiva): contadores, barra de preenchimento e chips de nicho.
    (function () {
        var root = document.querySelector('[data-identity]');
        if (!root) return;

        var name = root.querySelector('#f_name');
        var niche = root.querySelector('#f_niche');
        var segs = root.querySelectorAll('[data-identity-seg]');
        var done = root.querySelector('[data-identity-done]');

        function sync() {
            var n = name.value.trim();
            var v = niche.value.trim();
            var filled = [n !== '', v !== ''].filter(Boolean).length;
            if (done) done.textContent = String(filled);
            segs.forEach(function (seg, i) {
                seg.classList.toggle('bg-cyan', i < filled);
                seg.classList.toggle('bg-border-strong', i >= filled);
            });
            root.querySelectorAll('[data-count-for]').forEach(function (el) {
                var field = root.querySelector(el.getAttribute('data-count-for'));
                if (field) el.textContent = String(field.value.length);
            });
            root.querySelectorAll('[data-niche-chip]').forEach(function (chip) {
                chip.setAttribute('aria-pressed', chip.getAttribute('data-niche-chip').toLowerCase() === v.toLowerCase() ? 'true' : 'false');
            });
        }

        // Nicho é um valor só: o chip preenche (ou limpa, se já era o escolhido).
        root.querySelectorAll('[data-niche-chip]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var word = chip.getAttribute('data-niche-chip');
                niche.value = niche.value.trim().toLowerCase() === word.toLowerCase() ? '' : word;
                niche.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });

        [name, niche].forEach(function (el) { el.addEventListener('input', sync); });
        sync();
    })();
</script>

<?php if ($isEdit && $isAdminUser): ?>
    <script>
        // Botão só habilita com o nome exato digitado
        // — o servidor confere de novo (SiteController::destroy() nunca confia só no JS).
        (function () {
            var input = document.querySelector('[data-delete-confirm-input]');
            var button = document.querySelector('[data-delete-confirm-button]');
            if (!input || !button) return;
            input.addEventListener('input', function () {
                button.disabled = input.value !== input.getAttribute('data-delete-confirm-input');
            });
        })();
    </script>
<?php endif; ?>

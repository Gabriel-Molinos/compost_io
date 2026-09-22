<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ArticleService;
use App\Services\AuthService;
use App\Services\CategoryService;
use App\Services\CostBudgetService;
use App\Services\EditorialRuleService;
use App\Services\GoalService;
use App\Services\NotificationService;
use App\Services\ReportService;
use App\Services\SiteLogoLibraryService;
use App\Services\SiteService;
use App\Services\UserService;
use App\Services\WordPressConnectionService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Languages;
use App\Support\Session;
use App\Support\SiteListing;
use App\Support\Uploads;
use App\Support\Validator;
use App\View;

final class SiteController extends Controller
{
    private SiteService $sites;
    private CategoryService $categories;
    private EditorialRuleService $rules;
    private GoalService $goals;
    private ReportService $reports;
    private CostBudgetService $costBudget;
    private ArticleService $articles;
    private UserService $users;

    public function __construct()
    {
        $this->users = new UserService();
        $this->sites = new SiteService();
        $this->categories = new CategoryService();
        $this->rules = new EditorialRuleService();
        $this->goals = new GoalService();
        $this->reports = new ReportService();
        $this->costBudget = new CostBudgetService();
        $this->articles = new ArticleService();
    }

    /** Lista de sites — ADMIN vê todos, Redator-Chefe vê os vinculados. */
    public function index(): void
    {
        $user = AuthService::user();
        $all = $this->sites->overview(AuthService::isAdmin() ? null : (int) $user['id']);

        // Filtros da querystring (GET de verdade — compartilhável e atualizável com F5;
        // o filtro em tempo real da tela só troca as regiões de resultado). Valor
        // desconhecido vira "sem filtro", nunca erro.
        $q      = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $status = in_array($_GET['status'] ?? '', SiteListing::STATUS, true) ? (string) $_GET['status'] : '';
        $wp     = in_array($_GET['wp'] ?? '', SiteListing::WP, true) ? (string) $_GET['wp'] : '';
        $lang   = mb_substr(trim((string) ($_GET['lang'] ?? '')), 0, 20);
        $order  = in_array($_GET['order'] ?? '', SiteListing::ORDER, true) ? (string) $_GET['order'] : 'name';

        // Contagens dos chips e opções de idioma saem da lista COMPLETA (não mudam conforme o filtro).
        $counts = [
            'all'       => count($all),
            'active'    => count(array_filter($all, static fn (array $s): bool => (int) $s['is_active'] === 1)),
            'inactive'  => count(array_filter($all, static fn (array $s): bool => (int) $s['is_active'] !== 1)),
            'attention' => count(array_filter($all, static fn (array $s): bool => SiteListing::needsAttention($s))),
            'ok'        => 0, 'issue' => 0, 'none' => 0,
        ];
        $languages = [];
        $presets = Languages::presets();
        foreach ($all as $s) {
            $counts[SiteListing::wpBucket($s)]++;
            $key = SiteListing::languageKey($s);
            $languages[$key] ??= ['label' => ($presets[$key]['label'] ?? trim((string) $s['language'])), 'count' => 0];
            $languages[$key]['count']++;
        }
        ksort($languages);

        View::render('sites/index', [
            'title'         => 'Sites',
            'sites'         => SiteListing::sort(SiteListing::filter($all, $q, $status, $wp, $lang), $order),
            'isAdmin'       => AuthService::isAdmin(),
            'counts'        => $counts,
            'languages'     => $languages,
            'filters'       => ['q' => $q, 'status' => $status, 'wp' => $wp, 'lang' => $lang, 'order' => $order],
            'filtersActive' => $q !== '' || $status !== '' || $wp !== '' || $lang !== '' || $order !== 'name',
        ]);
    }

    /** Área de trabalho de um site (configuração editorial). */
    public function show(string $id): void
    {
        $site = $this->requireSite($id);

        // currentSpend() (2 queries), não monthly() (10 queries) — a Visão Geral só
        // usa goal_total/ai_cost; o relatório completo fica na aba Relatórios
        // (levantamento de performance, Fase 9).
        $currentPeriod = date('Y-m');
        $spend = $this->reports->currentSpend((int) $site['id'], $currentPeriod);

        View::render('sites/show', [
            'title'       => $site['name'],
            'site'        => $site,
            'categories'  => $this->categories->allForSite((int) $site['id']),
            'ruleCounts'  => $this->rules->countsForSite((int) $site['id']),
            'goalCount'   => $this->goals->countForSite((int) $site['id']),
            // Não usado em sites/show.php hoje (a aba "Configuração" de
            // _tabs.php é quem realmente controla o acesso à tela de
            // edição) — mantido coerente com a mesma regra mesmo assim,
            // pra não ficar um valor errado à espera de alguém usar.
            'canEditSite' => AuthService::canAccessSite((int) $site['id']),
            'costBudget'  => $this->costBudget->evaluate($spend),
            'attention'   => $this->articles->attentionCounts((int) $site['id']),
            'staleCount'  => $this->articles->staleGeneratingCount((int) $site['id']),
            // Só produzidos (mesma agregação leve do relatório, §97 performance) — dá
            // uma sensação de "painel de controle" na Visão Geral sem duplicar a
            // aba Relatórios (que segue sendo o lugar da análise completa).
            'trend'       => $this->reports->trend((int) $site['id'], $currentPeriod, 6),
        ]);
    }

    // --- CRUD da estrutura do site (somente ADMIN, via guard de rota) ---

    public function create(): void
    {
        View::render('sites/form', [
            'title'       => 'Novo site',
            'site'        => ['language' => 'pt-BR', 'is_active' => 1],
            'action'      => '/sites',
            'errors'      => [],
        ] + $this->formExtras(null));
    }

    public function store(): void
    {
        Csrf::verify();
        $this->applyLanguageChoice();

        $errors = $this->validate($_POST);
        if ($errors !== []) {
            http_response_code(422);
            View::render('sites/form', [
                'title' => 'Novo site', 'site' => $_POST, 'action' => '/sites', 'errors' => $errors,
            ] + $this->formExtras(null, $this->postedUserIds()));
            return;
        }

        $newId = $this->sites->create($_POST);
        // Vínculo dos redatores antes da logo: se a logo falhar (redirect abaixo), o vínculo já foi salvo.
        $this->syncEditors($newId, (string) $_POST['name']);

        try {
            $logo = $this->resolveLogo($newId, null, $_FILES['logo'] ?? null, (string) ($_POST['library_logo'] ?? ''), (string) ($_POST['wordpress_url'] ?? ''), allowAutoMatch: true);
            if ($logo !== null) {
                $this->sites->setLogo($newId, $logo['path'], $logo['library_filename']);
            }
        } catch (\RuntimeException $e) {
            Session::flash('error', 'Site criado, mas o logo não foi salvo: ' . $e->getMessage());
            Http::redirect('/sites/' . $newId . '/edit');
            return;
        }

        Session::flash('success', 'Site criado.');
        Http::redirect('/sites/' . $newId . '/edit');
    }

    public function edit(string $id): void
    {
        // requireSite(), não sites->find() direto: agora que Redator-Chefe
        // também acessa esta tela (rota virou auth: true, pedido do
        // responsável 2026-09-22 — "pode mudar configurações, só não pode
        // criar/excluir"), precisa checar que o site é um dos dele. Admin
        // continua vendo qualquer um, como sempre.
        $site = $this->requireSite($id);

        View::render('sites/form', [
            'title'       => 'Editar site',
            'site'        => $site,
            'action'      => '/sites/' . $site['id'],
            'errors'      => [],
        ] + $this->formExtras((int) $site['id']));
    }

    public function update(string $id): void
    {
        Csrf::verify();
        $this->applyLanguageChoice();
        $site = $this->requireSite($id); // ver comentário em edit()

        $errors = $this->validate($_POST);
        if ($errors !== []) {
            http_response_code(422);
            View::render('sites/form', [
                'title'  => 'Editar site',
                // A logo atual continua aparecendo no cabeçalho mesmo com erro de validação.
                'site'   => $_POST + [
                    'id' => $site['id'],
                    'logo_path' => $site['logo_path'] ?? null,
                    'logo_library_filename' => $site['logo_library_filename'] ?? null,
                ],
                'action' => '/sites/' . $site['id'],
                'errors' => $errors,
            ] + $this->formExtras((int) $site['id'], $this->postedUserIds()));
            return;
        }

        $this->sites->update((int) $site['id'], $_POST);
        // Só ADMIN mexe em quem é Redator-Chefe do site (a seção nem aparece
        // pro Redator-Chefe na View — ver sites/form.php) — sem esta guarda,
        // o POST dele não traria nenhum `user_ids[]` (seção ausente do HTML)
        // e syncEditors() DESVINCULARIA todo mundo do site a cada salvamento.
        if (AuthService::isAdmin()) {
            $this->syncEditors((int) $site['id'], (string) $_POST['name']);
        }

        if (!empty($_POST['remove_logo'])) {
            Uploads::delete($site['logo_path'] ?? null);
            $this->sites->setLogo((int) $site['id'], null, null);
        } else {
            try {
                $logo = $this->resolveLogo(
                    (int) $site['id'],
                    (int) $site['id'],
                    $_FILES['logo'] ?? null,
                    (string) ($_POST['library_logo'] ?? ''),
                    (string) ($_POST['wordpress_url'] ?? ''),
                    // Upload manual ou escolha explícita na biblioteca sempre
                    // vale; o casamento AUTOMÁTICO por domínio só entra se o
                    // site ainda não tem logo nenhuma — nunca troca uma logo
                    // já definida por trás do admin sem ele pedir.
                    allowAutoMatch: empty($site['logo_path']),
                );
                if ($logo !== null) {
                    $this->sites->setLogo((int) $site['id'], $logo['path'], $logo['library_filename']);
                }
            } catch (\RuntimeException $e) {
                Session::flash('error', 'Site salvo, mas o logo não foi atualizado: ' . $e->getMessage());
                Http::redirect('/sites/' . $site['id'] . '/edit');
                return;
            }
        }

        Session::flash('success', 'Site atualizado.');
        Http::redirect('/sites/' . $site['id'] . '/edit');
    }

    /**
     * Exclusão de verdade — apaga o site e TUDO ligado a ele (artigos,
     * categorias, metas, custo de IA, conexão WordPress...) via
     * `ON DELETE CASCADE` (ver `SiteService::delete()`). Irreversível, por
     * isso exige o admin digitar o nome exato do site no formulário (dupla
     * checagem: o botão só habilita com o nome certo no JS, e aqui de novo
     * no servidor — nunca confia só no que o JS deixou passar).
     */
    public function destroy(string $id): void
    {
        Csrf::verify();
        $site = $this->sites->find((int) $id) ?? $this->notFound();

        $typed = trim((string) ($_POST['confirm_name'] ?? ''));
        if ($typed !== $site['name']) {
            Session::flash('error', 'Nome digitado não bateu com o nome do site — nada foi excluído.');
            Http::redirect('/sites/' . $site['id'] . '/edit');
            return;
        }

        Uploads::delete($site['logo_path'] ?? null);
        $this->sites->delete((int) $site['id']);

        Session::flash('success', 'Site "' . $site['name'] . '" excluído, com todo o conteúdo ligado a ele.');
        Http::redirect('/sites');
    }

    /**
     * Dados extras que a tela do site precisa (biblioteca de logos, estado da
     * conexão WordPress, lista de Redatores-Chefe e quem já está vinculado).
     * `$assignedUserIds` explícito = re-render de um POST com erro (mantém o que
     * o admin tinha marcado); nulo = lê do banco.
     *
     * @param list<int>|null $assignedUserIds
     * @return array<string, mixed>
     */
    private function formExtras(?int $siteId, ?array $assignedUserIds = null): array
    {
        return [
            'logoLibrary'     => $this->availableLogoLibrary($siteId),
            'connection'      => $siteId !== null ? (new WordPressConnectionService())->forSite($siteId) : null,
            'editors'         => array_values(array_filter($this->users->all(), static fn (array $u): bool => $u['role'] === 'REDATOR_CHEFE')),
            'assignedUserIds' => $assignedUserIds ?? ($siteId !== null ? $this->sites->userIdsFor($siteId) : []),
        ];
    }

    /**
     * Seletor de idioma da tela do site: os presets (português/inglês/espanhol)
     * mandam o próprio texto a gravar em `language`; a opção "Outro" manda o
     * marcador `other` e o texto digitado vem em `language_custom`. Resolve isso
     * ANTES de validar/gravar/re-renderizar, então o resto do fluxo só enxerga
     * o idioma final (e "Outro" em branco cai no erro normal de campo obrigatório).
     */
    private function applyLanguageChoice(): void
    {
        if (($_POST['language'] ?? '') === 'other') {
            $_POST['language'] = trim((string) ($_POST['language_custom'] ?? ''));
        }
    }

    /** @return list<int> */
    private function postedUserIds(): array
    {
        return array_map('intval', (array) ($_POST['user_ids'] ?? []));
    }

    /** Grava os Redatores-Chefe marcados e avisa só os que acabaram de ser vinculados. */
    private function syncEditors(int $siteId, string $siteName): void
    {
        $notifications = new NotificationService();
        foreach ($this->sites->syncUsers($siteId, $this->postedUserIds()) as $userId) {
            $notifications->notifySiteAssigned($userId, $siteId, $siteName);
        }
    }

    /**
     * Logos da biblioteca disponíveis pro seletor — nunca as já ocupadas
     * por OUTRO site.
     *
     * @return list<array{domain: string, filename: string, url: string}>
     */
    private function availableLogoLibrary(?int $excludeSiteId): array
    {
        $used = $this->sites->usedLogoLibraryFilenames($excludeSiteId);

        return (new SiteLogoLibraryService())->all($used);
    }

    /**
     * Decide de onde vem a logo do site, nesta ordem — a primeira que
     * existir vence: (1) upload manual, (2) escolha explícita no seletor
     * visual da biblioteca (`sites/form.php`, achado real 2026-09-15: sem
     * seletor visível, o redator não sabia que a biblioteca existia), (3)
     * casamento automático pelo domínio, só quando `$allowAutoMatch` (nunca
     * substitui uma logo já definida sem o admin pedir explicitamente).
     * Sem nenhuma das três, devolve null silenciosamente.
     *
     * Nos casos (2) e (3), confere de novo no servidor que o arquivo não
     * está ocupado por outro site — o seletor já esconde as ocupadas, mas
     * nunca confia só nisso (POST forjado/página desatualizada poderia
     * mandar um filename que já não está mais livre).
     *
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $uploadedFile
     * @return array{path: string, library_filename: ?string}|null
     */
    private function resolveLogo(int $siteId, ?int $excludeSiteId, ?array $uploadedFile, string $libraryFilename, string $wordpressUrl, bool $allowAutoMatch): ?array
    {
        $logo = Uploads::image($uploadedFile, 'logos', $siteId);
        if ($logo !== null) {
            return ['path' => $logo, 'library_filename' => null];
        }

        $library = new SiteLogoLibraryService();
        $used = $this->sites->usedLogoLibraryFilenames($excludeSiteId);

        $chosenFile = $library->findByFilename($libraryFilename);
        if ($chosenFile !== null && !in_array(basename($chosenFile), $used, true)) {
            return ['path' => Uploads::fromLocalFile($chosenFile, 'logos', $siteId), 'library_filename' => basename($chosenFile)];
        }

        if (!$allowAutoMatch) {
            return null;
        }

        $matchedFile = $library->findForDomain($wordpressUrl);
        if ($matchedFile === null || in_array(basename($matchedFile), $used, true)) {
            return null;
        }

        return ['path' => Uploads::fromLocalFile($matchedFile, 'logos', $siteId), 'library_filename' => basename($matchedFile)];
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function validate(array $data): array
    {
        return (new Validator($data, [
            'name'          => ['required', 'max:191'],
            'language'      => ['required', 'max:20'],
            'wordpress_url' => ['max:255'],
            'niche'         => ['max:191'],
            'tone'          => ['max:100'],
            'target_audience' => ['max:255'],
            'editorial_identity' => ['max:5000'],
        ], [
            'name' => 'Nome', 'language' => 'Idioma', 'wordpress_url' => 'URL do WordPress',
            'niche' => 'Nicho', 'tone' => 'Tom', 'target_audience' => 'Público', 'editorial_identity' => 'Identidade editorial',
        ]))->errors();
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ArticleNoteService;
use App\Services\ResearchGapHintService;
use App\Services\SiteSourceService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\Support\Validator;
use App\View;
use Throwable;

/** Fontes confiáveis do site (fluxo-editorial §21) — cadastro sempre humano, consultado pela IA no passo research. */
final class SiteSourceController extends Controller
{
    private SiteSourceService $sources;

    public function __construct()
    {
        $this->sources = new SiteSourceService();
    }

    public function index(string $siteId): void
    {
        $site = $this->requireSite($siteId);

        View::render('sites/sources/index', [
            'title'   => 'Fontes · ' . $site['name'],
            'site'    => $site,
            'sources' => $this->sources->allForSite((int) $site['id']),
            'researchGaps' => (new ArticleNoteService())->researchGapsForSite((int) $site['id']),
        ]);
    }

    public function store(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        $errors = (new Validator($_POST, [
            'url'  => ['required', 'url', 'max:2048'],
            'note' => ['max:500'],
        ], ['url' => 'URL', 'note' => 'Nota']))->errors();

        if ($errors !== []) {
            http_response_code(422);
            Session::flash('error', reset($errors));
            Http::redirect('/sites/' . $site['id'] . '/sources');
            return;
        }

        $note = trim((string) ($_POST['note'] ?? ''));
        $this->sources->create((int) $site['id'], trim((string) $_POST['url']), $note === '' ? null : $note);
        Session::flash('success', 'Fonte adicionada.');
        Http::redirect('/sites/' . $site['id'] . '/sources');
    }

    public function destroy(string $siteId, string $sourceId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $source = $this->sources->find((int) $site['id'], (int) $sourceId) ?? $this->notFound();

        $this->sources->delete((int) $source['id']);
        Session::flash('success', 'Fonte removida.');
        Http::redirect('/sites/' . $site['id'] . '/sources');
    }

    /**
     * "Sugerir buscas" pra uma lacuna de pesquisa (pedido 2026-09-22) — 1
     * chamada de IA (custo real), mesmo padrão síncrono de
     * suggestInternalLinks()/suggestExternalLinks() em ProductionController.
     * O texto da lacuna vem do POST (não é lido de volta do banco por
     * `$gidx`) porque a view já tem o texto exato na mão — evita reabrir
     * `researchGapsForSite()` só pra reencontrar a mesma string.
     */
    public function suggestGapHints(string $siteId, string $articleId, string $gapIndex): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        $gapText = trim((string) ($_POST['gap_text'] ?? ''));

        set_time_limit(60);

        try {
            (new ResearchGapHintService())->suggest((int) $articleId, (int) $site['id'], (int) $gapIndex, $gapText);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        Http::redirect('/sites/' . $site['id'] . '/sources#h-lacunas');
    }
}

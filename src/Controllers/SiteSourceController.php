<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SiteSourceService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\Support\Validator;
use App\View;

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
}

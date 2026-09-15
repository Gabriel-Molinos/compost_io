<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ArticleNoteService;
use App\Services\ArticleService;
use App\Services\LinkFixService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\View;
use Throwable;

/**
 * Central de Links (`/sites/{id}/links`, achado real 2026-09-10): links
 * ambíguos (bloqueio de bot, não confirmados) ou que morreram depois de
 * publicados (link rot, `bin/worker.php`) viravam só texto de aviso — sem
 * jeito prático de agir sem editar HTML. Redator-Chefe (ou ADMIN).
 */
final class LinksController extends Controller
{
    private ArticleNoteService $notes;
    private ArticleService $articles;

    public function __construct()
    {
        $this->notes = new ArticleNoteService();
        $this->articles = new ArticleService();
    }

    public function index(string $siteId): void
    {
        $site = $this->requireSite($siteId);

        View::render('sites/links/index', [
            'title'  => 'Links · ' . $site['name'],
            'site'   => $site,
            'flagged' => $this->notes->flaggedLinksForSite((int) $site['id']),
        ]);
    }

    public function remove(string $siteId, string $articleId): void
    {
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            $url = (string) ($_POST['url'] ?? '');
            $result = (new LinkFixService())->removeLink($aid, $sid, $url);
            Session::flash('success', 'Link removido.' . $this->syncNote($result));
        });
    }

    public function replace(string $siteId, string $articleId): void
    {
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            $oldUrl = (string) ($_POST['old_url'] ?? '');
            $newUrl = (string) ($_POST['new_url'] ?? '');
            $result = (new LinkFixService())->replaceLink($aid, $sid, $oldUrl, $newUrl);
            $msg = 'Link atualizado.' . $this->syncNote($result);
            if (!empty($result['warning'])) {
                $msg .= ' ' . $result['warning'];
            }
            Session::flash('success', $msg);
        });
    }

    public function confirm(string $siteId, string $articleId): void
    {
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            $url = (string) ($_POST['url'] ?? '');
            (new LinkFixService())->confirmOk($aid, $url);
            Session::flash('success', 'Confirmado — o link continua no artigo sem avisos.');
        });
    }

    public function applyBacklink(string $siteId, string $articleId): void
    {
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            $anchor = (string) ($_POST['anchor_text'] ?? '');
            $url = (string) ($_POST['url'] ?? '');
            $result = (new LinkFixService())->applyBacklink($aid, $sid, $anchor, $url);
            Session::flash('success', 'Link interno adicionado.' . $this->syncNote($result));
        });
    }

    public function dismissBacklink(string $siteId, string $articleId): void
    {
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            $anchor = (string) ($_POST['anchor_text'] ?? '');
            $url = (string) ($_POST['url'] ?? '');
            (new LinkFixService())->dismissBacklink($aid, $anchor, $url);
            Session::flash('success', 'Sugestão dispensada.');
        });
    }

    /** @param array{synced_to_wordpress?:bool, wordpress_error?:?string} $result */
    private function syncNote(array $result): string
    {
        if (($result['synced_to_wordpress'] ?? false) === true) {
            return ' Já reenviado pro WordPress.';
        }
        if (!empty($result['wordpress_error'])) {
            return ' O post já está publicado, mas o reenvio pro WordPress falhou (' . $result['wordpress_error'] . ') — use "Atualizar no WordPress" na tela do artigo pra tentar de novo.';
        }

        return '';
    }

    /** @param callable(int, int): void $action */
    private function handle(string $siteId, string $articleId, callable $action): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $article = $this->articles->find((int) $site['id'], (int) $articleId) ?? $this->notFound();

        // Corrigir um link pode reenviar o artigo inteiro pro WordPress
        // (`syncIfPublished()`), que reconfere TODOS os links do corpo com
        // requisição HTTP de verdade — mesmo achado real de
        // ProductionController::updateContent() (2026-09-14): soma fácil
        // mais que os 30s padrão do PHP.
        @set_time_limit(120);

        try {
            $action((int) $site['id'], (int) $article['id']);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        Http::redirect('/sites/' . $site['id'] . '/links');
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ArticleService;
use App\Services\ScheduleService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use Throwable;

/**
 * Agendamento de publicação de um artigo aprovado (RF-011, Fase 7.4).
 * Redator-Chefe (ou ADMIN) com acesso ao site.
 */
final class ScheduleController extends Controller
{
    private ScheduleService $schedules;
    private ArticleService $articles;

    public function __construct()
    {
        $this->schedules = new ScheduleService();
        $this->articles = new ArticleService();
    }

    public function store(string $siteId, string $articleId): void
    {
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            $this->schedules->schedule($aid, $sid, $this->authorId(), $this->dateTime(), $this->imageId());
            Session::flash('success', 'Artigo agendado.');
        });
    }

    public function update(string $siteId, string $articleId): void
    {
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            $this->schedules->reschedule($aid, $sid, $this->authorId(), $this->dateTime(), $this->imageId());
            Session::flash('success', 'Agendamento atualizado.');
        });
    }

    public function destroy(string $siteId, string $articleId): void
    {
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            $this->schedules->cancel($aid, $sid);
            Session::flash('success', 'Agendamento cancelado. O artigo voltou para aprovado.');
        });
    }

    /** @param callable(int, int): void $action */
    private function handle(string $siteId, string $articleId, callable $action): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $article = $this->articles->find((int) $site['id'], (int) $articleId) ?? $this->notFound();

        try {
            $action((int) $site['id'], (int) $article['id']);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        Http::redirect('/sites/' . $site['id'] . '/production/' . $article['id'] . '#agendar');
    }

    private function authorId(): int
    {
        return (int) ($_POST['author_id'] ?? 0);
    }

    private function imageId(): int
    {
        return (int) ($_POST['image_id'] ?? 0);
    }

    private function dateTime(): string
    {
        return (string) ($_POST['scheduled_date'] ?? '');
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ArticleService;
use App\Services\ScheduleService;
use App\Services\WordPressPublishService;
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

    public function publish(string $siteId, string $articleId): void
    {
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            $r = (new WordPressPublishService())->publish($aid, $sid);
            $label = $r['status'] === 'future' ? 'agendado no WordPress' : 'publicado';
            Session::flash('success', 'Artigo ' . $label . ' — post #' . $r['post_id'] . ' no WordPress.' . self::extras($r));
        });
    }

    public function republish(string $siteId, string $articleId): void
    {
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            $r = (new WordPressPublishService())->update($aid, $sid);
            Session::flash('success', 'Post #' . $r['post_id'] . ' atualizado no WordPress.' . self::extras($r));
        });
    }

    public function retract(string $siteId, string $articleId): void
    {
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            (new WordPressPublishService())->retract($aid, $sid);
            Session::flash('success', 'Post retirado do WordPress (foi para a lixeira lá). O artigo voltou para aprovado.');
        });
    }

    /** @param array{links_rewritten:int, links_unwrapped:int, body_images:int} $r */
    private static function extras(array $r): string
    {
        $bits = [];
        if ($r['body_images'] > 0) {
            $bits[] = $r['body_images'] . ' imagem(ns) de corpo';
        }
        if ($r['links_rewritten'] > 0 || $r['links_unwrapped'] > 0) {
            $bits[] = sprintf('links internos: %d resolvido(s), %d removido(s)', $r['links_rewritten'], $r['links_unwrapped']);
        }

        return $bits === [] ? '' : ' (' . implode(' · ', $bits) . ')';
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

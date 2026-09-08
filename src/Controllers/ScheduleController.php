<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ArticleService;
use App\Services\NotificationService;
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
    private NotificationService $notifications;

    public function __construct()
    {
        $this->schedules = new ScheduleService();
        $this->articles = new ArticleService();
        $this->notifications = new NotificationService();
    }

    /**
     * Primeiro agendamento, logo após aprovar: só autor + imagem — a data/hora
     * é escolhida sozinha (próximo horário livre, 9h). Pedido do responsável
     * 2026-09-04: Redator-Chefe não digita mais data aqui. `update()` (reagendar)
     * continua manual, é o escape-hatch pra quem quiser uma data diferente.
     */
    public function store(string $siteId, string $articleId): void
    {
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            $this->schedules->scheduleAuto($aid, $sid, $this->authorId(), $this->imageId());
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
        $this->raiseLimit();
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            try {
                $r = (new WordPressPublishService())->publish($aid, $sid);
            } catch (Throwable $e) {
                $this->notifyPublishResult($sid, $aid, false, $e->getMessage());
                throw $e;
            }
            $label = $r['status'] === 'future' ? 'agendado no WordPress' : 'publicado';
            Session::flash('success', 'Artigo ' . $label . ' — post #' . $r['post_id'] . ' no WordPress.' . self::extras($r));
            $this->notifyPublishResult($sid, $aid, true, 'Post #' . $r['post_id'] . ' no WordPress.');
        });
    }

    public function republish(string $siteId, string $articleId): void
    {
        $this->raiseLimit();
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            $r = (new WordPressPublishService())->update($aid, $sid);
            Session::flash('success', 'Post #' . $r['post_id'] . ' atualizado no WordPress.' . self::extras($r));
        });
    }

    public function retract(string $siteId, string $articleId): void
    {
        $this->raiseLimit();
        $this->handle($siteId, $articleId, function (int $sid, int $aid): void {
            (new WordPressPublishService())->retract($aid, $sid);
            Session::flash('success', 'Post retirado do WordPress (foi para a lixeira lá). O artigo voltou para aprovado.');
        });
    }

    /**
     * Avisa a equipe do site (ADMINs + Redator-Chefe vinculado) do resultado
     * da publicação — deu certo ou deu errado (pedido do responsável,
     * 2026-09-08). Mesmo caminho usado pelo clique manual (aqui) e pela
     * publicação automática agendada (ScheduleJobHandlers) — ninguém precisa
     * estar olhando a tela na hora pra saber o que aconteceu.
     */
    private function notifyPublishResult(int $siteId, int $articleId, bool $success, string $detail): void
    {
        $article = $this->articles->find($siteId, $articleId);
        $title = $article !== null && !empty($article['title']) ? (string) $article['title'] : 'Rascunho #' . $articleId;
        $link = '/sites/' . $siteId . '/production/' . $articleId . '#agendar';

        $this->notifications->notifySiteTeam(
            $siteId,
            $success ? NotificationService::TYPE_PUBLISH_SUCCESS : NotificationService::TYPE_PUBLISH_FAILED,
            $success ? 'Post publicado' : 'Falha ao publicar',
            "\"{$title}\" — {$detail}",
            $link,
        );
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

    /** Upload de várias imagens ao WordPress numa requisição só pode passar de 30 s. */
    private function raiseLimit(): void
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
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

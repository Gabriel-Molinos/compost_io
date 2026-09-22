<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\NotificationService;
use App\Support\Csrf;
use App\Support\Http;
use App\View;

final class NotificationController
{
    private NotificationService $notifications;

    public function __construct()
    {
        $this->notifications = new NotificationService();
    }

    public function index(): void
    {
        $userId = (int) AuthService::id();

        View::render('notifications/index', [
            'title'         => 'Notificações',
            'notifications' => $this->notifications->listForUser($userId),
        ]);
    }

    public function markAllRead(): void
    {
        $userId = (int) AuthService::id();

        // A tela de notificações chama por fetch (sem recarregar) e pede JSON; o formulário puro continua funcionando.
        if ($this->wantsJson()) {
            if (!$this->jsonCsrfOk()) {
                return;
            }
            $this->notifications->markAllRead($userId);
            $this->json(['ok' => true, 'unread' => 0]);
            return;
        }

        Csrf::verify();
        $this->notifications->markAllRead($userId);
        Http::redirect('/notifications');
    }

    /** Marca UMA como lida sem sair da tela (fetch → JSON). */
    public function read(string $id): void
    {
        $this->toggle((int) $id, true);
    }

    /** Volta UMA pra não lida (fetch → JSON). */
    public function unread(string $id): void
    {
        $this->toggle((int) $id, false);
    }

    private function toggle(int $id, bool $read): void
    {
        if (!$this->jsonCsrfOk()) {
            return;
        }
        $userId = (int) AuthService::id();
        if ($this->notifications->find($id, $userId) === null) {
            http_response_code(404);
            $this->json(['ok' => false, 'error' => 'Notificação não encontrada.']);
            return;
        }

        $read ? $this->notifications->markRead($id, $userId) : $this->notifications->markUnread($id, $userId);
        $this->json(['ok' => true, 'id' => $id, 'read' => $read, 'unread' => $this->notifications->unreadCount($userId)]);
    }

    /**
     * Polling do sino (assets/js/notification-toast.js) — GET simples,
     * chamado a cada N segundos enquanto o usuário está com o app aberto.
     * Sem `after` (primeira chamada da página): só devolve o id mais
     * recente, pra servir de baseline — nunca lista nada aqui, senão toda
     * notificação não lida viraria pop-up de novo a cada F5. Com `after`:
     * devolve só o que é mais novo que esse id (o que efetivamente vira
     * pop-up + som no cliente).
     */
    public function poll(): void
    {
        $userId = (int) AuthService::id();
        $after = isset($_GET['after']) && ctype_digit((string) $_GET['after']) ? (int) $_GET['after'] : null;

        if ($after === null) {
            $this->json([
                'ok'            => true,
                'latestId'      => $this->notifications->latestId($userId),
                'notifications' => [],
                'unread'        => $this->notifications->unreadCount($userId),
            ]);
            return;
        }

        $fresh = $this->notifications->newerThan($userId, $after);
        $latestId = $fresh !== [] ? (int) $fresh[array_key_last($fresh)]['id'] : $after;

        $this->json([
            'ok'            => true,
            'latestId'      => $latestId,
            'notifications' => array_map(static fn (array $n): array => [
                'id'      => (int) $n['id'],
                'type'    => (string) $n['type'],
                'title'   => (string) $n['title'],
                'message' => (string) $n['message'],
                'link'    => $n['link'] !== null ? (string) $n['link'] : null,
            ], $fresh),
            'unread'        => $this->notifications->unreadCount($userId),
        ]);
    }

    private function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    /** Mesmo padrão do reagendamento do calendário: token no corpo (`_token`), erro em JSON. */
    private function jsonCsrfOk(): bool
    {
        if (Csrf::check($_POST['_token'] ?? null)) {
            return true;
        }
        http_response_code(419);
        $this->json(['ok' => false, 'error' => 'Sessão expirada — recarregue a página.']);

        return false;
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /** Marca como lida e leva pro link salvo — é o que o sininho e a lista usam pra abrir uma notificação. */
    public function open(string $id): void
    {
        Csrf::verify();
        $userId = (int) AuthService::id();
        $notification = $this->notifications->find((int) $id, $userId);
        if ($notification === null) {
            (new ErrorController())->show(404);
            return;
        }

        $this->notifications->markRead((int) $notification['id'], $userId);
        Http::redirect($notification['link'] !== null ? (string) $notification['link'] : '/notifications');
    }
}

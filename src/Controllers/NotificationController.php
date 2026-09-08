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
        Csrf::verify();
        $this->notifications->markAllRead((int) AuthService::id());
        Http::redirect('/notifications');
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

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\PlatformFeedbackService;
use App\Services\SiteService;
use App\View;

final class HomeController
{
    public function index(): void
    {
        $user = AuthService::user();

        $sites = [];
        $sitesTotal = 0;
        $notifications = [];
        $unreadNotifications = 0;
        $pendingFeedback = 0;
        if ($user !== null) {
            $isAdmin = $user['role'] === 'ADMIN';
            $siteService = new SiteService();
            $all = $isAdmin ? $siteService->all() : $siteService->forUser((int) $user['id']);
            $sitesTotal = count($all);
            $sites = array_slice($all, 0, 6); // cartão-grid embaixo, cap visual — "ver todos" cobre o resto

            // Prévia da tela de Início (pedido do responsável, 2026-09-21: "adiciona os botões de
            // feedback e uma sessão de notificações"). Só as 4 mais recentes — a lista completa é
            // em /notifications; o pendente de feedback só importa pro ADMIN (é quem revisa).
            $notificationService = new NotificationService();
            $notifications = $notificationService->listForUser((int) $user['id'], 4);
            $unreadNotifications = $notificationService->unreadCount((int) $user['id']);
            if ($isAdmin) {
                $pendingFeedback = (new PlatformFeedbackService())->countPending();
            }
        }

        View::render('home/index', [
            'title'               => 'Início',
            'user'                => $user,
            'sites'               => $sites,
            'sitesTotal'          => $sitesTotal,
            'notifications'       => $notifications,
            'unreadNotifications' => $unreadNotifications,
            'pendingFeedback'     => $pendingFeedback,
        ]);
    }

    /** Endpoint interno JSON — checagem de saúde do banco. */
    public function databaseHealth(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['data' => $this->databaseStatus()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /** @return array{connected: bool, tables?: int, users?: int, error?: string} */
    private function databaseStatus(): array
    {
        try {
            $pdo = Connection::get();

            $tables = (int) $pdo->query(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
            )->fetchColumn();

            $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

            return ['connected' => true, 'tables' => $tables, 'users' => $users];
        } catch (\Throwable $e) {
            error_log('DB health check failed: ' . $e->getMessage());

            return ['connected' => false, 'error' => 'não foi possível consultar o banco'];
        }
    }
}

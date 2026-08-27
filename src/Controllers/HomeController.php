<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Services\AuthService;
use App\View;

final class HomeController
{
    public function index(): void
    {
        View::render('home/index', [
            'title' => 'Início',
            'user'  => AuthService::user(),
            'db'    => $this->databaseStatus(),
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

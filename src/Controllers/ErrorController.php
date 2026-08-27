<?php

declare(strict_types=1);

namespace App\Controllers;

use App\View;

final class ErrorController
{
    private const MESSAGES = [
        401 => 'Você precisa entrar para acessar esta página.',
        403 => 'Você não tem permissão para acessar esta página.',
        404 => 'Essa página não existe.',
    ];

    public function notFound(): void
    {
        $this->show(404);
    }

    public function show(int $status): void
    {
        http_response_code($status);

        View::render('errors/generic', [
            'title'   => (string) $status,
            'status'  => $status,
            'message' => self::MESSAGES[$status] ?? 'Ocorreu um erro.',
        ]);
    }
}

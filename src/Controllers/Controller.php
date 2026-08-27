<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\SiteService;

abstract class Controller
{
    /**
     * Garante que o usuário atual pode operar o site. Aborta com 404 se o site
     * não existe, 403 se existe mas o usuário não tem acesso.
     *
     * @return array<string, mixed> o site
     */
    protected function requireSite(string $id): array
    {
        $site = (new SiteService())->find((int) $id);

        if ($site === null) {
            (new ErrorController())->show(404);
            exit;
        }

        if (!AuthService::canAccessSite((int) $site['id'])) {
            (new ErrorController())->show(403);
            exit;
        }

        return $site;
    }

    protected function notFound(): never
    {
        (new ErrorController())->show(404);
        exit;
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

/** Rótulos legíveis para valores de enum exibidos na interface. */
final class Labels
{
    public static function role(string $role): string
    {
        return match ($role) {
            'ADMIN'         => 'Administrador',
            'REDATOR_CHEFE' => 'Redator-Chefe',
            default         => $role,
        };
    }
}

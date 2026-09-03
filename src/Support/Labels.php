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

    public static function articleStatus(string $status): string
    {
        return match ($status) {
            'PLANNED'            => 'Planejado',
            'IN_PROGRESS'        => 'Em produção',
            'IN_REVIEW'          => 'Em revisão',
            'REVISION_REQUESTED' => 'Revisão pedida',
            'APPROVED'           => 'Aprovado',
            'SCHEDULED'          => 'Agendado',
            'PUBLISHED'          => 'Publicado',
            'DISCARDED'          => 'Descartado',
            'BLOCKED'            => 'Bloqueado',
            'ERROR'              => 'Falha técnica',
            default             => $status,
        };
    }
}

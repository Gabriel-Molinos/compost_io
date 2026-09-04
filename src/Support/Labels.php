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

    /** Badge pronta (HTML) pro perfil de um usuário — ADMIN em destaque (ciano), Redator-Chefe neutro. */
    public static function roleBadge(string $role): string
    {
        return self::badge(self::role($role), $role === 'ADMIN' ? 'cyan' : 'muted');
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

    /**
     * Tom semântico do status (pra badge colorida — R-UI-07: cor nunca é a
     * única pista, a badge sempre carrega o texto do status junto).
     * `success`/`warning`/`danger`/`info` mapeiam direto pras cores da
     * identidade visual; `cyan`/`muted` cobrem os estados neutros/de espera.
     */
    public static function articleStatusTone(string $status): string
    {
        return match ($status) {
            'IN_PROGRESS'                    => 'info',
            'IN_REVIEW', 'SCHEDULED'         => 'cyan',
            'REVISION_REQUESTED'             => 'warning',
            'APPROVED', 'PUBLISHED'          => 'success',
            'BLOCKED', 'ERROR'               => 'danger',
            'PLANNED', 'DISCARDED'           => 'muted',
            default                          => 'muted',
        };
    }

    /** Classes Tailwind (fundo + texto) do tom — usado pela badge e em qualquer outro indicador colorido. */
    public static function toneClasses(string $tone): string
    {
        return match ($tone) {
            'success' => 'bg-success/15 text-success',
            'warning' => 'bg-warning/15 text-warning',
            'danger'  => 'bg-danger/15 text-danger',
            'info'    => 'bg-info/15 text-info',
            'cyan'    => 'bg-cyan/15 text-cyan',
            default   => 'bg-border/40 text-text-secondary', // muted
        };
    }

    /** Badge pronta (HTML) pro status de um artigo — texto + cor, nunca só cor (R-UI-07). */
    public static function articleStatusBadge(string $status): string
    {
        return self::badge(self::articleStatus($status), self::articleStatusTone($status));
    }

    public static function scheduleStatus(string $status): string
    {
        return match ($status) {
            'PENDING'   => 'Agendado',
            'PUBLISHED' => 'Publicado',
            'FAILED'    => 'Falhou',
            default     => $status,
        };
    }

    public static function scheduleStatusTone(string $status): string
    {
        return match ($status) {
            'PUBLISHED' => 'success',
            'FAILED'    => 'danger',
            default     => 'cyan', // PENDING
        };
    }

    /** Badge pronta (HTML) pro status de um agendamento — mesmo formato de articleStatusBadge(). */
    public static function scheduleStatusBadge(string $status): string
    {
        return self::badge(self::scheduleStatus($status), self::scheduleStatusTone($status));
    }

    /**
     * Badge pronta (HTML) pra um booleano ativo/inativo — mesmo formato das
     * outras badges. `$feminine` pra concordância com substantivos femininos
     * (ex.: "lição ativa/desativada" em memory/index.php).
     */
    public static function activeBadge(bool $active, bool $feminine = false): string
    {
        $label = $feminine
            ? ($active ? 'Ativa' : 'Desativada')
            : ($active ? 'Ativo' : 'Inativo');

        return self::badge($label, $active ? 'success' : 'muted');
    }

    /** Monta o HTML comum de badge (bolinha + texto) — usado por todas as badges acima. */
    private static function badge(string $label, string $tone): string
    {
        return '<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ' . self::toneClasses($tone) . '">'
            . '<span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current"></span>'
            . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</span>';
    }
}

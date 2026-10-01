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
     * O que a IA está fazendo agora num rascunho que ainda está gerando (card
     * trancado da Produção) — `null` = nenhum passo começou ainda (na fila).
     */
    public static function generationStep(?string $step): string
    {
        return match ($step) {
            null           => 'Na fila, aguardando a IA',
            'planning'     => 'Planejando o artigo',
            'research'     => 'Pesquisando fontes',
            'writing'      => 'Escrevendo o texto',
            'seo'          => 'Ajustando SEO',
            'compliance'   => 'Checando conformidade',
            'review'       => 'Revisão da IA',
            'image'        => 'Gerando as imagens',
            default        => 'Finalizando',
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

    /**
     * Classe do card de rascunho na lista de Produção (fundo colorido + animação
     * por estado — `.article-card--*` em src/styles/input.css). Parte do MESMO
     * tom da badge, então card e badge nunca discordam. Os nomes ficam por
     * extenso aqui de propósito: o scanner do Tailwind só mantém regra de
     * `@layer components` cujo nome aparece literal num arquivo de `content`
     * — montar `'article-card--' . $tone` sumiria com o CSS sem erro nenhum.
     */
    public static function articleCardTone(string $tone): string
    {
        return match ($tone) {
            'success' => 'article-card--success',
            'warning' => 'article-card--warning',
            'danger'  => 'article-card--danger',
            'info'    => 'article-card--info',
            'cyan'    => 'article-card--cyan', // fundo continua preto; só a luz da animação é ciano
            default   => 'article-card--muted',
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

    /** Tom semântico do tipo de notificação (NotificationService::TYPE_*). */
    public static function notificationTone(string $type): string
    {
        return match ($type) {
            'PUBLISH_SUCCESS' => 'success',
            'PUBLISH_FAILED'  => 'danger',
            'SITE_ASSIGNED'   => 'cyan',
            'ATTENTION'       => 'warning',
            'ARTICLE_READY'   => 'success',
            default           => 'muted',
        };
    }

    /** Rótulo curto do tipo de notificação — a etiqueta ao lado do título. */
    public static function notificationTypeLabel(string $type): string
    {
        return match ($type) {
            'PUBLISH_SUCCESS' => 'Publicação',
            'PUBLISH_FAILED'  => 'Falha',
            'SITE_ASSIGNED'   => 'Site',
            'ATTENTION'       => 'Atenção',
            'ARTICLE_READY'   => 'Rascunho',
            default           => 'Aviso',
        };
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

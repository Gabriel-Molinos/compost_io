<?php

declare(strict_types=1);

namespace App\Support;

use App\View;

/**
 * Pixelito — o mascote-ajudante do COMPOST (pedido do responsável, 2026-09-25).
 *
 * Uma única fonte de verdade sobre QUAL expressão aparece em QUAL momento: o
 * JS (pop-up de notificação, tutorial) recebe este mesmo mapa via
 * `window.COMPOST_PIXELITO` (ver `jsConfig()`), então nunca existe uma 2ª cópia
 * pra ficar dessincronizada — o problema que os mapas de cor por tipo de
 * notificação já têm (Labels, notification-toast.js, notifications/index.php).
 *
 * As imagens ficam em public/assets/pixelito/<expressão>.webp — o nome do
 * arquivo É o nome da expressão. Todas mostram só a cabeça (quadrado 1080 px,
 * fundo branco) e são recortadas em círculo por `.pixelito-bubble` (input.css).
 */
final class Pixelito
{
    public const BASE = '/assets/pixelito/';

    /** Rosto neutro, boca fechada — usado quando não há motivo pra outra expressão. */
    public const DEFAULT = 'normal';

    /** Nomes válidos = arquivos existentes em public/assets/pixelito/. */
    public const EXPRESSIONS = [
        'normal',             // boca fechada, olhar neutro
        'falando',            // falando, olhar neutro
        'falando-confiante',  // falando, sobrancelha firme
        'falando-orgulhoso',  // falando, olhos sorridentes
        'orgulhoso',          // boca fechada, olhos sorridentes
        'relaxado',           // falando, pálpebra caída
        'sem-animo',          // boca reta, pálpebra caída
    ];

    /** Tipo de notificação (NotificationService::TYPE_*) → expressão. */
    private const BY_NOTIFICATION_TYPE = [
        'PUBLISH_SUCCESS' => 'falando-orgulhoso', // "deu certo!"
        'PUBLISH_FAILED'  => 'sem-animo',         // "ai, não deu"
        'ATTENTION'       => 'falando-confiante', // "presta atenção nisso"
        'SITE_ASSIGNED'   => 'falando',           // "bem-vindo(a) a esse site"
        'ARTICLE_READY'   => 'orgulhoso',         // "olha o que ficou pronto"
        'FEEDBACK'        => 'relaxado',          // conversa tranquila
    ];

    public static function forNotificationType(string $type): string
    {
        return self::BY_NOTIFICATION_TYPE[$type] ?? self::DEFAULT;
    }

    /** URL da imagem; nome desconhecido cai na expressão padrão (nunca gera <img> quebrada). */
    public static function url(string $expression): string
    {
        $expression = in_array($expression, self::EXPRESSIONS, true) ? $expression : self::DEFAULT;

        return self::BASE . $expression . '.webp';
    }

    /**
     * A bolinha branca com o Pixelito dentro. Decorativa (`alt=""`): o texto que
     * acompanha (título da notificação, pergunta do guia…) já diz tudo — quem
     * precisa de nome acessível é o botão que a contém. Sem `loading="lazy"` de
     * propósito: são só 7 arquivos de ~12 KB repetidos em toda página, e o lazy
     * deixava a bolinha do painel (escondido) branca por um instante ao abrir.
     *
     * @param 'sm'|'md'|'lg'|'xl' $size
     */
    public static function bubble(string $expression, string $size = 'md', string $extraClass = ''): string
    {
        return '<span class="pixelito-bubble pixelito-bubble--' . View::e($size) . ($extraClass !== '' ? ' ' . View::e($extraClass) : '') . '">'
            . '<img src="' . View::e(self::url($expression)) . '" alt="" width="96" height="96" decoding="async" draggable="false">'
            . '</span>';
    }

    /**
     * Configuração pro JS (`window.COMPOST_PIXELITO`): pasta das imagens, mapa
     * tipo→expressão e a expressão padrão.
     *
     * @return array{base: string, default: string, byType: array<string,string>}
     */
    public static function jsConfig(): array
    {
        return ['base' => self::BASE, 'default' => self::DEFAULT, 'byType' => self::BY_NOTIFICATION_TYPE];
    }
}

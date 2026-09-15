<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Defesa CSRF do POST que o Google Identity Services faz direto pra
 * `public/callback.php` (modo redirect, `data-login_uri`) — esse POST não é
 * um `<form>` deste app, então não carrega o `_token` de `App\Support\Csrf`.
 * O próprio GIS grava um cookie `g_csrf_token` e repete o mesmo valor num
 * campo do corpo do POST (double-submit) — comparar os dois é a única
 * defesa CSRF disponível pra este endpoint específico.
 */
final class GoogleCsrf
{
    public static function tokensMatch(?string $cookieToken, ?string $bodyToken): bool
    {
        return is_string($cookieToken) && is_string($bodyToken) && $cookieToken !== ''
            && hash_equals($cookieToken, $bodyToken);
    }
}

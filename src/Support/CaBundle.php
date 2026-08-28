<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolve o caminho de um bundle de certificados CA para chamadas HTTPS de
 * saída (cURL). O PHP nesta máquina usa OpenSSL sem `curl.cainfo` configurado,
 * então o Windows não fornece as raízes automaticamente — o projeto versiona
 * `tools/cacert.pem` (bundle da Mozilla, via curl.se) como fallback.
 */
final class CaBundle
{
    public static function path(): string
    {
        foreach ([ini_get('curl.cainfo'), ini_get('openssl.cafile')] as $configured) {
            if (is_string($configured) && $configured !== '' && is_file($configured)) {
                return $configured;
            }
        }

        return dirname(__DIR__, 2) . '/tools/cacert.pem';
    }
}

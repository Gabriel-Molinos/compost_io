<?php

declare(strict_types=1);

namespace App\Support;

use App\Config\Env;
use RuntimeException;

/**
 * Cifragem simétrica autenticada de dados sensíveis em repouso
 * (docs/technical/seguranca.md §47). Usa libsodium (`sodium_crypto_secretbox`,
 * XSalsa20-Poly1305) com chave única `APP_KEY` do `.env`.
 *
 * Formato do blob: `nonce (24 bytes) || ciphertext`, bytes crus — cabe em
 * `VARBINARY(512)`. Nunca logar o texto claro nem a chave.
 */
final class Crypto
{
    public static function encrypt(string $plaintext): string
    {
        self::assertAvailable();

        $key = self::key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
        sodium_memzero($key);

        return $nonce . $cipher;
    }

    public static function decrypt(string $blob): string
    {
        self::assertAvailable();

        $nonceBytes = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($blob) <= $nonceBytes) {
            throw new RuntimeException('Blob cifrado inválido (curto demais).');
        }

        $key = self::key();
        $plain = sodium_crypto_secretbox_open(
            substr($blob, $nonceBytes),
            substr($blob, 0, $nonceBytes),
            $key,
        );
        sodium_memzero($key);

        if ($plain === false) {
            throw new RuntimeException('Falha ao decifrar: APP_KEY incorreta ou dado corrompido.');
        }

        return $plain;
    }

    private static function assertAvailable(): void
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('Extensão PHP `sodium` não está habilitada (necessária para cifrar credenciais).');
        }
    }

    private static function key(): string
    {
        $raw = trim((string) Env::get('APP_KEY', ''));
        if ($raw === '') {
            throw new RuntimeException('APP_KEY não configurada no .env. Gere com: php -r "echo base64_encode(random_bytes(32));"');
        }

        $key = base64_decode($raw, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('APP_KEY inválida: esperado base64 de 32 bytes.');
        }

        return $key;
    }
}

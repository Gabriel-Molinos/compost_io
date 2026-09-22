<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use App\Services\AuthService;
use App\Support\Csrf;
use App\Support\Session;
use PHPUnit\Framework\TestCase;

/**
 * Base pra testes HTTP-de-verdade contra Controllers: sobe o servidor
 * embutido do PHP (o mesmo `php -S` usado em dev) numa porta livre, no
 * `setUpBeforeClass()` da subclasse, e derruba no `tearDownAfterClass()`.
 *
 * Por que HTTP de verdade e não instanciar o Controller direto: quase toda
 * ação de escrita termina em `Http::redirect()`, que chama `exit` de
 * verdade — em processo (PHPUnit normal) isso mata o test runner inteiro, e
 * mesmo com `@runInSeparateProcess` qualquer assert DEPOIS da chamada nunca
 * roda (o processo morre antes do PHPUnit conseguir serializar o
 * resultado). Batendo via HTTP no servidor embutido, `exit()`/`header()`
 * fazem exatamente o que fariam em produção — sem gambiarra, e cobre
 * Controller + rota + CSRF + auth juntos, como um usuário real bateria.
 *
 * Cobre o "ainda não implementado: cobertura de Controllers ... E2E de
 * navegador" de docs/technical/testes-e-observabilidade.md §92 — as duas
 * lacunas de uma vez, já que aqui é HTTP real de ponta a ponta.
 */
abstract class HttpServerTestCase extends TestCase
{
    /** @var resource|null */
    private static $process = null;

    /** @var list<resource> */
    private static array $pipes = [];

    protected static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        $port = 8100 + random_int(0, 300); // faixa alta, evita colidir com servidor de dev (8000) já ligado
        self::$baseUrl = "http://127.0.0.1:{$port}";
        $root = dirname(__DIR__, 3);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root . '/public'],
            $descriptors,
            $pipes,
            $root,
        );

        if ($process === false) {
            self::fail('Não consegui subir o servidor embutido do PHP pro teste HTTP.');
        }
        self::$process = $process;
        self::$pipes = $pipes;
        foreach (self::$pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        // Espera o servidor aceitar conexão de verdade (até 5s) — sem isso a
        // primeira request da suíte pode chegar antes do listener existir.
        $deadline = microtime(true) + 5.0;
        $up = false;
        while (microtime(true) < $deadline) {
            $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if (is_resource($conn)) {
                fclose($conn);
                $up = true;
                break;
            }
            usleep(100_000);
        }
        if (!$up) {
            self::tearDownAfterClass();
            self::fail('Servidor embutido do PHP não respondeu a tempo (porta ' . $port . ').');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$process !== null && is_resource(self::$process)) {
            foreach (self::$pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_terminate(self::$process);
            proc_close(self::$process);
        }
        self::$process = null;
        self::$pipes = [];
    }

    /**
     * Sessão de admin forjada (mesma técnica documentada na memória do
     * projeto pra print de tela local — CLI e `php -S` compartilham o mesmo
     * `session.save_path` por padrão) + token CSRF real dessa sessão. Fecha
     * a sessão antes de devolver: senão o lock de arquivo da sessão do PHP
     * trava a request HTTP que o teste for fazer em seguida (mesma sessão,
     * processo diferente disputando o mesmo arquivo).
     *
     * @return array{cookie: string, token: string}
     */
    protected function forgeAdminSession(int $userId = 1): array
    {
        Session::start();
        AuthService::login(['id' => $userId]);
        $sessionId = session_id();
        $token = Csrf::token();
        session_write_close();

        return ['cookie' => 'compost_session=' . $sessionId, 'token' => $token];
    }

    /** @param array<string, mixed> $fields @return array{status:int, headers:array<string,string>, body:string} */
    protected function httpPost(string $path, array $fields, string $cookie): array
    {
        return $this->http('POST', $path, $fields, $cookie);
    }

    /** @return array{status:int, headers:array<string,string>, body:string} */
    protected function httpGet(string $path, string $cookie = ''): array
    {
        return $this->http('GET', $path, [], $cookie);
    }

    /** @param array<string, mixed> $fields @return array{status:int, headers:array<string,string>, body:string} */
    private function http(string $method, string $path, array $fields, string $cookie): array
    {
        $ch = curl_init(self::$baseUrl . $path);
        $headers = $cookie !== '' ? ['Cookie: ' . $cookie] : [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $method === 'POST' ? http_build_query($fields) : null,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->fail('cURL falhou: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr((string) $raw, 0, $headerSize);
        $body = substr((string) $raw, $headerSize);

        $parsedHeaders = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $parsedHeaders[strtolower(trim($k))] = trim($v);
            }
        }

        return ['status' => $status, 'headers' => $parsedHeaders, 'body' => $body];
    }
}

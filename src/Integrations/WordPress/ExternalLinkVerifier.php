<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use App\Support\CaBundle;
use DOMDocument;
use DOMElement;

/**
 * Confere se os links EXTERNOS que a IA cita no corpo do artigo existem de
 * verdade, antes de publicar (achado real, 2026-09-09: a IA escreve "cite a
 * URL exata que veio da pesquisa", mas nada verificava — quando o passo de
 * pesquisa "lembrava" uma URL plausível em vez de usar uma real, ela ia pro
 * artigo publicado sem ninguém checar, e o leitor caía num 404).
 *
 * Mesma ideia do `InternalLinkResolver` (link que não bate com nada real
 * perde o `<a>`, mas o TEXTO fica — nunca publica um link morto, nunca
 * também some com a citação inteira), só que a verificação aqui é uma
 * requisição HTTP de verdade, não uma comparação de slug contra o
 * WordPress. Só desembrulha em sinal de alta confiança de que o link está
 * morto (404/410, DNS não resolve, conexão recusada/timeout).
 *
 * Achado real #2 (2026-09-09, mesmo dia): um 403/429/500 é ambíguo — muito
 * comum em domínio grande com proteção de bot (Akamai/Cloudflare bloqueiam
 * QUALQUER requisição automatizada, `curl` ou até um Chrome de verdade
 * headless, com o MESMO 403 de "Access Denied", pág real ou não — testado
 * contra tesla.com: três URLs, uma real e confirmada (`/powerwall`) e duas
 * nunca confirmadas de outra forma, todas voltaram o mesmíssimo bloqueio).
 * Ou seja: pra esses domínios, HTTP sozinho **não decide nada** — nem "existe"
 * nem "não existe". A versão anterior tratava esse caso como "mantém e
 * segue" silenciosamente, o que na prática escondia do humano revisor
 * exatamente os casos que mais precisavam de um olhar manual. Agora esses
 * casos voltam num terceiro grupo (`ambiguous`) pro chamador avisar
 * explicitamente "não consegui confirmar isto, confira você mesmo" — nunca
 * decide por adivinhação.
 */
final class ExternalLinkVerifier
{
    private const CONNECT_TIMEOUT = 4;
    private const TOTAL_TIMEOUT = 7;
    // UA de navegador real (não "compatible; bot") — evita bloqueio trivial
    // por filtro de User-Agent em sites com proteção simples. Não resolve
    // Akamai/Cloudflare (confirmado: bloqueiam até Chrome headless de
    // verdade com o mesmo 403), mas recupera o meio-termo.
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';
    private const RETRY_BACKOFF_SECONDS = 1.5;

    private const ALIVE = 'alive';
    private const DEAD = 'dead';
    private const AMBIGUOUS = 'ambiguous';
    private const RETRY_WITH_GET = 'retry';
    private const RETRY_RATE_LIMITED = 'retry_rate_limited';

    /** @var array<string, string> URL => self::ALIVE|self::DEAD|self::AMBIGUOUS */
    private array $cache = [];

    /** @return array{html:string, checked:int, unwrapped:int, ambiguous:list<string>, dead:list<string>} */
    public function verify(string $html): array
    {
        if (trim($html) === '' || stripos($html, '<a') === false) {
            return ['html' => $html, 'checked' => 0, 'unwrapped' => 0, 'ambiguous' => [], 'dead' => []];
        }

        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root__">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('__root__');
        if ($root === null) {
            return ['html' => $html, 'checked' => 0, 'unwrapped' => 0, 'ambiguous' => [], 'dead' => []];
        }

        $checked = 0;
        $unwrapped = 0;
        $ambiguous = [];
        $dead = [];

        foreach (iterator_to_array($doc->getElementsByTagName('a')) as $a) {
            if (!$a instanceof DOMElement) {
                continue;
            }

            $href = trim($a->getAttribute('href'));
            $scheme = parse_url($href, PHP_URL_SCHEME);
            if ($scheme === null || !in_array(strtolower($scheme), ['http', 'https'], true)) {
                continue; // não é http(s) — nada a checar aqui (interno já resolvido antes, mailto/tel/âncora)
            }

            $checked++;
            $status = $this->classify($href);

            if ($status === self::DEAD) {
                while ($a->firstChild !== null) {
                    $a->parentNode?->insertBefore($a->firstChild, $a);
                }
                $a->parentNode?->removeChild($a);
                $unwrapped++;
                $dead[] = $href;
            } elseif ($status === self::AMBIGUOUS) {
                $ambiguous[] = $href;
            }
        }

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return ['html' => trim($out), 'checked' => $checked, 'unwrapped' => $unwrapped, 'ambiguous' => $ambiguous, 'dead' => $dead];
    }

    /** @return self::ALIVE|self::DEAD|self::AMBIGUOUS */
    private function classify(string $url): string
    {
        if (array_key_exists($url, $this->cache)) {
            return $this->cache[$url];
        }

        $result = $this->request($url, head: true);
        if ($result === self::RETRY_WITH_GET) {
            // Alguns servidores rejeitam HEAD especificamente (405/501) mesmo
            // com a página existindo — confirma com GET antes de concluir.
            $result = $this->request($url, head: false);
            if ($result === self::RETRY_WITH_GET) {
                $result = self::AMBIGUOUS; // nem GET deu um status conclusivo
            }
        }
        if ($result === self::RETRY_RATE_LIMITED) {
            // 429/503 costuma ser rate-limit passageiro, não bloqueio de
            // verdade — vale uma segunda tentativa antes de desistir.
            usleep((int) (self::RETRY_BACKOFF_SECONDS * 1_000_000));
            $result = $this->request($url, head: true);
            if ($result === self::RETRY_WITH_GET) {
                $result = $this->request($url, head: false);
            }
            if ($result === self::RETRY_WITH_GET || $result === self::RETRY_RATE_LIMITED) {
                $result = self::AMBIGUOUS;
            }
        }

        return $this->cache[$url] = $result;
    }

    /** @return self::ALIVE|self::DEAD|self::AMBIGUOUS|self::RETRY_WITH_GET */
    private function request(string $url, bool $head): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => $head,
            CURLOPT_HTTPGET        => !$head,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TOTAL_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CAINFO         => CaBundle::path(),
            CURLOPT_USERAGENT      => self::USER_AGENT,
        ]);
        if (!$head) {
            // GET só precisa confirmar o status, não o corpo inteiro.
            curl_setopt($ch, CURLOPT_RANGE, '0-2047');
        }

        curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return self::DEAD; // DNS, conexão recusada, timeout, TLS — sem resposta HTTP nenhuma
        }
        if ($status === 404 || $status === 410) {
            return self::DEAD;
        }
        if ($status === 405 || $status === 501 || $status === 0) {
            return self::RETRY_WITH_GET;
        }
        if ($status === 429 || $status === 503) {
            return self::RETRY_RATE_LIMITED;
        }
        if ($status >= 200 && $status < 400) {
            return self::ALIVE;
        }

        return self::AMBIGUOUS; // 403/500/502... — bloqueio comum, não decide nada por si só
    }
}

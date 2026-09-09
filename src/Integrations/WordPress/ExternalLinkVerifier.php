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
 * WordPress. Só desembrulha em sinal de alta confiança de que o link
 * está morto (404/410, DNS não resolve, conexão recusada/timeout) — um
 * 403/429/500 é ambíguo (comum em servidor que bloqueia acesso automatizado
 * mesmo a página existindo pra humano de verdade) e fica como está, pra não
 * arriscar remover uma fonte legítima por falso positivo.
 */
final class ExternalLinkVerifier
{
    private const CONNECT_TIMEOUT = 4;
    private const TOTAL_TIMEOUT = 7;
    private const USER_AGENT = 'Mozilla/5.0 (compatible; COMPOST-LinkCheck/1.0)';

    /** @var array<string, bool> URL => existe (true) ou morto (false) */
    private array $cache = [];

    /** @return array{html:string, checked:int, unwrapped:int} */
    public function verify(string $html): array
    {
        if (trim($html) === '' || stripos($html, '<a') === false) {
            return ['html' => $html, 'checked' => 0, 'unwrapped' => 0];
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
            return ['html' => $html, 'checked' => 0, 'unwrapped' => 0];
        }

        $checked = 0;
        $unwrapped = 0;

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
            if ($this->isDead($href)) {
                while ($a->firstChild !== null) {
                    $a->parentNode?->insertBefore($a->firstChild, $a);
                }
                $a->parentNode?->removeChild($a);
                $unwrapped++;
            }
        }

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return ['html' => trim($out), 'checked' => $checked, 'unwrapped' => $unwrapped];
    }

    private function isDead(string $url): bool
    {
        if (array_key_exists($url, $this->cache)) {
            return !$this->cache[$url];
        }

        $alive = $this->request($url, head: true);
        if ($alive === null) {
            // Alguns servidores rejeitam HEAD especificamente (405/501) mesmo
            // com a página existindo — confirma com GET antes de concluir.
            $alive = $this->request($url, head: false);
        }

        // null (erro de rede/DNS/timeout) tratado como morto, igual 404/410.
        $this->cache[$url] = $alive ?? false;

        return !$this->cache[$url];
    }

    /** true = existe, false = 404/410 confirmado, null = ambíguo/erro de rede (chamador decide). */
    private function request(string $url, bool $head): ?bool
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
            return null; // DNS, conexão recusada, timeout, TLS — sem resposta HTTP nenhuma
        }
        if ($status === 404 || $status === 410) {
            return false;
        }
        if ($status === 405 || $status === 501 || $status === 0) {
            return null; // método não suportado (tenta de novo com GET) ou sem status nenhum
        }

        return true; // 2xx/3xx e qualquer outro 4xx/5xx ambíguo (403/429/500...) — não desembrulha
    }
}

<?php

declare(strict_types=1);

namespace App\Cache;

use Predis\Client;
use Throwable;

/**
 * Cache-aside sobre o mesmo Redis/Memurai da fila (Fase 9/escala, achado
 * real 2026-09-14): `WordPressClient::listRecentPosts()`, `CostBudgetService`
 * e `ReportService` recalculavam tudo do zero em toda visita — ver
 * `docs/technical/cache.md` pra tabela completa de chave/TTL/origem.
 *
 * Nunca uma dependência: qualquer falha do Redis (não configurado, fora do
 * ar, erro de rede, URL malformada) cai silenciosamente pro cálculo real via
 * `$compute()` — cache só acelera, nunca pode quebrar nada. Por isso todo
 * `catch` aqui é `\Throwable`, não só a exceção de conexão do Predis.
 *
 * TODA chave escrita carrega TTL explícito (nunca um SET sem expiração) —
 * pré-requisito pra usar `maxmemory-policy volatile-lru` no Redis
 * compartilhado com a fila (ver docs/technical/cache.md) sem risco de a
 * política de despejo nunca livrar memória pras chaves de cache.
 */
final class CacheService
{
    private CacheConfig $config;
    private ?Client $client = null;

    public function __construct(?CacheConfig $config = null)
    {
        $this->config = $config ?? CacheConfig::fromEnv();

        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $this->client = new Client($this->config->url);
        } catch (Throwable) {
            // URL malformada ou config inválida — cache desabilitado, nunca quebra o resto.
            $this->client = null;
        }
    }

    /**
     * Lê do cache; em erro/não encontrado, calcula de verdade via
     * `$compute()`, grava com TTL e devolve. `$compute()` é chamado
     * exatamente como se não houvesse cache nenhum — suas próprias exceções
     * (ex. `WordPressException`) sobem normalmente pro chamador.
     *
     * @param callable(): mixed $compute
     */
    public function remember(string $key, int $ttlSeconds, callable $compute): mixed
    {
        $fullKey = $this->config->prefix . $key;

        if ($this->client !== null) {
            try {
                $cached = $this->client->get($fullKey);
                if ($cached !== null) {
                    return json_decode($cached, true, 512, JSON_THROW_ON_ERROR)['v'];
                }
            } catch (Throwable) {
                // Redis fora do ar / erro de rede / lixo no valor — ignora, calcula de verdade.
            }
        }

        $value = $compute();

        if ($this->client !== null) {
            try {
                // Envolvido em {"v": ...} de propósito: sem isso, cachear um
                // resultado `null` de verdade (ex. CostBudgetService sem
                // dados ainda) seria indistinguível de "não encontrado no
                // cache" — nunca cachearia esse caso, recalcularia sempre.
                $payload = json_encode(['v' => $value], JSON_THROW_ON_ERROR);
                $this->client->set($fullKey, $payload, 'EX', max(1, $ttlSeconds));
            } catch (Throwable) {
                // Falha ao gravar não é problema — só perde o ganho de cache desta vez.
            }
        }

        return $value;
    }

    /** Invalidação pontual — nenhum caller usa isso ainda (tudo expira só por TTL), existe pra uso futuro/testes. */
    public function forget(string $key): void
    {
        if ($this->client === null) {
            return;
        }

        try {
            $this->client->del([$this->config->prefix . $key]);
        } catch (Throwable) {
            // Sem problema — a chave só vai expirar pelo TTL normal.
        }
    }
}

# Cache — leituras repetidas e caras (Redis)

> Fatia de performance/escala. Complementa a fila de IA
> ([ADR-006](../decisions/adr-006-fila-redis.md)) — mesma instância
> Redis/Memurai, uso completamente separado (prefixo `cache:` nunca colide
> com `queue:jobs`/`queue:jobs:processing`/`queue:jobs:dead`). Resolve a
> pendência registrada em
> [testes-e-observabilidade.md §97](testes-e-observabilidade.md#97-performance-para-60-sites-fase-9)
> sobre o custo máximo global.

## Estado atual

`App\Cache\CacheService::remember(string $key, int $ttlSeconds, callable $compute)`
implementa o padrão **cache-aside**: tenta ler do Redis — acerto, devolve
direto; erro (sem `REDIS_URL`, Redis fora do ar, erro de rede, URL malformada)
ou chave não encontrada, calcula de verdade via `$compute()`, grava com TTL e
devolve. **Nunca lança exceção por causa do Redis** — cache é otimização,
nunca dependência (mesma postura já usada pra integrações externas opcionais,
ex. NotebookLM, `integracoes.md` §40.11). Exceções do PRÓPRIO `$compute()`
(ex. `WordPressException`) sobem normais — o cache só protege contra falha
dele mesmo, nunca engole erro de quem ele está envolvendo.

`App\Cache\CacheConfig::fromEnv()` reaproveita a mesma `REDIS_URL` da fila
(`App\Queue\RedisConfig`) — **sem variável de ambiente nova**. Vazia/ausente
= cache desabilitado (todo `remember()` vira um passthrough que sempre
calcula de verdade), nunca erro — diferente de `RedisConfig::fromEnv()`
(fila), que exige `REDIS_URL` quando `QUEUE_DRIVER=redis`.

Valores são serializados em JSON (não `serialize()` do PHP) — mais fácil de
inspecionar via `redis-cli`/RedisInsight. Envolvidos num wrapper `{"v": ...}`
de propósito: sem isso, cachear um resultado `null` de verdade (ex.
`CostBudgetService` sem nenhum dado ainda) seria indistinguível de "não
encontrado no cache" e nunca cachearia esse caso.

### O que está cacheado hoje

| Chave                                              | TTL                                | Origem                                             | Escopo               |
|-----------------------------------------------------|-------------------------------------|-----------------------------------------------------|-----------------------|
| `cache:wp:posts:{md5(baseUrl)}`                     | 5 min                                | `WordPressClient::listRecentPosts()`                 | por site WordPress    |
| `cache:cost:max_observed`                           | 10 min                               | `CostBudgetService::maxObservedCostPerArticle()`      | global                |
| `cache:report:current_spend:{siteId}:{period}`      | 2 min                                 | `ReportService::currentSpend()`                       | por site + período    |
| `cache:report:trend:{siteId}:{period}:{months}`     | 5 min                                 | `ReportService::trend()`                              | por site + período + janela |
| `cache:report:monthly:{siteId}:{period}`            | 2 min (mês corrente) / 24h (mês passado) | `ReportService::monthly()`                        | por site + período    |

**`monthly()` tem uma ressalva importante**: o campo `pending_now` (artigos
em revisão agora) é por definição um retrato do momento atual, não do
período pedido — mesmo pedindo o relatório de um mês já fechado. Por isso
esse campo fica **fora** do bloco cacheado e é sempre recalculado, mesmo
quando o resto da resposta vem do cache com TTL de 24h. Sem essa separação,
o número de pendentes ficaria congelado por até 24h ao navegar pra um mês
passado — um bug real de corretude, não só de performance.

**Invalidação: só por expiração (TTL)** — nenhuma das origens acima tem, hoje,
um caminho de escrita direto neste app que justifique invalidação manual (ex.:
um post novo no WordPress não passa por este app). `CacheService::forget(string $key)`
existe pra invalidação pontual futura, mas nenhum caller usa ainda.

## Configuração recomendada do Redis/Memurai

Mesma instância da fila ([ADR-006](../decisions/adr-006-fila-redis.md)) — mas
a fila guarda listas **sem TTL** (`queue:jobs`, `queue:jobs:processing`,
`queue:jobs:dead`) que nunca podem ser despejadas por pressão de memória
(perder um job da fila silenciosamente é inaceitável). Por isso a política de
despejo do servidor deve ser:

```
maxmemory-policy volatile-lru
```

(`redis.conf` ou `CONFIG SET maxmemory-policy volatile-lru`) — só despeja
chaves que **têm** TTL definido. Nunca usar `allkeys-lru`, que despejaria
qualquer chave sob pressão de memória, incluindo a lista de jobs pendentes.
Pré-requisito do lado da aplicação (já garantido por `CacheService::remember()`):
toda chave escrita pelo cache **sempre** carrega um TTL explícito — nunca um
`SET` sem expiração.

Isso é configuração de infraestrutura (`redis.conf`/`CONFIG SET`), fora do
escopo do código PHP.

## Verificação

- `bin/cache_smoke.php` — roda contra o Memurai local: hit depois de miss,
  expiração por TTL, cache de valor `null`, e fallback quando o Redis não
  responde (sem lançar nada).
- Instrumentação manual temporária (`error_log`) já confirmou, na prática,
  que `ProductionController::show()` — visitado várias vezes em sequência —
  dispara só 1 chamada real ao WordPress dentro da janela de 5 minutos.
- Comparação byte-a-byte (`diff`) da Visão Geral e da aba Relatórios com
  cache quente vs. cache limpo vs. cache desligado (`REDIS_URL` vazia) — sem
  nenhuma diferença de conteúdo, só de velocidade.

## Ver também

- [ADR-006 — fila Redis](../decisions/adr-006-fila-redis.md)
- [Fila de IA](fila-ia.md)
- [Testes e observabilidade §95 — controle de custo de IA](testes-e-observabilidade.md#95-controle-de-custo-de-ia-proposta)
- [Testes e observabilidade §97 — performance para 60+ sites](testes-e-observabilidade.md#97-performance-para-60-sites-fase-9)

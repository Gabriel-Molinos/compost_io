# Fila de IA — execução, retry e custo

> Fatia 4.3 do roadmap. Implementa [ADR-006](../decisions/adr-006-fila-redis.md),
> [retry §96](testes-e-observabilidade.md#96-política-de-retry--falha-da-ia-proposta)
> e [custo §95](testes-e-observabilidade.md#95-controle-de-custo-de-ia-proposta).

## Estado atual

Dois drivers: **`sync`** (padrão — jobs rodam inline, na mesma requisição que
os enfileira) e **`redis`** (fila de verdade via `predis/predis`, consumida
por `bin/worker.php`). Desde a Fase 9.1b, o pipeline editorial usa a fila de
verdade: `ProductionController::generate()`/`regenerate()` fazem só a parte
síncrona e rápida (`ArticlePipeline::prepareGenerate()`/`prepareRegenerate()`
— cria a linha do artigo) e despacham um `Job` (`article.generate` /
`article.regenerate`); quem chama IA de verdade (`runGenerate()`/
`runRegenerate()`) é o handler registrado por `App\Queue\ArticleJobHandlers`,
usado tanto pelo controller (driver `sync` — roda ali mesmo, inline) quanto
por `bin/worker.php` (driver `redis` — processa em segundo plano, com
`RetryPolicy::default()` em vez do backoff curto usado inline). `smoke.echo`
continua existindo só pra verificação isolada (`bin/queue_smoke.php`).

Falha definitiva do pipeline (retries esgotados) marca o artigo como
`ERROR` (migration `0010`) — visível na página do artigo, com o motivo vindo
do `ai_executions.error_message` do passo que falhou. Enquanto o artigo está
`PLANNED`/`IN_PROGRESS`, a página se atualiza sozinha (`<meta
http-equiv="refresh">`, sem JS).

## Peças

| Classe | Papel |
|---|---|
| `App\Queue\Job` | `{type, payload, id}` — unidade de trabalho; `toJson()`/`fromJson()` pra serializar |
| `App\Queue\QueueDriver` | interface do transporte (`push`, `reserve`, `name`) |
| `App\Queue\SyncQueueDriver` | executa o job no `push()`; `reserve()` não se aplica (lança) |
| `App\Queue\RedisQueueDriver` | `push()` = `LPUSH`; `reserve()` = `BRPOP` bloqueante (Fase 9.1) |
| `App\Queue\RedisConfig` | lê `REDIS_URL` do `.env` (`fromEnv()`, mesmo padrão de `GeminiConfig`) |
| `App\Queue\Queue` | registra handlers por tipo, `dispatch()` enfileira, `execute()` roda, `reserve()` delega ao driver |
| `App\Queue\ArticleJobHandlers` | registra `article.generate`/`article.regenerate` numa `Queue` — usado pelo controller (driver sync) e pelo worker (driver redis); marca `ERROR` em falha definitiva |
| `App\Queue\RetryPolicy` | 3 tentativas; backoff 30s→2min→10min em produção, 1s→2s→4s fora |
| `App\Queue\RetryRunner` | roda uma operação repetindo só em `AIException::$retryable` |
| `App\Services\AiExecutionService` | ciclo de `ai_executions`: QUEUED→RUNNING→RETRYING→SUCCESS/FAILED |
| `App\Integrations\Gemini\GeminiPricing` | estima `cost` (USD) a partir dos tokens do `AIResult` |

## Configuração (`.env`)

| Variável | Valor | Efeito |
|---|---|---|
| `QUEUE_DRIVER` | `sync` (padrão) ou `redis` | `redis` exige `bin/worker.php` rodando pra consumir os jobs |
| `REDIS_URL` | `redis://[:senha@]host:porta[/db]` | obrigatória quando `QUEUE_DRIVER=redis` — ver `docs/technical/setup-e-operacoes.md` §77.1 pra subir um Redis local |

## Ciclo de uma execução

```
create(article_id, step, provider)   -> QUEUED   (queued_at)
markRunning()                        -> RUNNING  (started_at)
  falha técnica retryable → markRetrying(n, erro) -> RETRYING (retry_count = n)
  sucesso  → markSuccess(AIResult)   -> SUCCESS  (cost, finished_at)
  esgotou  → markFailed(erro)        -> FAILED   (error_message, finished_at)
```

Passos válidos (`ai_executions.step`, após a migration 0002):
`planning, research, writing, seo, compliance, image, review`.

## Custo

`GeminiPricing::estimate()` usa tarifas **aproximadas** das tabelas públicas do
Google (conferidas em 2026-08-28). Tokens de raciocínio contam como saída. O
valor-limite por site/mês (§95) só será definido com dados reais dos primeiros
artigos (Fase 4.4) — até lá, rodar poucos artigos por vez.

## Teste

Sem suíte automatizada no projeto; validado por teste de integração
auto-limpante (retry nos 3 cenários, pricing, fila síncrona, ciclo completo de
`ai_executions` contra um artigo temporário). A fila Redis (Fase 9.1) foi
validada manualmente com `bin/queue_smoke.php` (despacha) + `bin/worker.php`
(consome) contra um Redis local — ver `docs/technical/setup-e-operacoes.md`
§77.1. A conexão do pipeline (Fase 9.1b) foi validada com 2 gerações reais
contra o site Gavsy: uma com `QUEUE_DRIVER=sync` (roda inline, igual antes —
296s, terminou `IN_REVIEW`) e uma com `QUEUE_DRIVER=redis` + `bin/worker.php`
rodando (dispatch em 0,06s, artigo ficou `PLANNED` até o worker processar,
terminou `IN_REVIEW` em ~3min — planning→research→writing→seo→compliance→
review, cada passo visível em `ai_executions` em tempo real).

## Próximo (não implementado)

- Redis gerenciado em produção (hoje só há instância local de dev) +
  `bin/worker.php` rodando sob `supervisor`.
- Retry/dead-letter no nível de *job* (falha do handler inteiro) — distinto do
  retry de step da IA (`RetryRunner`), que já existe.
- Botão de "tentar novamente" a partir do estado `ERROR` — hoje regeneração só
  existe a partir de `REVISION_REQUESTED`.

# Fila de IA — execução, retry e custo

> Fatia 4.3 do roadmap. Implementa [ADR-006](../decisions/adr-006-fila-redis.md),
> [retry §96](testes-e-observabilidade.md#96-política-de-retry--falha-da-ia-proposta)
> e [custo §95](testes-e-observabilidade.md#95-controle-de-custo-de-ia-proposta).

## Estado atual

Só o **driver síncrono**: os jobs rodam inline, na mesma requisição que os
enfileira. Não há Redis nem worker em execução. A abstração (`QueueDriver`) já
isola isso — trocar para Redis é adicionar `RedisQueueDriver` + ligar
`bin/worker.php`, sem mexer em quem enfileira.

## Peças

| Classe | Papel |
|---|---|
| `App\Queue\Job` | `{type, payload, id}` — unidade de trabalho |
| `App\Queue\QueueDriver` | interface do transporte |
| `App\Queue\SyncQueueDriver` | executa o job no `push()` |
| `App\Queue\Queue` | registra handlers por tipo, `dispatch()` enfileira, `execute()` roda |
| `App\Queue\RetryPolicy` | 3 tentativas; backoff 30s→2min→10min em produção, 1s→2s→4s fora |
| `App\Queue\RetryRunner` | roda uma operação repetindo só em `AIException::$retryable` |
| `App\Services\AiExecutionService` | ciclo de `ai_executions`: QUEUED→RUNNING→RETRYING→SUCCESS/FAILED |
| `App\Integrations\Gemini\GeminiPricing` | estima `cost` (USD) a partir dos tokens do `AIResult` |

## Configuração (`.env`)

| Variável | Valor | Efeito |
|---|---|---|
| `QUEUE_DRIVER` | `sync` (padrão) | jobs rodam inline |
| `REDIS_URL` | — | usado só quando o driver Redis existir |

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
`ai_executions` contra um artigo temporário).

## Próximo (não implementado)

`RedisQueueDriver` (via `predis`) + `bin/worker.php` como laço consumidor sob
`supervisor`. Depende de uma instância Redis — ver opções no handoff da 4.3.

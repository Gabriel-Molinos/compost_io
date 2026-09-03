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
http-equiv="refresh">`, sem JS). A partir de `ERROR` dá pra "Tentar de novo"
(mesma mecânica de linhagem/tentativas da regeneração por rejeição, §29 —
`prepareRegenerate()` aceita os dois status).

**Retry/dead-letter no nível de job (Fase 9):** `RedisQueueDriver` segue o
padrão "fila confiável" do Redis — `reserve()` usa `BRPOPLPUSH` (move pra
`processingKey`, não remove) em vez de `BRPOP`; sucesso chama `ack()` (tira
de `processingKey`); falha chama `fail()` (reenfileira até `Job::MAX_ATTEMPTS`
tentativas — constante pública, 3 —, depois `deadLetterKey`). Se o worker
morrer no meio de um job, ele fica visível em `processingKey` — recuperar
com `bin/queue_requeue_stuck.php` (sem timeout automático, é manual por
decisão de escopo). Importante: `bin/worker.php` distingue `PipelineException`
(falha definitiva do pipeline, já tratada — `ack()`, nunca `fail()`, senão
reenfileirar rodaria a IA de novo do zero, custo real) de qualquer outra
exceção (bug/infra — aí sim `fail()`, candidata a retry de job de verdade).

**Publicação agendada automática (Fase 9):** `bin/worker.php` varre
`ScheduleService::dueForPublish()` a cada ~60s (dentro do próprio laço,
independente de `reserve()` ter achado job) e despacha `schedule.publish`
por agendamento `PENDING` vencido — `App\Queue\ScheduleJobHandlers` chama
`WordPressPublishService::publish()` de verdade. Falha de API do WordPress
segue o `fail()` normal (retry automático); na última tentativa antes do
dead-letter, o handler marca `schedules.status = 'FAILED'` (valor do ENUM já
existia, nunca usado até agora) — visível na página do artigo, com botão
"Cancelar agendamento" pra recomeçar. Uma guarda em memória no worker evita
despachar o mesmo agendamento duas vezes entre varreduras (o schedule só sai
de `PENDING`, e portanto de `dueForPublish()`, quando o job de fato termina —
`PUBLISHED` ou `FAILED`).

## Peças

| Classe | Papel |
|---|---|
| `App\Queue\Job` | `{type, payload, id, attempts}` — unidade de trabalho; `toJson()`/`fromJson()` pra serializar |
| `App\Queue\QueueDriver` | interface do transporte (`push`, `reserve`, `ack`, `fail`, `name`) |
| `App\Queue\SyncQueueDriver` | executa o job no `push()`; `reserve`/`ack`/`fail` não se aplicam (lançam) |
| `App\Queue\RedisQueueDriver` | `push()` = `LPUSH`; `reserve()` = `BRPOPLPUSH` (move pra `processingKey`); `ack()`/`fail()` fecham o ciclo (retry até 3x, depois `deadLetterKey`) |
| `App\Queue\RedisConfig` | lê `REDIS_URL` do `.env` (`fromEnv()`, mesmo padrão de `GeminiConfig`); deriva `processingKey()`/`deadLetterKey()` de `queueKey` |
| `App\Queue\Queue` | registra handlers por tipo, `dispatch()` enfileira, `execute()` roda, `reserve()`/`ack()`/`fail()` delegam ao driver |
| `App\Queue\ArticleJobHandlers` | registra `article.generate`/`article.regenerate` numa `Queue` — usado pelo controller (driver sync) e pelo worker (driver redis); marca `ERROR` em falha definitiva |
| `App\Queue\ScheduleJobHandlers` | registra `schedule.publish` — só usado pelo worker (a varredura que despacha só roda lá); marca `schedules.FAILED` na última tentativa |
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
review, cada passo visível em `ai_executions` em tempo real). Retry/dead-letter
de job (Fase 9) validado contra o Redis local: sucesso limpa `processingKey`;
3 falhas seguidas incrementam `attempts` (0→1→2→3) e caem no dead-letter; job
"preso" (reserve sem ack/fail, simulando worker morto) recuperado com
`bin/queue_requeue_stuck.php --dry-run` (só lista) e sem a flag (move de
volta, `reserve()` seguinte pega o mesmo job de novo). Publicação agendada
automática validada com dados sintéticos: `dueForPublish()` encontra
agendamento vencido e ignora um no futuro; artigo propositalmente fora do
status `SCHEDULED` faz `WordPressPublishService::publish()` falhar na
checagem local (sem tocar o WordPress real) — confirmadas as 3 tentativas,
o dead-letter e `schedules.status = FAILED`; banner de erro na página do
artigo renderizado nos 3 cenários (com/sem agendamento anterior, e o caso
que não deve aparecer).

## Próximo (não implementado)

- Redis gerenciado em produção (hoje só há instância local de dev) +
  `bin/worker.php` rodando sob `supervisor`.
- Timeout/lease automático pra jobs presos em `processingKey` — hoje a
  recuperação é manual (`bin/queue_requeue_stuck.php`), decisão de escopo
  registrada ao abrir o retry/dead-letter de job.

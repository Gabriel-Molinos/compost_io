# ADR-006 — Mecanismo de fila para o Redis

**Contexto:** a produção de artigos pode levar horas (pesquisa → escrita → imagem → compliance → revisão) e não deve depender de uma requisição HTTP aberta (ver [seção 26](../editorial/fluxo-editorial.md#26-processamento-assíncrono)). O Redis foi confirmado como tecnologia de fila. Faltava decidir *como* o PHP conversa com o Redis: cliente manual + worker próprio, ou uma biblioteca de fila pronta (ex.: `php-enqueue`).

**Decisão:** usar a extensão **`phpredis`** (ou `predis/predis` como alternativa 100% PHP, sem extensão nativa) para empurrar jobs numa fila do Redis, consumidos por um **worker PHP próprio**, rodando em segundo plano via `supervisor` (produção) ou processo manual (dev).

**Motivos:**
- Mantém a mesma filosofia do [ADR-002](adr-002-php-pdo.md): sem dependências pesadas, comportamento explícito, fácil de o Claude Code entender e depurar linha a linha.
- Um worker próprio é um script PHP simples (`while (true) { ... }` consumindo a fila) — não exige aprender a API de uma biblioteca de fila completa para um caso de uso relativamente simples (uma fila, poucos tipos de job).
- Evita trazer uma dependência a mais só para orquestrar filas, quando o volume inicial (60+ sites, não milhares de jobs simultâneos) não justifica a complexidade de um sistema de filas mais robusto.

**Trade-offs aceitos:**
- Recursos como retry automático, prioridade de fila e dashboards de monitoramento (que uma lib de fila madura ofereceria pronto) precisam ser implementados manualmente — a [Política de retry/falha da IA (seção 96)](../technical/testes-e-observabilidade.md#96-política-de-retry--falha-da-ia-proposta) e o campo `retry_count` do rascunho de schema (seção 87) cobrem a parte de retry no nível de step da IA. Retry/dead-letter no nível de *job* (se um handler inteiro falhar) ainda não existe.
- Se o volume de jobs crescer muito na Fase 9 (escala), pode valer reavaliar esse ADR e migrar para uma solução mais robusta — registrar isso como revisão futura do ADR, não como decisão definitiva para sempre.

**Status (Fase 9.1 — infraestrutura):** entre as duas opções da decisão, foi implementado `predis/predis`, não a extensão nativa `phpredis` — evita depender de uma extensão PHP compilada nativamente (fricção em ambientes de dev, ex.: Windows sem a extensão instalada), mantendo o resto do racional do ADR de pé (sem lib de fila completa, worker próprio simples). Ver `src/Queue/RedisQueueDriver.php`, `src/Queue/RedisConfig.php` e `bin/worker.php`.

**Status (Fase 9.1b — pipeline conectado):** `ProductionController::generate()`/`regenerate()` despacham `Job` (`article.generate`/`article.regenerate`) em vez de rodar `ArticlePipeline` inline; `App\Queue\ArticleJobHandlers` registra os handlers, usados tanto pelo controller (driver `sync` — roda ali mesmo) quanto por `bin/worker.php` (driver `redis` — processa em segundo plano). Validado com 2 gerações reais contra o site Gavsy, uma em cada driver — ver `docs/technical/fila-ia.md`.

## Ver também

- [Fluxo editorial — Processamento assíncrono](../editorial/fluxo-editorial.md#26-processamento-assíncrono)
- [Testes e observabilidade — retry](../technical/testes-e-observabilidade.md#96-política-de-retry--falha-da-ia-proposta)
- [Diagrama — falha técnica e retry](../technical/diagrama-sequencia.md#982-sequência-de-falha-técnica--retry-proposta)

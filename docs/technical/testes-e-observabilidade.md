# Parte 16 — Testes `[PROPOSTA]`

### 92. Estratégia de testes `[PROPOSTA]`

| Tipo | Onde roda | Quando usar | Ferramenta sugerida |
|---|---|---|---|
| Unitário | PHP | Services e regras de negócio isoladas | PHPUnit |
| Integração | PHP | Fluxos entre Controller → Service → PDO (ex.: criar artigo → gerar meta) | PHPUnit + banco de teste |
| E2E | Navegador | Fluxos críticos completos (login, aprovação, publicação) | Playwright ou Cypress — continuam válidos por automatizarem o navegador, independente da tecnologia por trás da página |
| Acessibilidade | Navegador | Views novas ou alteradas | Checagem manual de teclado + contraste ([Parte 20, seção 106](ui-ux-frontend.md#106-checklist-de-ui-antes-de-um-pr)); ferramenta automatizável (ex.: axe) opcional no futuro |

### 93. Cobertura mínima esperada `[PROPOSTA — definir número junto ao time]`

- Módulos com regra de negócio crítica (aprovação, permissões, integrações) devem ter teste antes de serem considerados concluídos — coerente com a [Regra de transparência (seção 59)](../ai/regras-claude-code.md#59-regra-de-transparência): "nunca considerar uma tarefa concluída se apenas foi criada a estrutura sem que o fluxo tenha sido realmente testado."

---

# Parte 17 — Observabilidade `[PROPOSTA]`

### 94. Logging `[PROPOSTA]`

- Níveis: `error`, `warn`, `info`, `debug`.
- Nunca logar credenciais, tokens ou dados sensíveis (reforça a [seção 48, regra 5](../technical/seguranca.md#48-política-de-segurança-para-credenciais)).
- Logs de execução de IA (pesquisa, escrita, SEO, imagem) devem ser persistidos na tabela `ai_executions` (ver [seção 87](schema.md#87-tabelas--estado-atual-migration-0001)), não só em arquivo de log.

### 94.1 Monitoramento e alertas

> **Implementado (Fase 9.3).** Sem ferramenta paga nesta fase: falha após retry (`articles.status = ERROR`, Fase 9.1b), custo de IA no limite (Fase 9.2), e artigo `BLOCKED` viram indicadores visuais na Visão Geral do site (`sites/show.php`) — ver [seção 33](../editorial/fluxo-editorial.md#33-centro-de-inteligência-editorial). `ArticleService::attentionCounts()` conta `BLOCKED`/`ERROR` por site; banner `role="alert"` aparece só quando há pendência, com link pra Produção. Alerta externo (e-mail/Slack) e métricas de sistema seguem fora de escopo — sem volume que justifique ainda.

### 95. Controle de custo de IA `[PROPOSTA]`

- Registrar o custo de cada execução de IA por artigo (campo `cost` em `ai_executions`).
- Relatório de custo por site pode compor o [Centro de Inteligência Editorial (seção 33)](../editorial/fluxo-editorial.md#33-centro-de-inteligência-editorial) no futuro.

> **Decisão registrada (método):** o limite de gasto por site/mês é calculado pela fórmula `custo médio por artigo × meta de artigos/mês do site × margem de segurança`. Ao atingir o limite, o comportamento inicial é **alertar o Administrador** (não bloquear automaticamente a produção) — bloqueio automático só deve ser considerado depois de haver dados reais de custo.
>
> **Implementado (Fase 9.2):** com pouco dado real ainda (poucos artigos produzidos), "custo médio por artigo" usa o **maior custo por artigo já observado**, em qualquer site (`CostBudgetService::maxObservedCostPerArticle()`) — mais conservador que uma média de amostra pequena, e recalculado a cada carregamento conforme mais artigos forem produzidos, sem guardar um número fixo (não há tabela nova/migration para isso). **Margem de segurança: 1,5x.** Sem meta definida pro mês, ou sem nenhum dado real de custo em nenhum site ainda, o indicador simplesmente não aparece — reforça a [Regra de não-invenção (seção 58)](../ai/regras-claude-code.md#58-regra-de-não-invenção): não inventar um número sem base real. O alerta é só visual, na Visão Geral do site (`sites/show.php`) — sem e-mail/Slack, como já previsto em [94.1](#941-monitoramento-e-alertas).

**Teto diário de gerações (guarda distinta do limite mensal acima):** `ProductionController::DAILY_LIMIT` (15/dia por site) enquanto o limite de custo por site/mês não bloqueia automaticamente. **Corrigido (Fase 9, performance/correção):** `ArticleService::countCreatedLast24h()` sozinha tinha race condition — duas requisições concorrentes liam a mesma contagem antes de qualquer uma criar a linha, podendo passar do teto. `ArticleService::createWithDailyLimit()` fecha isso com `GET_LOCK` do MySQL escopado por site (check + criação da linha viram uma seção crítica). Validado com 4 rodadas de 2 processos concorrentes reais — em todas, exatamente 1 passou.

### 96. Política de retry / falha da IA `[PROPOSTA]`

Diferente da [regeneração por rejeição humana (seção 29)](../editorial/fluxo-editorial.md#29-regeneração), esta trata de falhas técnicas (timeout, erro de API, resposta inválida):

> **Decisão registrada:** **3 tentativas automáticas** por etapa técnica (pesquisa, escrita, SEO, imagem), com **backoff exponencial** entre elas (ex.: 30s → 2min → 10min). Usa a mesma cardinalidade da [Regeneração por rejeição humana (seção 29)](../editorial/fluxo-editorial.md#29-regeneração) só por consistência de leitura — são conceitos diferentes (falha técnica vs. rejeição humana) e podem divergir no futuro se fizer sentido.

- Após esgotar as 3 tentativas, o artigo deve ir para um estado de erro visível ao Redator-Chefe (ex.: `BLOCKED` ou um novo estado `ERROR`), nunca falhar silenciosamente — reforça a [Regra de transparência (seção 59)](../ai/regras-claude-code.md#59-regra-de-transparência).

**Implementado (Fase 9, "tentar de novo"):** `ArticlePipeline::prepareRegenerate()` aceita `ERROR` além de `REVISION_REQUESTED` — mesma mecânica de linhagem/tentativas da [regeneração por rejeição (seção 29)](../editorial/fluxo-editorial.md#29-regeneração) (`attempt_number`, `BLOCKED` ao esgotar `MAX_ATTEMPTS`), sem reaproveitar o feedback de rejeição (não existe pra uma falha técnica — `lineageFeedbackContext()` degrada pra string vazia nesse caso). Botão "Tentar de novo" na página do artigo (`sites/production/show.php`), mesma rota `/regenerate`.

### 97. Performance para 60+ sites (Fase 9)

Levantamento (sem fatia numerada nos requisitos originais — feito sob demanda ao abrir a Fase 9/Escala) encontrou dois pontos concretos, ambos endereçados:

- **Índices faltando** nos filtros de período mais usados (`ReportService`, `CostBudgetService`) — `articles.created_at`/`reviewed_at`, `ai_executions.cost`/`created_at`, `feedback.created_at` não tinham índice, forçando scan de todas as linhas do site (ou, na consulta de custo máximo global, de `ai_executions` inteira, todos os sites) a cada carregamento. Corrigido pela migration `0011` (só índices, nenhuma coluna nova).
- **`SiteController::show()`** (Visão Geral do site) rodava `ReportService::monthly()` inteiro — 10 queries — só para extrair `goal_total`/`ai_cost` usados pelo `CostBudgetService`. Como a Visão Geral é carregada a cada visita (não só a aba Relatórios), isso rodava a cada page load, de qualquer site. `ReportService::currentSpend()` (2 queries) substitui essa chamada; `monthly()` continua intacto para a aba Relatórios, que precisa do relatório completo.

**Implementado (2026-09-14):**
- **Cache** — ver [`docs/technical/cache.md`](cache.md): `App\Cache\CacheService` (cache-aside sobre o mesmo Redis/Memurai da fila) agora envolve `CostBudgetService::maxObservedCostPerArticle()` (10 min, global), `ReportService::currentSpend()`/`trend()`/`monthly()` (2-5 min, ou 24h para meses já fechados) e `WordPressClient::listRecentPosts()` (5 min, a chamada HTTP mais repetida do app — disparada em toda visita à página de um artigo). Nunca uma dependência: sem `REDIS_URL`, ou com o Redis fora do ar, tudo continua funcionando igual, só sem o ganho de velocidade.

**Implementado (2026-09-15):**
- **Paginação** de `ArticleService::allForSite()` (listagem de artigos do site, aba Produção) — antes buscava tudo sem `LIMIT`. `allForSite()` ganhou `$statusGroup`/`$page`/`$perPage` (20 por página, `LIMIT`/`OFFSET`), com `countsByStatusGroup()` (badges das abas de filtro, contagem real do site, independente da página) e `firstInReview()` (hint do tutorial guiado) como consultas próprias e leves. O filtro por status, que antes era só JS escondendo linhas já carregadas, virou navegação de verdade (`?status=...&page=...`) — necessário porque com a lista paginada um filtro client-side só teria as linhas da página atual pra mostrar, dando contagem incoerente com o badge.

## Ver também

- [Diagrama — falha técnica e retry](diagrama-sequencia.md#982-sequência-de-falha-técnica--retry-proposta)
- [Schema — tabela `ai_executions`](schema.md#87-tabelas--estado-atual-migration-0001)
- [Segurança — política de credenciais](seguranca.md)

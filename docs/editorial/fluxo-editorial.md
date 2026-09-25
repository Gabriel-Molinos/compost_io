# Parte 3 — Usuários, Sites e Configuração Editorial

### 14. Usuários e permissões

O sistema terá dois papéis principais: **ADMINISTRADOR** e **REDATOR-CHEFE**.

A relação entre usuários e sites será independente da função. Exemplo:

```
João
 ├── Site A
 ├── Site B
 └── Site C

Maria
 ├── Site D
 ├── Site E
 └── Site F
```

O backend será responsável por garantir o isolamento.

### 15. Estrutura editorial de cada site

Cada site possuirá:

```
Site
│
├── Nicho
├── Idioma
├── Público
├── Categorias
├── Identidade editorial
├── Interesses
├── Não-interesses
├── Compliance
├── Regras SEO
├── Preferências de imagem
└── Conexão WordPress
```

As **Regras SEO** de cada site são específicas e somam por cima da linha de base comum a todos os sites, definida no [Checklist SEO On-Page](seo.md). O **Compliance** do site soma da mesma forma sobre a [Compliance de conteúdo](compliance.md).

### 16. Metas editoriais

O Redator-Chefe cria uma meta por período. Exemplo:

```
META — SETEMBRO

Total: 30 artigos

Cartões: 10
Investimentos: 10
Empréstimos: 10
```

Além do volume, a meta possui **diretrizes gerais**, exemplo: *"Priorizar Nubank e comparativos. Focar iniciantes. Evitar repetição."*

Cada categoria também pode possuir suas próprias diretrizes.

### 17. Personalidade editorial (camadas de contexto)

A IA deve receber:

```
REGRAS DO SITE
        +
DIRETRIZES DA META
        +
REGRAS DA CATEGORIA
        +
BRIEF DO ARTIGO
        +
MEMÓRIA EDITORIAL
```

Isso forma o contexto final da produção.

### 18. Interesses

Exemplos: linguagem clara, dados oficiais, exemplos práticos, comparativos, FAQ, glossário, links internos.

### 19. Não-interesses

Exemplos: sensacionalismo, promessas, gírias, informações sem fundamento, clickbait, keyword stuffing.

Cada regra (interesse ou não-interesse) poderá possuir **intensidade de 1 a 5**.

### 20. Configuração específica do site

As configurações específicas não devem ser copiadas para 60 arquivos diferentes. Elas serão armazenadas como **dados associados ao site**. Exemplo:

```
Site: Valorizei
Nicho: Finanças
Idioma: Português
Tom: Profissional
Público: Iniciantes
```

O sistema monta o contexto necessário automaticamente.

---

# Parte 4 — Fluxo de Produção com IA

### 21. Como a IA deve trabalhar

A IA não deverá iniciar escrevendo. Processo:

```
Entender site
↓
Entender público
↓
Entender meta
↓
Verificar histórico
↓
Escolher tema
↓
Verificar duplicação
↓
Pesquisar
↓
Validar fontes
↓
Criar brief
↓
Estruturar
↓
Escrever
↓
SEO
↓
Compliance
↓
Imagem
↓
Revisão
↓
Redator
```

- A etapa **SEO** segue o [Checklist SEO On-Page](seo.md); a etapa **Compliance** segue a [Compliance de conteúdo](compliance.md).
- O passo **Verificar duplicação** cobre a **canibalização de palavra-chave**: se a palavra-chave já foi usada em um artigo anterior do site, a IA deve avisar e deixar o artigo em rascunho em vez de seguir para publicação (ver [Regra de transparência, seção 59](../ai/regras-claude-code.md#59-regra-de-transparência)).

### 22. Prompt base da plataforma

O prompt base será responsável por ensinar à IA:

- como pensar como redator;
- como pesquisar;
- como avaliar temas;
- como verificar fontes;
- como estruturar;
- como revisar;
- como respeitar regras.

Ele **não** será específico de um site — será uma instrução global da plataforma.

As camadas de prompt se combinam da seguinte forma:

```
Prompt Base
      +
Identidade do Site
      +
Meta
      +
Categoria
      +
Brief
      +
Memória Editorial
      ↓
Prompt Final
      ↓
Gemini
```

- **Prompt Base** ensina como um redator experiente pensa, pesquisa, estrutura e revisa.
- **Identidade do Site** define como aquele site deve escrever.
- **Meta** define o que precisa ser produzido naquele período.
- **Brief** define o que aquele artigo específico precisa fazer.

### 23. Estrutura de prompts

```
docs/
└── ai/
    ├── base-editorial.md   ✅ Fase 4.1
    ├── planning.md         ✅ Fase 4.1
    ├── research.md         ✅ Fase 4.1
    ├── writing.md          ✅ Fase 4.1
    ├── seo.md              ✅ Fase 4.1
    ├── compliance.md       ✅ Fase 4.1
    ├── review.md           ✅ Fase 4.1
    └── image.md            (Fase 5 — imagens)
```

As regras específicas dos sites **não** viram 60 arquivos duplicados — ficam como dados relacionados a cada site e são injetadas em tempo de montagem pelo `PromptBuilder` (`src/Services/PromptBuilder.php`), que combina Prompt Base + Passo + Identidade do Site + Meta + Categoria + Pedido do Redator (quando houver, ver §24) + Brief + Memória Editorial (§22).

### 24. Produção do artigo

O artigo terá um único padrão de produção. **Prazo: até 24 horas.** A extensão mínima de **1500 palavras** faz parte desse padrão único (ver [Checklist SEO On-Page](seo.md#regras-firmes--referência-rápida)).

O processo deve priorizar fontes confiáveis. Fontes preferenciais:

- órgãos oficiais;
- empresas responsáveis;
- documentação oficial;
- estudos;
- instituições reconhecidas;
- fontes jornalísticas confiáveis.

A plataforma deve registrar as fontes utilizadas.

**Rascunho específico (pedido do redator).** Além do rascunho comum (a IA escolhe o tema) e do automático diário, a página de Produção tem o botão **"Rascunho específico"**: o redator escolhe a **categoria** (obrigatória), opcionalmente a meta, e **descreve o post que quer** (40 a 3000 caracteres — assunto, público, pontos obrigatórios, tom, o que evitar).

- O texto fica guardado no artigo (`articles.writer_request`) e entra como camada de **prioridade máxima** no prompt de **todos** os passos da IA, do planejamento à revisão; a etapa de revisão confere o artigo ponto a ponto contra o pedido.
- As regras fixas de compliance/SEO (mínimo de 1500 palavras, links, etc.) continuam valendo por cima do pedido — o redator manda no conteúdo, não nas regras.
- **Regenerações herdam o pedido**: se o Redator-Chefe rejeitar e o artigo for regenerado, a nova tentativa da mesma linhagem continua seguindo o mesmo pedido.
- Quem revisa vê o pedido no topo da página do artigo ("Pedido do redator"), e a lista de Produção marca esses rascunhos como **específico**.
- Custo e limite diário são os mesmos do rascunho comum (várias chamadas ao Gemini por geração).

**Geração automática diária — só com meta.** O worker gera 1 rascunho por dia por site ativo, seguindo a **meta do mês atual**; se o mês atual não tem meta, usa a **do próximo mês** (e distribui as categorias contando os artigos daquela meta). Se o site não tem meta em nenhum dos dois, **a geração automática fica pausada**: a equipe do site (admins + redator-chefe vinculado) recebe uma notificação "Geração automática pausada: falta a meta" (no máximo 1 por dia) e a geração volta sozinha, na varredura seguinte, assim que uma meta for cadastrada. O rascunho manual continua livre, com ou sem meta.

### 25. Imagens

A geração de imagens será separada da geração de texto.

- **Texto** → Gemini.
- **Imagem** → Nano Banana.

A plataforma deverá gerar múltiplas opções de imagem para o Redator-Chefe escolher. Fluxo:

```
Artigo
↓
Brief visual
↓
Nano Banana
↓
3–5 opções
↓
Redator escolhe
```

Implementado na Fase 5 — detalhes técnicos em [docs/integrations/images.md](../integrations/images.md). O brief visual é o passo `image` (`docs/ai/image.md`); as opções ficam em `images` (`selected = 0`) e o Redator-Chefe escolhe a destacada na página do artigo.

> **Implementado (2026-09-24) — imagem própria:** além das geradas pela IA, o redator pode subir a sua (destacada ou de corpo) no bloco "Enviar uma imagem sua" da tela do artigo. Regras (`App\Support\ImageUploadValidator`, checadas pelos bytes do arquivo, não pela extensão): **WebP**, largura de **1200 a 2560 px**, proporção **16:9** (ex.: 1200×675 ou 1600×900), até **2 MB**, e descrição (alt) obrigatória. A destacada enviada já fica escolhida; a de corpo entra na distribuição automática. Imagens próprias não têm prompt, então não têm o botão "Substituir".

### 26. Processamento assíncrono

A IA pode levar horas para finalizar uma produção. Portanto, o sistema **não** deverá depender de uma requisição HTTP aberta durante todo o processo.

> **Decisão registrada:** o **Redis será utilizado** para a fila de processamento assíncrono (deixou de ser algo "futuro/opcional" — ver seção 6 e nota abaixo).

```
Meta
 ↓
Job
 ↓
Fila (Redis)
 ↓
Pesquisa
 ↓
Escrita
 ↓
Imagem
 ↓
Compliance
 ↓
Revisão
```

Como o backend é **PHP puro, sem framework** (ver [seção 6](../technical/arquitetura.md#6-stack-do-projeto--backend)), a fila não usará BullMQ (que é uma biblioteca específica do ecossistema Node.js).

> **Decisão registrada ([ADR-006](../decisions/adr-006-fila-redis.md)):** cliente Redis via `phpredis`/`predis` + **worker PHP próprio** rodando em segundo plano (`supervisor` em produção, processo manual em dev) — sem biblioteca de fila pronta, mantendo a mesma filosofia "sem dependência pesada" do backend.

---

# Parte 5 — Revisão, Publicação e Relatórios

### 27. Revisão humana (estados do artigo)

O artigo só conta para a meta quando estiver `APPROVED`.

> **Implementado (Fase 9):** em `IN_REVIEW`, o Redator-Chefe também pode editar o corpo (HTML) direto na página do artigo antes de aprovar/rejeitar — evita um ciclo inteiro de rejeição+regeneração (com custo de IA) só para corrigir um trecho. Grava como nova versão em `article_versions`, histórico preservado. Ver bloco `[Fase 9]` do `CHANGELOG.md`.

> **Implementado (2026-09-15):** checklist de pré-aprovação obrigatório (recomendação do relatório de Inteligência: artigos vinham chegando a `BLOCKED` em compliance/SEO por fugir de categoria válida ou da faixa de link, sem nada barrando uma aprovação manual fora dessa faixa). `ArticleReviewService::approve()` verifica **categoria definida**, **3 a 5 links internos** e **no máximo 2 links externos** no corpo — se algum item falhar, a aprovação é recusada (`RuntimeException`) e o botão "Aprovar artigo" já aparece desabilitado na página do artigo, com o motivo de cada item que falhou.
>
> **Implementado (2026-09-23):** o checklist de pré-aprovação ganhou um 4º item mecânico, **extensão mínima de 1500 palavras** (mesma regra de [compliance de conteúdo](compliance.md#regras-de-compliance-de-conteúdo-qualidade--adsense)) — antes essa regra só existia em texto solto num parecer da IA, sem nada travando de verdade; agora é contada direto do HTML salvo (`article_versions.word_count`), sem depender da IA acertar. Além disso, o parecer de **compliance** da IA (`blocking`, docs/ai/compliance.md) passou a exigir confirmação explícita do Redator-Chefe antes de aprovar: se houver pendência de compliance sem confirmar, `approve()` recusa (mensagem clara) e a página exige marcar "Revisei as pendências e decido aprovar assim mesmo" — o parecer da IA continua **não travando sozinho** (pode errar, ver aviso de falso-positivo em docs/ai/compliance.md), mas também não passa mais despercebido. O card "Compliance" da página agora mostra o trecho (`evidence`) que a IA usou como base pra cada pendência — antes só apareciam a regra e a correção sugerida, sem o reviewer conseguir conferir contra o texto real — e uma legenda curta explicando bloqueio vs. aviso em linguagem simples. Escolher "Problema de compliance" no motivo de rejeição pré-preenche a justificativa com as pendências já apontadas (menos trabalho manual de retranscrever).

Estados principais:

```
IN_PROGRESS
IN_REVIEW
REVISION_REQUESTED
APPROVED
SCHEDULED
PUBLISHED
DISCARDED
BLOCKED
```

### 28. Feedback

Quando o redator rejeitar, exigir:

**Motivo** — exemplos: tema fraco, informação incorreta, conteúdo superficial, fora do tom, tema repetido, não seguiu a meta, problema de compliance, imagem inadequada, outro.

**Justificativa** — campo de texto explicando o problema.

O artigo descartado permanece no histórico.

### 29. Regeneração

Um artigo rejeitado pode ser regenerado. Cada tentativa deve ser registrada.

- Limite planejado: **3 tentativas por linhagem**.
- Após duas linhagens completas sem aprovação: `BLOQUEADO` — o redator precisa tomar uma decisão.

> **Implementado (Fase 6.2):** cada tentativa é uma nova linha em `articles` com o
> mesmo `lineage_id` e `attempt_number + 1`; o feedback de todas as tentativas
> entra no prompt da regeneração. A 4ª tentativa não roda — o artigo vira
> `BLOCKED`. O conceito de "múltiplas linhagens por slot" foi adiado (ver
> [schema §87.2](../technical/schema.md#872-pendências-de-modelagem-proposta)).

> **Implementado (Fase 9):** além do feedback recente derivado em tempo real (acima), o site
> pode ter uma **memória editorial curada** — lições duradouras escritas por um humano
> (Redator-Chefe/Admin), nunca pela IA — na aba **Memória**. Entram somadas em todo prompt
> de geração/regeneração, não só na regeneração. Ver [schema §87.2](../technical/schema.md#872-pendências-de-modelagem-proposta) (`editorial_memory`).

### 30. Agendamento

Depois da aprovação, o redator escolhe: imagem, categoria, autor, data, horário. Depois a plataforma envia ao WordPress.

> **Implementado na Fase 7.4.** `APPROVED → SCHEDULED` (`ScheduleService`), linha `PENDING` em `schedules`. A imagem destacada precisa estar escolhida. Dá para reagendar e cancelar (volta a `APPROVED`). Calendário mensal por site na aba **Calendário** (7.6).
> **Fase 9:** dá pra reagendar só a data arrastando o item pra outro dia direto no Calendário (mantém autor/imagem/horário) — o formulário completo continua na tela do artigo.

### 31. Integração WordPress

A publicação será feita por meio da **WordPress REST API**. A API do WordPress permite consultar e modificar recursos como posts, categorias, mídia e usuários por HTTP/JSON; entre os endpoints padrão estão `/wp/v2/posts`, `/wp/v2/categories`, `/wp/v2/media` e `/wp/v2/users`.

A integração deverá conseguir:

- listar categorias;
- listar autores;
- enviar imagem;
- criar post;
- definir categoria;
- definir autor;
- definir slug;
- agendar;
- verificar publicação.

Cada site terá sua própria conexão.

> **Implementado na Fase 7.5.** `SCHEDULED → PUBLISHED` (`WordPressPublishService`). Post como `future` (o WordPress publica na data) ou `publish` se a data já passou. Sobe a imagem destacada e as de corpo (WebP, distribuídas entre as seções), define categoria/autor/slug/excerpt, resolve links internos. "Atualizar no WordPress" e "Retirar do WordPress" (post → lixeira). Envio por botão manual **ou automático** (Fase 9: `bin/worker.php` varre `schedules` `PENDING` vencidos a cada ~60s e publica sozinho — `ScheduleJobHandlers`/`ScheduleService::dueForPublish()`; falha esgotada vira `schedules.status = FAILED`, visível na página do artigo).

### 32. Relatórios

A dashboard terá relatórios para ajudar o Redator-Chefe a entender a operação.

**Relatório mensal** — mostrar: meta, aprovados, publicados, rejeitados, pendentes, taxa de aprovação, tempo médio de revisão.

**O que melhorou** — exemplos: taxa de aprovação aumentou; menos correções de tom; menor tempo médio de revisão.

**O que não melhorou** — exemplos: informações incorretas continuam frequentes; imagens continuam sendo trocadas; categoria X está atrasada.

**Aprendizados** — mostrar padrões encontrados, exemplos: comparativos possuem alta aprovação; introduções mais curtas são preferidas; conteúdo muito técnico gera mais ajustes.

### 33. Centro de Inteligência Editorial

A plataforma deverá possuir uma área dedicada à análise, que deve responder:

- O que está funcionando?
- O que está dando errado?
- O que melhorou?
- O que não melhorou?
- O que a IA aprendeu?
- O que precisa ser ajustado?

Essa área não deve competir com a operação diária.

- **Operação** fica em: Visão Geral, Produção, Planejamento, Calendário.
- **Análise** fica em: Relatórios, Inteligência Editorial.

> **Implementado na Fase 8.3.** Aba **Inteligência** por site. `IntelligenceService` junta os números dos últimos 3 meses (`ReportService`) + as justificativas de rejeição recentes e pede ao Gemini uma resposta narrativa às seis perguntas acima (JSON com schema; instrução de sistema proíbe inventar dado — §58). Geração **sob demanda** (botão), com teto de 10/dia por site; o resultado fica salvo em `editorial_insights` até regenerar. Custo típico ~US$ 0,02.

## Ver também

- [Requisitos e modelagem](../technical/requisitos.md) — RF, permissões, máquina de estados
- [Compliance de conteúdo](compliance.md) — regras que todo artigo precisa seguir antes de aprovado
- [Diagramas de sequência](../technical/diagrama-sequencia.md) — esses fluxos em nível de chamadas
- [Arquitetura — backend](../technical/arquitetura.md#6-stack-do-projeto--backend)

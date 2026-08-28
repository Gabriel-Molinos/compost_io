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

As regras específicas dos sites **não** viram 60 arquivos duplicados — ficam como dados relacionados a cada site e são injetadas em tempo de montagem pelo `PromptBuilder` (`src/Services/PromptBuilder.php`), que combina Prompt Base + Passo + Identidade do Site + Meta + Categoria + Brief + Memória Editorial (§22).

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

### 30. Agendamento

Depois da aprovação, o redator escolhe: imagem, categoria, autor, data, horário. Depois a plataforma envia ao WordPress.

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

## Ver também

- [Requisitos e modelagem](../technical/requisitos.md) — RF, permissões, máquina de estados
- [Compliance de conteúdo](compliance.md) — regras que todo artigo precisa seguir antes de aprovado
- [Diagramas de sequência](../technical/diagrama-sequencia.md) — esses fluxos em nível de chamadas
- [Arquitetura — backend](../technical/arquitetura.md#6-stack-do-projeto--backend)

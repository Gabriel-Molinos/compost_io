# Parte 20 — UI/UX & Frontend Standards `[PROPOSTA]`

Padrão único de interface, experiência e acessibilidade da plataforma COMPOST. Vale para toda View PHP (`src/Views/`), todo CSS compilado pelo Tailwind CLI e todo JavaScript vanilla (ver [Stack — Frontend](arquitetura.md#5-stack-do-projeto--frontend) e [ADR-008](../decisions/adr-008-frontend-php-puro.md)).

> **Decisão registrada:** o alvo de acessibilidade da plataforma é **WCAG 2.2 nível AA**. O nível AAA não é meta geral (a própria W3C não recomenda como alvo global e é incompatível com a paleta neon da identidade); o nível A é insuficiente para uma ferramenta interna usada o dia todo.

> **Decisão registrada:** o **Material Design 3** é adotado **apenas como guia de design** (padrões de componente, layout, estados e navegação). A biblioteca *Material Web Components* **não** será usada — exige npm/Node.js, proibido pela [ADR-008](../decisions/adr-008-frontend-php-puro.md). Todo componente é implementado à mão em HTML + Tailwind + JS vanilla.

### 100. Documentação oficial de referência (por área)

Antes de criar ou alterar interface, consultar a documentação oficial da área correspondente. Prioridade de fontes segue a [Regra para utilização de documentação por IA (seção 61)](../ai/regras-claude-code.md#61-regra-para-utilização-de-documentação-por-ia).

| Área | Referência |
|---|---|
| UX | Apple HIG + Design Principles |
| UI | Apple HIG + Material Design 3 |
| Acessibilidade | W3C WCAG 2.2 (alvo: nível AA) |
| Layout | HIG + Material Design 3 |
| Tipografia | HIG (+ paleta/tipografia da [identidade-visual.md](../product/identidade-visual.md)) |
| Cores / contraste | HIG + WCAG 2.2 |
| Componentes | Material Design 3 + HIG |
| Estados de interface | HIG + Material Design 3 |
| Formulários | HIG + WCAG 2.2 |
| Navegação | HIG + Material Design 3 |
| Responsividade | princípios do HIG + padrões web (MDN) |
| Teclado / foco | WCAG 2.2 |
| Motion / animações | HIG |
| UX Writing | HIG |

Links oficiais (também listados em [Referências, seção 82](../referencias.md#82-referências-oficiais-links)):

| Fonte | Link |
|---|---|
| W3C WCAG 2.2 | https://www.w3.org/TR/WCAG22/ |
| Apple Human Interface Guidelines | https://developer.apple.com/design/human-interface-guidelines/ |
| HIG — Design Principles | https://developer.apple.com/design/human-interface-guidelines/design-principles |
| HIG — Foundations | https://developer.apple.com/design/human-interface-guidelines/foundations |
| Material Design 3 | https://m3.material.io/ |
| MDN Web Docs | https://developer.mozilla.org/pt-BR/docs/Web |

> **Nota:** o HIG é escrito para iOS/iPadOS/macOS. Adaptar os **princípios** (clareza, hierarquia, deferência ao conteúdo, feedback) para uma aplicação **web** renderizada em PHP — não copiar componentes nativos da Apple nem assumir gestos/controles de plataforma móvel.

### 101. Princípios (o "porquê")

Derivados do HIG Design Principles e do Material 3:

- **Clareza acima de tudo** — cada tela responde: onde estou, o que está acontecendo, o que precisa da minha atenção, qual o próximo passo (reforça os [Princípios do produto, seção 3](../product/visao-geral.md#3-princípios-do-produto)).
- **Hierarquia visual** — tamanho, peso, cor e espaçamento guiam o olho para o que importa primeiro.
- **Deferência ao conteúdo** — a interface serve o conteúdo editorial; cromo e decoração não competem com ele.
- **Consistência** — o mesmo elemento se parece e se comporta igual em toda a plataforma.
- **Feedback imediato** — toda ação do usuário produz uma resposta visível.
- **Prevenção e perdão de erros** — confirmar ações destrutivas, permitir desfazer/cancelar, nunca punir exploração.
- **Acessibilidade desde o início** — tratada como requisito de design, não como ajuste posterior.

### 102. Regras obrigatórias

Toda interface criada ou alterada deve cumprir:

- **R-UI-01** — priorizar clareza e hierarquia visual.
- **R-UI-02** — manter consistência entre componentes.
- **R-UI-03** — não criar componentes visualmente diferentes para a mesma função.
- **R-UI-04** — respeitar os estados `hover`, `focus` (`focus-visible` para teclado), `active`, `disabled`, `loading` e `error`. Nenhum elemento interativo é entregue sem os estados aplicáveis.
- **R-UI-05** — garantir navegação completa por teclado: ordem de foco lógica, foco sempre visível, sem armadilha de foco, sem depender de mouse/hover para acessar função (WCAG 2.2 SC 2.1.1, 2.1.2, 2.4.3, 2.4.7, 2.4.11).
- **R-UI-06** — respeitar o contraste mínimo AA: **4.5:1** para texto normal, **3:1** para texto grande e para componentes de UI / indicadores de foco (WCAG 1.4.3, 1.4.11).
- **R-UI-07** — não depender exclusivamente de cor para transmitir informação; acompanhar sempre de ícone, texto ou forma (WCAG 1.4.1). Aplica-se diretamente aos status do artigo (`APPROVED`, `BLOCKED`, `IN_REVIEW`, `REVISION_REQUESTED`…) — ver [máquina de estados, seção 27](../editorial/fluxo-editorial.md#27-revisão-humana-estados-do-artigo).
- **R-UI-08** — manter áreas clicáveis adequadas: alvo mínimo **24×24 px** CSS (WCAG 2.2 SC 2.5.8), recomendado **~44×44 px** (HIG).
- **R-UI-09** — criar layouts responsivos: mobile-first, breakpoints do Tailwind, sem scroll horizontal, reflow legível até **320 px** de largura (WCAG 1.4.10).
- **R-UI-10** — evitar elementos decorativos que prejudiquem função ou legibilidade. O glow/neon ciano da identidade não pode reduzir o contraste abaixo do AA.
- **R-UI-11** — preservar feedback visual para toda ação: estado de `loading` em submits, confirmação de sucesso e mensagem de erro clara (casa com o [padrão de resposta JSON, seção 88.3](padroes-de-codigo.md#883-padrão-de-código--estrutura-e-formato-de-resposta-proposta)).
- **R-UI-12** — respeitar `prefers-reduced-motion` em qualquer animação ou transição (HIG — Motion).
- **R-UI-13** — considerar acessibilidade desde o início, não como ajuste posterior.
- **R-UI-14** — **não alterar UI importante sem confirmação do responsável** (ver [Regras para o Claude Code, seção 62.2](../ai/regras-claude-code.md#622-uiux--regra-de-consulta-e-aprovação)).

### 103. HTML semântico e ARIA

- Usar elementos nativos primeiro (`<button>`, `<a>`, `<label>`, `<nav>`, `<main>`, `<table>`, `<fieldset>`) antes de recriar comportamento com `<div>` + ARIA. ARIA só quando não há elemento nativo equivalente.
- `<html lang>` correto conforme o idioma do site/tela.
- Toda imagem informativa com `alt` descritivo; imagem puramente decorativa com `alt=""`.
- Usar landmark regions (`header`, `nav`, `main`, `footer`) e oferecer um link "pular para o conteúdo" no layout base.
- Reforçar o escape de saída (`htmlspecialchars`) já exigido pela [ADR-008](../decisions/adr-008-frontend-php-puro.md): todo dado editorial ou de usuário renderizado dentro do HTML das Views deve ser escapado.

### 104. Formulários

- Label sempre visível e associada ao campo (`<label for>` ou envolvendo o input) — placeholder não substitui label.
- Erro descrito em **texto** ao lado do campo, não apenas borda vermelha; associar via `aria-describedby`.
- Ao submeter com erro, mover o foco para o primeiro campo inválido.
- Não desabilitar o botão de submit sem indicar o que falta para habilitá-lo.
- Agrupar campos relacionados com `<fieldset>`/`<legend>`.

### 105. Componentes — catálogo mínimo e consistência

Os componentes recorrentes vivem como **partials PHP reutilizáveis** em `src/Views/layout/` (ou subpasta `src/Views/layout/components/`) — um único lugar por componente, para cumprir a **R-UI-03**:

botão, input, select, textarea, checkbox/radio, card, badge/status, modal, toast/notificação, tabela, paginação, tabs, breadcrumb, sidebar/navegação, estado vazio, spinner/skeleton de loading.

Cada tela em `src/Views/{dashboard,production,planning,calendar,reports,settings,sites}/` (ver [Organização das Views, seção 12](arquitetura.md#12-organização-das-views)) compõe a partir desses partials — não redefine o componente localmente.

#### 105.1 Pixelito — o mascote-ajudante (2026-09-25)

O Pixelito (um passarinho azul) aparece **sempre dentro da mesma bolinha branca** (`.pixelito-bubble`, tamanhos `sm/md/lg/xl`) e faz três papéis para o redator — todos sem IA e sem custo:

| Onde | O que faz | Arquivos |
|---|---|---|
| Pop-up e lista de notificações | avisa; a expressão muda pelo **tipo** da notificação | `notification-toast.js`, `notifications/index.php` |
| Tutorial (`tour.js`) | é quem "fala" cada passo | `tour.js` (função `mood`) |
| Botão flutuante (canto inferior direito) | abre um **guia de dúvidas** ("como faço X?") com busca, respostas curtas e link para a tela | `layout/_pixelito.php`, `pixelito.js`, `Support/PixelitoGuide.php` |

**Fonte única de verdade:** `App\Support\Pixelito` guarda as expressões válidas e o mapa tipo de notificação → expressão. O layout entrega o mesmo mapa ao JS em `window.COMPOST_PIXELITO` (nunca há uma 2ª cópia no JS). As imagens ficam em `public/assets/pixelito/<expressão>.webp` — o **nome do arquivo é o nome da expressão**.

| Expressão | Quando aparece |
|---|---|
| `normal` | padrão; botão flutuante |
| `falando` | tutorial (passos comuns e abertura), vínculo a um site, cabeçalho do painel de ajuda |
| `falando-confiante` | atenção (`ATTENTION`); passos do tutorial que só apontam o menu |
| `falando-orgulhoso` | publicação com sucesso; fim do tutorial |
| `orgulhoso` | rascunho pronto para revisão (`ARTICLE_READY`) |
| `relaxado` | feedback |
| `sem-animo` | falha ao publicar; busca sem resultado no guia |

**Como adicionar uma expressão nova:** coloque o `.webp` (quadrado, cabeça enquadrada como as atuais) em `public/assets/pixelito/`, acrescente o nome em `Pixelito::EXPRESSIONS` e, se for de um tipo de notificação, no mapa `BY_NOTIFICATION_TYPE`. `PixelitoTest` falha se um tipo de notificação novo (`NotificationService::TYPE_*`) ficar sem expressão, ou se houver imagem sem registro.

**Como adicionar uma dúvida ao guia:** um item em `PixelitoGuide::topics()` — pergunta, resposta curta (≤ 420 caracteres, fiel à tela), link opcional (`{site}` vira o id do site atual). `PixelitoGuideTest` garante que todo link aponta para uma rota GET que existe.

Regras de UI: o botão é fixo (`z-index: 150` — acima do conteúdo e do menu, abaixo dos pop-ups e do tutorial) e só a bolinha "boia" por dentro (alvo de clique parado); `main` reserva `pb-24` para o botão não cobrir o fim da página; Esc/clique fora fecham; `prefers-reduced-motion` desliga as animações. **Fora do escopo desta versão:** chat livre com IA, Pixelito na tela de login, preferência de "silenciar" salva por usuário.

### 106. Checklist de UI antes de um PR

Espelha o formato do [checklist de padrão de código (seção 88.3)](padroes-de-codigo.md#883-padrão-de-código--estrutura-e-formato-de-resposta-proposta). Toda View nova ou alterada:

- [ ] Estados `hover`/`focus`/`active`/`disabled`/`loading`/`error` presentes onde aplicável (R-UI-04).
- [ ] Navegável 100% por teclado, com foco visível (R-UI-05).
- [ ] Contraste AA verificado — texto e componentes (R-UI-06).
- [ ] Nenhuma informação depende só de cor (R-UI-07).
- [ ] Alvos clicáveis ≥ 24 px; layout sem scroll horizontal até 320 px (R-UI-08, R-UI-09).
- [ ] HTML semântico; `alt` em imagens; toda saída escapada com `htmlspecialchars` (seção 103).
- [ ] Componente reutiliza partial existente, não duplica (R-UI-03).
- [ ] `prefers-reduced-motion` respeitado em animações (R-UI-12).

## Ver também

- [Identidade Visual — COMPOST](../product/identidade-visual.md) — paleta, tipografia, mockups
- [Convenções de Código (seção 88.4)](padroes-de-codigo.md#884-frontend--views--convenções-proposta) — convenções de código das Views
- [Regras para o Claude Code (seção 62.2)](../ai/regras-claude-code.md#622-uiux--regra-de-consulta-e-aprovação) — consulta e aprovação de mudanças de UI
- [Referências oficiais (seção 82)](../referencias.md#82-referências-oficiais-links)
- [Arquitetura — stack frontend (seção 5)](arquitetura.md#5-stack-do-projeto--frontend) e [Organização das Views (seção 12)](arquitetura.md#12-organização-das-views)

# Identidade Visual — COMPOST

O nome da plataforma é **COMPOST**. Estética: **retro-futurista / Y2K** — neon sobre
fundo escuro profundo, tipografia geométrica techy, superfícies planas com bordas
finas e brilho contido. **Sem** parecer site antigo: nada de fontes pixeladas,
botões com bisel/cromado, sombras de texto por toda parte ou textura de fundo
pesada. Referência mental: HUD de painel sci-fi limpo / vaporwave sóbrio.

**Modo:** dark-only por enquanto. Um modo claro pode entrar numa revisão futura —
por isso a paleta abaixo marca quais tons funcionam sobre fundo claro.

> **Status de implementação (migrada em 2026-09-03):** esta identidade está **implementada**
> no código — [tailwind.config.js](../../tailwind.config.js), `layout/base.php`/`layout/auth.php`,
> `font-display` (Orbitron) nos H1–H3 e KPIs de todas as Views, logo/ícone reais baixados para
> `public/assets/brand/` (favicon incluído), `cyan.light`/`cyan.dark` renomeados para
> `cyan.bright`/`cyan.pressed`/`cyan.dark` (a antiga classe `cyan-light` virou `cyan-bright` em
> todo o código). Definida em 2026-08-28, ficou registrada como "fase de acabamento" até o fluxo
> editorial inteiro (fases 6–9) estar pronto — só então foi aplicada.

## Marca

Logo e ícone **finais** (arte fechada):

| Asset | Origem | Uso |
|---|---|---|
| Ícone | `http://tutehi.com/wp-content/uploads/2026/08/iconcompost.webp` | favicon, navegação recolhida, avatar da app |
| Lockup completo | `http://tutehi.com/wp-content/uploads/2026/08/compostlogo-1.webp` | tela de login |
| Wordmark (recorte) | derivado do lockup via GD, `public/assets/brand/wordmark.png` | cabeçalho compacto, ao lado do ícone |

- **Símbolo:** pena/aparo branco com um laço, sobre um **círculo em gradiente
  teal** — claro no topo-esquerda, escuro embaixo-direita. Fundo transparente.
  Gradiente aproximado: `linear-gradient(135deg, #05A8C6, #06405A)` (amostrado do
  arquivo — bordas ~`#018EAC`, centro ~`#057493`, base ~`#04425A`).
- **Lockup:** símbolo + **COMPOST** (branco, display pesado com textura levemente
  desgastada — **é arte, não fonte**) + **EDITORIAL DASHBOARD** (ciano
  `#00D0F0`, caixa-alta, `letter-spacing` largo). O estilo do descritor
  "EDITORIAL DASHBOARD" (geométrica larga) é o que a fonte de UI **Orbitron**
  reproduz nos títulos de tela — coerente com o lockup.
- **Feito (2026-09-03):** os dois arquivos foram baixados para `public/assets/brand/`
  (`icon.webp`, `logo-lockup.webp`) — nada depende mais do host externo em runtime.
  O ícone também foi convertido (via GD) em `favicon-32.png`, `apple-touch-icon.png`
  (180×180) e `icon-512.png`; e a região só do texto "COMPOST" foi recortada do lockup
  em `wordmark.png` (pro cabeçalho compacto, que não cabe o lockup inteiro) — todos
  referenciados em `layout/base.php`/`layout/auth.php`.

## Mockups (referência de layout — precisam ser refeitos na nova paleta)

Três telas (login, visão geral, revisão de artigo), desenhadas na paleta antiga:

**https://claude.ai/code/artifact/0e329b33-7baa-43ec-a826-e1f75bfd817f**

> Servem só para a **estrutura** (sidebar, cards de status, KPIs, calendário). As
> cores e a tipografia mudam conforme esta doc.

## Paleta de cores

Contraste calculado sobre o fundo base `#050B0F`. Alvo WCAG 2.2 AA: **4,5:1**
(texto normal), **3:1** (texto grande ≥ 24px / 18,66px bold, componentes de UI,
anel de foco — [Parte 20, R-UI-06](../technical/ui-ux-frontend.md#102-regras-obrigatórias)).

### Superfícies — camadas com tinta azul-marinho

| Token | Papel | Hex |
|---|---|---|
| `void` | Fundo geral da aplicação | `#050B0F` |
| `surface` | Cards, inputs, painéis | `#063F59` |
| `surface-2` | Superfície elevada, sidebar, modais, linhas destacadas | `#0A2647` |
| `border` | Contorno de cards/inputs, divisórias | `#3D5266` |

Diferença de luminância entre camadas (base→surface ~1,76:1,
base→surface-2 ~1,30:1) — **por escolha estética**. Cards, inputs e sidebar
**não se apoiam só no preenchimento**: usar a **borda `#3D5266`**, sombra, e/ou um
fio de glow ciano em estados ativos/foco. Onde precisar de um separador mais
firme, usar `border` + o delta de fundo (agora perceptível graças ao azul) — ou
uma borda mais clara pontual `#4A6076`.

### Acento ciano (neon) — a assinatura da marca

| Token | Papel | Hex | vs `#050B0F` |
|---|---|---|---|
| `cyan.DEFAULT` | Acento primário, botão/CTA, estado ativo | `#00D0F0` | ~10,7:1 ✅ AA/AAA |
| `cyan.bright` | Hover, brilho/glow, anel de foco, realce sobre o neon | `#7FE8FF` | ~14,1:1 ✅ AA/AAA |
| `cyan.pressed` | Estado pressionado | `#00A8C4` | ~7,0:1 ✅ AA |
| `cyan.dark` | Ícone/borda-acento, único ciano OK sobre fundo **claro** | `#0092B0` | ~5,4:1 ✅ (≈4,1:1 sobre `#0A2647` → só texto grande/UI ali) |

### Azul — realce secundário

| Token | Papel | Hex | vs `#050B0F` |
|---|---|---|---|
| `blue.light` | Links, realce secundário, estado **info** | `#6FCFFF` | ~11,4:1 ✅ |
| `navy` | = `surface-2` (`#0A2647`) — fundo, não texto | `#0A2647` | — |
| `navy.deep` | = `surface` (`#063F59`) — fundo, não texto | `#063F59` | — |

### Texto

| Token | Papel | Hex | vs `#050B0F` |
|---|---|---|---|
| `text.primary` | Texto principal | `#F0F8FF` (aliceblue) | ~18,6:1 ✅ |
| `text.secondary` | Labels, texto de apoio | `#8FA6BC` | ~7,9:1 ✅ |
| `text.muted` | Terciário, timestamps, placeholders | `#7B8FA1` | ~6,0:1 ✅ |

> `#3D5266` (o "cinza-azulado" da paleta) dá só ~2,5:1 sobre o fundo — é cor de
> **borda/divisória**, nunca de texto. Os tons de texto de apoio acima foram
> derivados dele, mais claros, para passar no contraste.

### Semânticas — versão Y2K vibrante

| Token | Papel | Hex | vs `#050B0F` |
|---|---|---|---|
| `success` | Aprovado, indicadores positivos | `#3DF07A` | ~13,2:1 ✅ |
| `warning` | Revisão solicitada, pendências | `#FFC53D` | ~12,6:1 ✅ |
| `danger` | Bloqueado, erro, atrasado | `#FF5C7A` | ~6,7:1 ✅ (≈5,1:1 sobre `#0A2647`) |
| `info` | Em revisão / informativo | `#6FCFFF` (= `blue.light`) | ~11,4:1 ✅ |

Em **badge/pílula preenchida** com essas cores de fundo, o texto vai **escuro**
(`#050B0F`). Em texto/ícone sobre fundo escuro, usar a cor direto.

### Regras de contraste da paleta

1. **Botão com fundo ciano** (`#00D0F0` / `#00A8C4`) → texto **sempre escuro**
   (`#050B0F`). Texto claro sobre ciano fica ~1,7:1 e reprova.
2. **Superfícies** não se delimitam só pelo fill — sempre borda `#3D5266` e/ou
   sombra/glow.
3. **Anel de foco**: `#7FE8FF`, 2px, offset 2px (>13:1 — cobre WCAG 1.4.11).
4. **`#0092B0`** é o único ciano com contraste adequado sobre fundo claro — os
   outros dois cianos só funcionam sobre escuro.
5. Sobre a superfície azulada `#0A2647`, `cyan.dark` e `danger` caem para faixa
   de texto grande / UI (3:1) — não usar em texto normal ali.

## Tipografia

Retro-futurista, mas legível. Duas famílias (Google Fonts):

| Uso | Fonte | Pesos | Observação |
|---|---|---|---|
| **Títulos / display** (H1–H3, wordmark, rótulos de seção) | **Orbitron** | 500, 600, 700, 800 | Geométrica sci-fi. Caixa-alta + `letter-spacing` no wordmark e em rótulos de seção. Não usar em texto corrido. |
| **Números / métricas** (KPIs, custo, contadores, datas grandes) | **Orbitron** | 500, 700 | O "hit" retro-futurista nos números. |
| **Corpo / UI** (parágrafos, labels, botões, tabelas, formulários) | **Chakra Petch** | 300, 400, 500, 600, 700 | Quadrada, techy, mas confortável em 14–16px. 400 corpo, 500 labels/botões, 600–700 ênfase. |
| **Código / logs** (blocos de JSON dos pareceres da IA, mensagens de erro técnicas) | **JetBrains Mono** | 400, 600 | Mantida só para conteúdo genuinamente monoespaçado — legibilidade acima da estética aqui. |

Carregamento:

```html
<link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Orbitron:wght@500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
```

Fallbacks: Orbitron → `'Orbitron', ui-sans-serif, system-ui, sans-serif`;
Chakra Petch → `'Chakra Petch', ui-sans-serif, system-ui, -apple-system, sans-serif`;
JetBrains Mono → `'JetBrains Mono', ui-monospace, SFMono-Regular, monospace`.

## Guarda-corpos da estética ("sem parecer site antigo")

**Evitar:** fontes pixeladas/bitmap, botões com bisel/gradiente cromado, sombra
de texto genérica, `text-shadow` em parágrafo, textura de fundo acima de ~5% de
opacidade, starfield/gif, cantos muito arredondados (pílula) em tudo, skeuomorfismo.

**Usar:** superfícies planas + borda de 1px `#3D5266`; **glow contido** só em
primário / foco / ativo (`box-shadow` com `#00D0F0` a ~20–30% alpha); raio de
canto pequeno (2–6px); caixa-alta + `letter-spacing` em rótulos de seção e no
wordmark; bastante espaço negativo; alinhamento em grade; no máximo uma textura
sutil de scanline/gradiente a 3–5%. Movimento rápido (120–160ms) e já respeitando
`prefers-reduced-motion`.

**Logo/wordmark:** o `◆` no header hoje é placeholder — substituir pelos assets
reais da seção **Marca** (ícone + lockup). O wordmark "COMPOST" é arte fechada;
não recriar em fonte.

## Bloco Tailwind pronto (colar na fase de acabamento)

```js
// tailwind.config.js — theme.extend
colors: {
  base: '#050B0F',
  surface: '#063F59',
  'surface-2': '#0A2647',
  border: '#3D5266',
  'border-strong': '#4A6076',
  cyan: {
    DEFAULT: '#00D0F0',
    bright:  '#7FE8FF',
    pressed: '#00A8C4',
    dark:    '#0092B0',
  },
  blue: { light: '#6FCFFF' },
  // gradiente do símbolo da marca (usar em backgroundImage, não como cor de texto)
  // 'brand-grad': 'linear-gradient(135deg, #05A8C6, #06405A)'
  text: {
    primary:   '#F0F8FF',
    secondary: '#8FA6BC',
    muted:     '#7B8FA1',
  },
  success: '#3DF07A',
  warning: '#FFC53D',
  danger:  '#FF5C7A',
  info:    '#6FCFFF',
},
fontFamily: {
  display: ['Orbitron', 'ui-sans-serif', 'system-ui', 'sans-serif'],
  sans:    ['"Chakra Petch"', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'],
  mono:    ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
},
```

> **Migrado (2026-09-03).** `font-sans` deixou de ser Manrope e passou a ser Chakra Petch;
> `font-display` (Orbitron) aplicado a todo H1/H2/H3 e KPI numérico das Views (~50 pontos,
> ver `CHANGELOG.md`). `:focus-visible`/`.skip-link` em [layout/base.php](../../src/Views/layout/base.php)
> e [layout/auth.php](../../src/Views/layout/auth.php) já usam `#7FE8FF` (foco) e `#00D0F0`
> (fundo do skip-link). `cyan.light`/`cyan.dark` do config antigo viraram `cyan.bright`
> (`#7FE8FF`, hover — a classe `cyan-light` usada em ~37 lugares virou `cyan-bright`),
> `cyan.pressed` (`#00A8C4`) e `cyan.dark` (`#0092B0`, novo, ainda sem uso no código).

## Histórico

- **2026-08-28** — identidade final definida: paleta ciano+azul-marinho+neutros
  acima, estética retro-futurista/Y2K, tipografia Chakra Petch + Orbitron,
  semânticas Y2K vibrantes, dark-only. Logo e ícone finais recebidos (pena sobre
  círculo em gradiente teal + wordmark COMPOST desgastado + "EDITORIAL DASHBOARD").
  Folha de identidade visual: <https://claude.ai/code/artifact/fe0138c5-00b9-4641-acd3-e4b432969cec>.
  **Substitui** a paleta anterior (ciano único `#0AFFEF`, superfícies
  quase-monocromáticas `#101B2C`/`#16212A`, Manrope + JetBrains Mono) — que
  continua no código até a fase de acabamento.
- **2026-09-03** — **migração aplicada** (fase de acabamento, depois do fluxo editorial
  fases 6–9 completo): `tailwind.config.js` com a paleta/tipografia finais; ícone e lockup
  baixados para `public/assets/brand/` + favicons gerados via GD; `layout/base.php` (header
  compacto) e `layout/auth.php` (login: lockup completo) atualizados; `font-display` aplicado
  a todo H1/H2/H3 e número de KPI das Views; `cyan-light` → `cyan-bright` em todo o código
  (37 usos). Nenhuma mudança de conteúdo/lógica, só identidade visual.
- **2026-09-03 (mesmo dia)** — **wordmark real no header + efeitos globais**: a primeira
  passada tinha colocado "COMPOST" em texto Orbitron no header — a própria doc diz "o wordmark
  é arte, não fonte" (seção Marca), corrigido logo em seguida. `public/assets/brand/wordmark.png`
  (novo) recorta só a região do texto "COMPOST" do `logo-lockup.webp` via GD — o header usa
  ícone + esse wordmark, os dois como imagem. Camada de efeitos globais em `src/styles/input.css`
  (transições, glow ciano contido em botões primários, glow de foco em formulários, scrollbar
  temática, `.hover-card` nos cards clicáveis, `.status-dot` em indicadores "ao vivo", entrada
  suave do conteúdo) — tudo aditivo sobre as classes Tailwind já usadas nas Views, sem duplicar
  componente por tela, e respeitando `prefers-reduced-motion`. Ver bloco `[Acabamento]` do
  `CHANGELOG.md`.
- **2026-09-18** — **sistema de botões** (pedido do responsável: "esse degradê tá estranho,
  deixa todos em uma cor sólida … o hover pode ser um motion que acompanha o movimento do
  mouse e troca de cor com um brilho"): as ~5 receitas de classes Tailwind repetidas nas views
  (cheio ciano, contorno ciano/vermelho/cinza) viraram `.btn` + variantes sólidas em
  `src/styles/input.css` — `.btn-primary` (ciano), `.btn-secondary` (ardósia), `.btn-danger`,
  `.btn-success`. Repouso 100% sólido; no hover a cor troca pra um tom mais claro, ganha brilho
  e um holofote que **acompanha o mouse** dentro do botão (`assets/js/btn-fx.js`). Os cards
  clicáveis (`.hover-card`: início, Visão Geral do site) seguem a mesma linguagem e deixam de
  ter o degradê do "sheen". O glow ciano de `.bg-cyan:hover` (item acima) foi aposentado.
- **2026-09-18** — **seleções com o mesmo motion** (pedido do responsável: "aquele efeito do
  hover em motion, não só nele mas nas seleções em geral"): `.pick` em `src/styles/input.css`
  — cards de radio/checkbox (idioma, redatores, perfil, sites do usuário), interruptores em
  cartão (`.pick-success`, acende verde), logos da biblioteca (`.pick-light`) e chips
  (`.pick-chip`, `aria-pressed`). Holofote que segue o mouse (o `btn-fx.js` agora também
  alimenta `.pick`), "puxão" de ~2px, borda/fundo trocando de cor e brilho; marcado =
  aceso (CSS puro via `input:checked + .pick`). Seção Identidade do site refeita no padrão
  da Voz editorial: passos numerados, contadores, barra de preenchimento, chips de nicho.
- **2026-09-18** — **/sites redesenhada** (pedido do responsável: logo em header, tags na borda,
  destaques, botão de abrir mais bonito, busca e filtros): cada site é um card largo no mesmo
  sistema dos cards de artigo (`.article-card` — tom por situação, animação, tag sobre a borda) +
  `.hover-card` (holofote que segue o mouse). Tom: inativo = cinza, com problema (artigo
  travado/erro ou WordPress falhou) = vermelho, rascunho em revisão = ciano animado, WordPress sem
  conexão válida = âmbar, tudo em ordem = verde. Logo numa placa branca à esquerda, tags
  (Ativo + estado do WordPress) no canto de cima da borda, contadores Em revisão/Aprovados/Com
  problema e botão "Abrir site". Busca + chips de Situação/WordPress + idioma + ordenação, todos
  GET reais com filtro em tempo real (mesma técnica da Produção). Regras em `SiteListing`
  (testadas); dados em `SiteService::overview()` (1 consulta pra todos os sites).
- **2026-09-18** — **sidebar radial ("dial")** (pedido do responsável, referência de "rotary
  dial / radial selector"): a sidebar deixou de ser uma coluna reta. Um disco no canto da tela
  (avatar meio escondido, atalhos redondos de notificações/feedback/sair em arco, relógio de
  Brasília em cartão) e, em volta, um anel escuro com os itens de navegação ao longo de um
  arco, cada um na sua cor. O item do centro é o selecionado (pílula acesa + nome do grupo);
  ao mudar de seção o mostrador **gira** (itens e marcas) até o item novo — arrastar, roda do
  mouse, setas e Tab também giram, com inércia e encaixe. Em qualquer troca de página (links, envios de formulário, login) um véu em
  degradê (bordas nítidas com fio ciano) cobre tudo menos ela e só sai quando a página nova
  carrega (sem tempo fixo). O dial é fixo no desktop; no celular vira gaveta com botão redondo.
  Desempenho: cada quadro só escreve transform/opacity, a navegação começa na hora do clique e a
  sidebar busca as contagens numa consulta só (`SidebarService`) — o banco gerenciado custa ~280 ms
  por ida e volta e eram 5 por página. Tela `/profile` refeita no mesmo tema (foto grande com
  pré-visualização ao vivo, nome, papel e sites). Geometria em `assets/js/dial.js`
  (mesma fórmula do CSS), HTML em `layout/_dial.php`.
- **2026-09-18** — **notificações redesenhadas** (pedido do responsável: "mais bonita e mais
  interativa"): cabeçalho com contador grande das não lidas (sino com pulso, tag "Novas"/"Tudo em
  dia"), busca e filtros instantâneos (situação e tipo, com contagem), lista agrupada por dia
  (Hoje / Ontem / Esta semana / Mais antigas) e cards no sistema `.article-card` — colorido pelo
  tipo enquanto não lida, apagado depois de lida. Interação sem recarregar: marcar UMA como
  lida/não lida e "marcar todas" (fetch → JSON), atualizando contador, selo do sino na sidebar e
  cor do card; clicar no card abre. Endpoints novos: `POST /notifications/{id}/read|unread`.
- **2026-09-21** — **sidebar mais óbvia** (pedido do responsável: quem usa não é da área de
  tecnologia, os botões de navegação e "Meus sites" precisam ser óbvios pra qualquer pessoa
  entender de cara). Itens do anel maiores e mais legíveis: fonte `.82rem` → `.92rem` e peso 500 →
  600, ícone `1.65rem` → `1.9rem`, cor do texto mais clara (`#8FA6BC` → `#B9CADA`), faixa do disco
  `--band` `9rem` → `9.5rem` pra caber o texto maior sem cortar. Itens fora do centro (que antes
  quase desapareciam) ficam bem mais legíveis: piso de opacidade `.38` → `.62` em
  `assets/js/dial.js`. Títulos de seção ("Navegação", "Meus sites", "Site · X") maiores
  (`.58rem` → `.62rem`) e ganharam um ícone (casa / grade), reforçando de relance o que é
  navegação geral e o que é "meus sites".
- **2026-09-21** — **sem marcas de mostrador**: as marcas tipo ponteiro de relógio na borda do
  disco (`.dial-ticks`, giravam com a seleção) saíram — pedido do responsável, "deixa só a seta
  indicando onde é a página atual". Fica só a `.dial-marker`, fixa, apontando o item selecionado.
- **2026-09-21** — **navegação x site, diferença óbvia**: os separadores de grupo ("Navegação",
  "Meus sites", "Site · X") viraram um crachá preenchido (fundo + borda na cor do escopo), não só
  um fio fraco. Cada item do anel ganhou um trilho colorido fixo na borda esquerda e o texto já
  nasce tingido pelo tom do escopo (ciano/violeta), sempre visível — não só no hover/ativo como
  antes (só o ícone carregava cor). Pedido do responsável: "preciso que tenha uma diferença mais
  óbvia entre navegação e sites".
- **2026-09-21** — **degradê animado, seta por escopo, navegar só de scroll** (mesmo pedido, num
  fôlego só): a região preta atrás dos itens do anel (`.dial-band`) deixou de ser um preto parado
  — agora tem um degradê que gira sozinho, bem devagar (36s por volta, só `transform`/compositor,
  a mesma técnica do anel tracejado do avatar — nunca recalcula o degradê em si, mesmo o círculo
  sendo enorme). A seta fixa (`.dial-marker`) troca de cor sozinha conforme o item mais perto do
  centro: ciano na navegação, violeta dentro do site (`assets/js/dial.js` decide a cada quadro,
  classe `.dial.scope-site`). E a roda do mouse ganhou navegação: continua girando o mostrador,
  mas se parar de rolar num item diferente da página atual (550ms de pausa), navega sozinha pra
  ele — o clique continua indo direto, sem mudar.
- **2026-09-21** — **`/` (Início) redesenhada** (pedido do responsável: "imagem grande no centro,
  texto embaixo, mais parecida com as páginas atualizadas"): o cabeçalho virou um `.article-card`
  grande com a marca da COMPOST em destaque no centro (o ícone redondo, com o mesmo brilho ciano
  usado no logo do login) e a saudação embaixo dela, em vez da linha avatar+texto de antes. Duas
  seções novas: **Feedback**, sempre visível pra qualquer usuário, com dois botões ("Dar feedback"
  e "Ver feedback recebido"/"Ver meu histórico", conforme o papel) e o número de pendentes pro
  ADMIN; e **Notificações**, prévia das 4 mais recentes no mesmo sistema de card das notificações
  (tom pelo tipo, apagado se já lida), clicar marca como lida e abre — a lista cheia continua em
  `/notifications`. `HomeController` ganhou as consultas de `NotificationService` e
  `PlatformFeedbackService` pra isso. A foto do usuário (que tinha saído da tela) voltou como um
  selo pequeno sobre o canto da marca, linkando pro perfil. E "Meus sites"/"Sites" trocou a grade de
  miniaturas por linhas no mesmo header de `/sites` (placa de logo, nome grande, "Abrir site"), com
  o nicho como tag no canto de cima — uma versão mais simples (sem os fatos e estatísticas da tela
  cheia, que não cabem numa prévia).
- **2026-09-21** — **`/login` redesenhado** (pedido do responsável: "mais bonito" + "colocar uma
  transição ao logar"). Fundo com duas manchas de luz respirando nos cantos (`.auth-glow`, só
  `opacity`, reaproveita `article-breathe`) e a logo com um brilho pulsando atrás (`animate-pulse`
  do Tailwind). Os ícones de e-mail/senha viraram selos redondos tingidos (ciano, ou vermelho junto
  com a borda do campo quando dá erro), campos com mais respiro e uma sombra interna sutil. O botão
  "Entrar" ganha um estado de carregando (spinner + "Entrando…") na hora do clique, síncrono com o
  evento `submit` — feedback imediato antes do véu global (`assets/js/veil.js`, já dispara em
  qualquer envio de formulário) cobrir a tela na troca pra Início.

## Ver também

- [Visão geral do produto](visao-geral.md)
- [Parte 20 — UI/UX & Frontend Standards](../technical/ui-ux-frontend.md) — como esta identidade vira regra de UI e acessibilidade
- [Arquitetura — stack frontend](../technical/arquitetura.md#5-stack-do-projeto--frontend)
- [Roadmap](roadmap.md)

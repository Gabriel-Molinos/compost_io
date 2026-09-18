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

## Ver também

- [Visão geral do produto](visao-geral.md)
- [Parte 20 — UI/UX & Frontend Standards](../technical/ui-ux-frontend.md) — como esta identidade vira regra de UI e acessibilidade
- [Arquitetura — stack frontend](../technical/arquitetura.md#5-stack-do-projeto--frontend)
- [Roadmap](roadmap.md)

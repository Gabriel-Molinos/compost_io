# Identidade Visual — COMPOST `[NOVO — registrado nesta reorganização; não fazia parte do README original]`

O nome da plataforma é **COMPOST**. Logo e favicon já existem (círculo ciano com uma pena/palito de escrever, wordmark "DASHBOARD INTELIGENTE" em ciano com letras espaçadas).

## Mockups

Três telas de mockup (login, visão geral, revisão de artigo) foram desenhadas em dark mode com acento ciano/neon e publicadas como canvas de design:

**https://claude.ai/code/artifact/0e329b33-7baa-43ec-a826-e1f75bfd817f**

> O link é privado por padrão — compartilhe pelo menu da própria página se quiser que outra pessoa da equipe veja.

## Paleta de cores

| Token | Uso | Hex |
|---|---|---|
| Fundo quase-preto | Fundo geral da aplicação | `#050B0F` |
| Azul profundo (apoio) | Superfície de cards, sidebar, modais | `#101B2C` |
| Superfície 2 | Inputs, elementos aninhados | `#16212A` |
| Ciano neon (principal) | Acento primário, botão primário/CTA, estados ativos, hover, glow | `#0AFFEF` |
| Ciano claro | Realce sobre o neon, hover claro, indicador de foco | `#5CFFF3` |
| Ciano escuro | Texto/ícone ciano, bordas, estado pressionado, uso sobre superfícies claras | `#00B8AE` |
| Borda / divisória | Contorno de cards, inputs, separadores | `#24374D` |
| Texto primário | Texto principal | `#F2FAFB` |
| Texto secundário | Labels, texto de apoio | `#8CA3AC` / `#93A9B2` |
| Texto muted | Texto terciário, timestamps | `#7C929C` |
| Sucesso | Aprovado, indicadores positivos | `#34D399` |
| Alerta | Revisão solicitada, pendências | `#FBBF24` |
| Erro/bloqueado | Bloqueado, atrasado | `#F87171` |
| Info | Em revisão | `#818CF8` |

**Tipografia:** Manrope (UI/texto geral) + JetBrains Mono (números/métricas, KPIs).

Esta paleta e tipografia devem guiar a implementação real das Views em Tailwind CSS (ver [Stack — Frontend](../technical/arquitetura.md#5-stack-do-projeto--frontend)) quando a Fase 1 (Fundação) chegar na parte visual.

### Verificação de contraste (WCAG 2.2 AA)

Contraste calculado sobre o fundo base `#050B0F` e sobre a superfície `#101B2C`. Alvo: 4.5:1 (texto normal), 3:1 (texto grande ≥ 24px / 18.66px bold e componentes de UI / foco — [Parte 20, R-UI-06](../technical/ui-ux-frontend.md#102-regras-obrigatórias)).

| Par | vs `#050B0F` | vs `#101B2C` | Situação |
|---|---|---|---|
| Ciano neon `#0AFFEF` (texto/ícone) | ~15,6:1 | ~13,6:1 | ✅ passa AA e AAA |
| Ciano claro `#5CFFF3` (texto/ícone) | ~16:1 | ~14:1 | ✅ passa AA e AAA |
| Ciano escuro `#00B8AE` (texto/ícone) | ~8:1 | ~7:1 | ✅ passa AA |
| Texto primário `#F2FAFB` | ~18,7:1 | ~16,3:1 | ✅ |
| Texto secundário `#8CA3AC` / `#93A9B2` | ~7,5:1 | ~6,5:1 | ✅ |
| Texto muted `#7C929C` | ~6,1:1 | ~5,3:1 | ✅ (ajustado — era `#5B707A`, que reprovava a 3,8:1) |
| Sucesso `#34D399` | ~10,3:1 | ~9:1 | ✅ |
| Alerta `#FBBF24` | ~11,9:1 | ~10:1 | ✅ |
| Erro `#F87171` | ~7,2:1 | ~6,3:1 | ✅ |
| Info `#818CF8` | ~6,6:1 | ~5,8:1 | ✅ |

**Decisões tomadas sobre a paleta:**

1. **Texto muted** clareado de `#5B707A` (3,8:1 — reprovava) para **`#7C929C`** (~5–6:1), mantendo a hierarquia visual abaixo do texto secundário. Timestamps e texto terciário passam a ser legíveis em qualquer tamanho.
2. **Separação de camadas** — fundo `#050B0F`, superfície `#101B2C` e superfície 2 `#16212A` têm diferença de luminância pequena (~1,2:1 entre camadas), por escolha estética (dark profundo). Cards, inputs e a sidebar **não devem se apoiar só no preenchimento** para se delimitar: usar a **borda `#24374D`** e/ou sombra. O anel de foco usa o ciano `#0AFFEF` (13:1 — mais que suficiente para WCAG 1.4.11).
3. **Botões com fundo ciano** (`#0AFFEF` ou `#00B8AE`) — texto do botão sempre **escuro** (`#050B0F`). Texto claro sobre ciano fica em ~2,3:1 e reprova; texto escuro passa (8–15:1).
4. **`#00B8AE`** é a única variação de ciano com contraste adequado sobre **fundo claro** (caso algum contexto light apareça no futuro); `#0AFFEF` e `#5CFFF3` só funcionam sobre fundo escuro.

## Ver também

- [Visão geral do produto](visao-geral.md)
- [Parte 20 — UI/UX & Frontend Standards](../technical/ui-ux-frontend.md) — como esta identidade vira regra de UI e acessibilidade
- [Arquitetura — stack frontend](../technical/arquitetura.md#5-stack-do-projeto--frontend)
- [Roadmap — Fase 1](roadmap.md#71-roadmap-completo-fases-19)

# Parte 19 — Diagrama de Sequência do Fluxo de Produção `[PROPOSTA]`

### 98. Sequência de chamadas entre serviços `[PROPOSTA]`

```
Redator-Chefe          Backend (PHP)           AI Provider (Gemini)     Image Provider     WordPress
      │                        │                        │                     │                 │
      │  cria/edita Meta       │                        │                     │                 │
      ├───────────────────────▶│                        │                     │                 │
      │                        │  cria Job de produção   │                     │                 │
      │                        ├──────┐                 │                     │                 │
      │                        │      │ (fila)           │                     │                 │
      │                        │◀─────┘                 │                     │                 │
      │                        │  pesquisa + brief        │                     │                 │
      │                        ├───────────────────────▶│                     │                 │
      │                        │◀───────────────────────┤                     │                 │
      │                        │  gera artigo + SEO       │                     │                 │
      │                        ├───────────────────────▶│                     │                 │
      │                        │◀───────────────────────┤                     │                 │
      │                        │  solicita imagens         │                     │                 │
      │                        ├─────────────────────────────────────────────▶│                 │
      │                        │◀─────────────────────────────────────────────┤                 │
      │  artigo em IN_REVIEW    │                        │                     │                 │
      │◀───────────────────────┤                        │                     │                 │
      │  aprova + agenda        │                        │                     │                 │
      ├───────────────────────▶│                        │                     │                 │
      │                        │  publica no WordPress    │                     │                 │
      │                        ├─────────────────────────────────────────────────────────────────▶│
      │                        │◀─────────────────────────────────────────────────────────────────┤
      │  artigo PUBLISHED       │                        │                     │                 │
      │◀───────────────────────┤                        │                     │                 │
```

> Este diagrama detalha, em nível de chamadas, o mesmo fluxo já descrito em [Como a IA deve trabalhar (seção 21)](../editorial/fluxo-editorial.md#21-como-a-ia-deve-trabalhar) e na [Máquina de estados do artigo (seção 65)](requisitos.md#65-máquina-de-estados-do-artigo).

### 98.1 Sequência de rejeição → regeneração `[PROPOSTA]`

> Adicionado nesta reorganização, não existia no documento original.

```
Redator-Chefe          Backend (PHP)           AI Provider (Gemini)
      │                        │                        │
      │  rejeita artigo         │                        │
      │  (motivo + justificativa)                        │
      ├───────────────────────▶│                        │
      │                        │  artigo → REVISION_REQUESTED
      │                        ├──────┐                 │
      │                        │      │ registra feedback │
      │                        │◀─────┘                 │
      │                        │  verifica tentativas da linhagem (seção 29)
      │                        ├──────┐                 │
      │                        │      │                 │
      │                        │◀─────┘                 │
      │                        │                        │
      │           ┌────────────┴────────────┐            │
      │      < 3 tentativas?              esgotou 3 tentativas
      │           │                          │            │
      │           ▼                          ▼            │
      │  artigo → IN_PROGRESS         2 linhagens completas sem aprovação?
      │  regenera com feedback               │            │
      │           │                          ▼            │
      │           ├─────────────────────▶│              │
      │           │  pesquisa + brief novamente          │
      │           │◀──────────────────────┤              │
      │  artigo em IN_REVIEW novamente           artigo → BLOCKED
      │◀──────────┤                          (redator precisa decidir — seção 29)
```

> Cobre o mesmo fluxo descrito em [Feedback (seção 28)](../editorial/fluxo-editorial.md#28-feedback) e [Regeneração (seção 29)](../editorial/fluxo-editorial.md#29-regeneração).

### 98.2 Sequência de falha técnica → retry `[PROPOSTA]`

> Adicionado nesta reorganização, não existia no documento original.

```
Backend (PHP)              Fila (Redis)         AI Provider (Gemini)     Redator-Chefe
      │                        │                        │                     │
      │  consome job            │                        │                     │
      │◀───────────────────────┤                        │                     │
      │  chama etapa técnica (pesquisa/escrita/SEO/imagem)                      │
      ├─────────────────────────────────────────────────▶│                     │
      │                        │      timeout / erro de API / resposta inválida │
      │◀─────────────────────────────────────────────────┤                     │
      │  tentativa 1 falhou — aguarda backoff (30s)                             │
      ├──────┐                 │                        │                     │
      │      │                 │                        │                     │
      │◀─────┘                 │                        │                     │
      │  retry automático (tentativa 2)                                        │
      ├─────────────────────────────────────────────────▶│                     │
      │                        │             falha de novo│                     │
      │◀─────────────────────────────────────────────────┤                     │
      │  aguarda backoff (2min) — retry (tentativa 3)                          │
      ├─────────────────────────────────────────────────▶│                     │
      │                        │             falha de novo│                     │
      │◀─────────────────────────────────────────────────┤                     │
      │  esgotou as 3 tentativas técnicas (seção 96)                            │
      │  artigo → BLOCKED / ERROR, nunca falha silenciosamente                  │
      ├─────────────────────────────────────────────────────────────────────────▶│
      │                        │                        │      alerta visível   │
```

> Cobre o mesmo fluxo descrito em [Processamento assíncrono (seção 26)](../editorial/fluxo-editorial.md#26-processamento-assíncrono), [Política de retry/falha da IA (seção 96)](testes-e-observabilidade.md#96-política-de-retry--falha-da-ia-proposta) e a proposta de [Monitoramento e alertas (seção 94.1)](testes-e-observabilidade.md#941-monitoramento-e-alertas-proposta) — a diferença para a seção 98.1 é que aqui a falha é **técnica** (timeout/erro de API), não uma rejeição humana.

## Ver também

- [Requisitos — estado × ação por perfil](requisitos.md#651-estado--ação-possível-por-perfil-proposta)
- [Fluxo editorial — Feedback e Regeneração](../editorial/fluxo-editorial.md#28-feedback)
- [Testes e observabilidade — retry](testes-e-observabilidade.md#96-política-de-retry--falha-da-ia-proposta)

# Integração — Gemini API

> Doc interna da integração (integracoes.md §42). Fatia 4.2 do roadmap.

## Objetivo

Gerar texto para os passos editoriais da IA: planejamento, pesquisa, escrita,
SEO, compliance e revisão (integracoes.md §35). É o único provedor de texto da
plataforma hoje.

## Como funciona

```
PromptBuilder (docs/ai/ + banco)  ->  prompt (string)
        |
        v
GeminiProvider.generateJson(prompt, schema, systemInstruction)
        |
        v
GeminiClient  ->  POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
        |
        v
AIResult { text, json, promptTokens, outputTokens, thoughtsTokens, totalTokens, model }
```

- `GeminiConfig` — lê `.env`, monta a URL do endpoint.
- `GeminiClient` — só o HTTP (cURL), autenticação e tradução de erro. Não conhece prompts.
- `GeminiProvider` — implementa `App\Integrations\AIProvider`; monta o corpo
  `generateContent` (`contents`, `systemInstruction`, `generationConfig`) e
  extrai texto + `usageMetadata` da resposta.

Trocar de fornecedor = nova implementação de `AIProvider`, sem tocar nos módulos
editoriais (integracoes.md §41).

## Configuração

| Variável (`.env`) | Descrição | Padrão |
|---|---|---|
| `GEMINI_API_KEY` | Chave da API. Obrigatória. Nunca versionar nem logar. | — |
| `GEMINI_MODEL` | ID do modelo | `gemini-2.5-pro` |

Certificados: o PHP local usa OpenSSL sem `curl.cainfo`, então as chamadas HTTPS
de saída usam o bundle de CA versionado em `tools/cacert.pem` (`App\Support\CaBundle`).
Para atualizar: `curl -o tools/cacert.pem https://curl.se/ca/cacert.pem`.

## Endpoint usado

`POST /v1beta/models/{model}:generateContent`
Autenticação: header `x-goog-api-key: <GEMINI_API_KEY>`.

Saída estruturada: `generationConfig.responseMimeType = "application/json"` +
`generationConfig.responseSchema` (JSON Schema). O texto vem em
`candidates[0].content.parts[*].text`; tokens em `usageMetadata`.

## Tratamento de erros

`GeminiException extends App\Integrations\AIException`, com `retryable` e `httpStatus`.

| Situação | `retryable` |
|---|---|
| Timeout / falha de conexão / TLS (curl errno ≠ 0) | sim |
| HTTP 429 (rate limit) | sim |
| HTTP 5xx | sim |
| HTTP 400 / 401 / 403 (chave, request inválido) | não |
| Resposta sem `candidates` / bloqueada por safety | não |
| `finishReason: MAX_TOKENS` | sim |

A fila da Fase 4.3 usa `retryable` para decidir a repetição com backoff
(fluxo-editorial §96).

## Limitações / notas

- `gemini-2.5-pro` é um modelo *thinking*: gasta muitos tokens de raciocínio
  (`thoughtsTokenCount`), faturados como saída. `AIResult::thoughtsTokens` os
  expõe para o cálculo de custo (`ai_executions.cost`, requisitos §95).
- Sem streaming — a chamada é síncrona e pode levar dezenas de segundos.
- Sem cache de prompt e sem retry nesta fatia (retry entra na 4.3).
- O valor-limite de custo por site/mês ainda não existe (§95) — rodar a Fase 4
  com poucos artigos por vez.

## Teste

```
php bin/gemini_smoke.php
```

Faz uma chamada real pedindo saída estruturada e confere texto + contagem de
tokens. Precisa de `GEMINI_API_KEY` válida.

## Documentação oficial

- Text generation: https://ai.google.dev/gemini-api/docs/text-generation
- generateContent (REST): https://ai.google.dev/api/generate-content
- Structured output: https://ai.google.dev/gemini-api/docs/structured-output
- Modelos: https://ai.google.dev/gemini-api/docs/models
- API key: https://ai.google.dev/gemini-api/docs/api-key

## Exemplo (sem credencial)

```bash
curl -X POST \
  "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent" \
  -H "x-goog-api-key: $GEMINI_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
        "contents": [{ "role": "user", "parts": [{ "text": "Escreva uma frase." }] }]
      }'
```

# Integração — Nano Banana (geração de imagens)

> Doc interna da integração (integracoes.md §42). Fatia 5.2 do roadmap.

## Objetivo

Gerar as imagens dos artigos (destacada + corpo) a partir dos briefs visuais
produzidos pelo passo `image` (`docs/ai/image.md`). Serviço **separado** da
geração de texto (integracoes.md §36, ADR-004) — trocar de fornecedor/modelo não
deve afetar o resto da aplicação.

## Como funciona

```
passo image (docs/ai/image.md)  ->  briefs { prompt, alt_text, aspect_ratio, role }
        |
        v
ImageProvider.generate(ImageRequest)          // App\Integrations\Image
        |
        v
NanoBananaClient  ->  POST https://generativelanguage.googleapis.com/v1beta/interactions
        |
        v
ImageResult { bytes, mimeType, model, inputTokens, outputTokens, totalTokens }
```

- `NanoBananaConfig` — lê o `.env`, monta a URL do endpoint.
- `NanoBananaClient` — só o HTTP (cURL), autenticação e tradução de erro. Não
  conhece briefs. Espelha `Gemini\GeminiClient` (mesmo `x-goog-api-key`, mesma
  classificação `retryable`).
- `NanoBananaProvider` — implementa `App\Integrations\Image\ImageProvider`; monta
  o corpo `CreateInteraction` e extrai a imagem + `usage` da resposta.

O provedor devolve os **bytes crus**. A conversão para WebP
(`App\Support\ImageConverter`, ext-gd) e a gravação em
`public/assets/uploads/{site}/{article_id}/` (`App\Support\ImageStorage`,
integracoes.md §36.1) são feitas pelo `ArticlePipeline` na fatia 5.3.

## No pipeline (fatia 5.3)

Depois que o artigo entra em `IN_REVIEW`, o `ArticlePipeline`:

1. roda o passo `image` (brief visual, via Gemini) — 1 execução em `ai_executions`
   (`step=image`, `provider=gemini`), parecer em `article_ai_notes`;
2. para cada brief, gera a imagem pelo Nano Banana — a **destacada** em
   `IMAGE_FEATURED_OPTIONS` variações, cada **corpo** 1×;
3. converte para WebP se `ext-gd` existir (senão guarda o formato original + aviso);
4. grava o arquivo e cria a linha em `images` (`selected=0` — a escolha é do
   Redator-Chefe, fatia 5.4);
5. registra o lote como 1 execução `ai_executions` (`provider=nano-banana`,
   `cost` = soma de `ImagePricing::estimate`).

Falha de imagem **nunca bloqueia** o artigo (requisitos §65.1) — vira aviso.

## Configuração

| Variável (`.env`) | Descrição | Padrão |
|---|---|---|
| `IMAGE_API_KEY` | Chave da API de imagem. Vazio ⇒ usa `GEMINI_API_KEY` (mesma conta Google hoje). | — |
| `IMAGE_MODEL` | ID do modelo | `gemini-3-pro-image-preview` |
| `IMAGE_API_REVISION` | Header `Api-Revision` exigido pelo endpoint de interações | `2026-05-20` |
| `IMAGE_DEFAULT_SIZE` | Resolução padrão (`1K` \| `2K` \| `4K`) | `1K` |
| `IMAGE_FEATURED_OPTIONS` | Nº de variações da imagem destacada geradas para o Redator-Chefe escolher | `3` |

Certificados: mesma história do Gemini — o PHP local usa o bundle de CA
versionado em `tools/cacert.pem` (`App\Support\CaBundle`).

## Endpoint usado

`POST /v1beta/interactions` (API de Interações do Gemini 3 — o modelo de imagem
**não** usa `:generateContent`).

Headers: `x-goog-api-key: <chave>`, `Content-Type: application/json`,
`Api-Revision: <IMAGE_API_REVISION>`.

Corpo:

```json
{
  "model": "gemini-3-pro-image-preview",
  "input": "descrição visual em inglês",
  "response_format": { "type": "image", "aspect_ratio": "16:9", "image_size": "2K" }
}
```

`aspect_ratio` aceitos: `1:1`, `3:2`, `2:3`, `4:3`, `3:4`, `16:9`, `9:16`.
`image_size`: `1K`, `2K`, `4K` (custo cresce com a resolução).

## Resposta

A imagem vem **base64** dentro do passo `model_output`:

```
steps[].content[]  ->  { "type": "image", "data": "<base64>", "mime_type": "image/jpeg" }
```

`NanoBananaProvider` também aceita um atalho `output_image.data` caso a API passe
a devolvê-lo. Tokens em `usage.total_input_tokens` / `usage.total_output_tokens`
/ `usage.total_tokens` (a saída de imagem é contada em tokens — ver custo).

## Tratamento de erros

`ImageException extends App\Integrations\AIException`, com `retryable` e `httpStatus`.

| Situação | `retryable` |
|---|---|
| Timeout / falha de conexão / TLS (curl errno ≠ 0) | sim |
| HTTP 429 | sim |
| HTTP 5xx | sim |
| HTTP 400 / 401 / 403 | não |
| `status: "failed"` no corpo | não |
| Resposta sem parte de imagem | não |

## Limitações / notas

- **`ext-gd` precisa estar habilitada** para a conversão WebP. Hoje o PHP local
  **não** tem `gd` — habilitar no `php.ini` (`extension=gd`). Não é pacote
  Composer. Sem ela o pipeline guarda JPEG/PNG e registra um aviso; um artigo
  aprovado com imagem não-WebP falha o checklist de SEO (`seo.md`).
- Arquivos 2K vêm com 2,5–4 MB (JPEG); 1K fica ~0,7 MB. Preço por imagem é igual
  em 1K e 2K no `gemini-3-pro-image` — por isso o padrão é `1K`.
- Sem streaming — a chamada é síncrona (~20 s por imagem no teste).
- Sem retry nesta fatia (entra no pipeline, fatia 5.3, via `RetryRunner`).
- Modelo em `-preview`: o ID pode mudar quando sair de preview — por isso é
  `IMAGE_MODEL` no `.env`.

## Custo

Pela documentação oficial, ~US$ 0,13 por imagem no `gemini-3-pro-image`
(~US$ 0,067 no `*-flash-image`), variando por resolução. O cálculo de
`ai_executions.cost` entra na fatia 5.3.

## Teste

```
php bin/nanobanana_smoke.php ["prompt"] [aspect_ratio] [size]
```

Faz uma geração real e grava o arquivo em `sys_get_temp_dir()`. Precisa de
`IMAGE_API_KEY` (ou `GEMINI_API_KEY`) válida. Testado: `gemini-3-pro-image-preview`,
16:9, 1K → JPEG de ~690 KB em ~20 s.

## Documentação oficial

- Geração de imagens: https://ai.google.dev/gemini-api/docs/image-generation
- Guia Gemini 3 / Interactions API: https://ai.google.dev/gemini-api/docs/gemini-3
- Referência da Interactions API: https://ai.google.dev/api/interactions-api

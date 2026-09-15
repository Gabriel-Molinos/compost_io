# Parte 6 — Integrações Externas

### 34. Visão geral das integrações

A arquitetura terá:

```
AI Provider
    │
    ├── Content Generation
    ├── Research
    ├── SEO
    └── Review

Image Provider
    │
    └── Nano Banana
```

- **Texto** → Gemini API.
- **Imagens** → Nano Banana.
- **Desenvolvimento** → Claude Code será utilizado pelo desenvolvedor para auxiliar na implementação da plataforma. Claude **não** fará parte do runtime da plataforma.

Todas as integrações externas deverão ficar isoladas. Estrutura planejada:

```
backend/
└── src/
    └── integrations/
        ├── gemini/
        ├── image-generation/
        ├── wordpress/
        ├── mysql/
        └── redis/
```

Cada integração deverá possuir: configuração, cliente, serviço, tratamento de erros, tipos, testes quando aplicável. O restante da aplicação não deve precisar conhecer os detalhes internos da API externa.

### 35. Gemini API

Responsável por: planejamento, pesquisa, produção, SEO, revisão, análise de feedback e a análise narrativa do Centro de Inteligência Editorial (§33).

> **Implementado nas Fases 4 e 8.3.** `src/Integrations/Gemini/` (`GeminiClient`/`Config`/`Provider`/`Exception`) por trás da interface `AIProvider`. Pipeline de produção usa `generateJson` com `responseSchema` por passo; o Centro de Inteligência (`IntelligenceService`) usa o mesmo provider para as seis respostas da §33. Custo por chamada em `GeminiPricing`.

Documentação:
- Docs: https://ai.google.dev/gemini-api/docs
- Quickstart: https://ai.google.dev/gemini-api/docs/quickstart
- Geração de texto: https://ai.google.dev/gemini-api/docs/text-generation
- Modelos: https://ai.google.dev/gemini-api/docs/models
- Autenticação: https://ai.google.dev/gemini-api/docs/api-key
- Structured Outputs: https://ai.google.dev/gemini-api/docs/structured-output

### 36. Geração de imagens (Nano Banana)

A geração de imagens é um serviço separado da geração de texto. Implementação atual (Fase 5): modelo **Nano Banana Pro** (`gemini-3-pro-image-preview`), endpoint de Interações do Gemini 3.

A integração fica isolada em `src/Integrations/Image/` (interface `ImageProvider`, implementação `NanoBanana\NanoBananaProvider`) — trocar de fornecedor/modelo não afeta o restante. Detalhes técnicos: [docs/integrations/images.md](../integrations/images.md).

O serviço **converte as imagens para WebP** antes do upload para o WordPress (`App\Support\ImageConverter`, extensão `gd`), e o corpo do artigo pode conter **várias imagens** (não só a destacada) — a cadência e as regras de alt text ficam no [Checklist SEO On-Page — Imagens](../editorial/seo.md#imagens).

Documentação oficial: https://ai.google.dev/gemini-api/docs/image-generation

### 36.1 Armazenamento das imagens geradas

> **Decisão tomada (Fase 5.3), aprovada pelo responsável.**

- As imagens geradas ficam em `public/assets/uploads/{site_id}/{article_id}/` (`App\Support\ImageStorage`), com o caminho web referenciado em `images.url` (ver [schema — seção 87](schema.md#87-tabelas--estado-atual-migration-0001)). O diretório é `gitignored`.
- Simples e sem dependência externa nova — coerente com a filosofia "sem dependência pesada" das ADRs 002/006, adequado para a fase inicial com poucos sites.
- Quando o artigo é aprovado e agendado, a imagem escolhida (`images.selected = 1`) é enviada para a **media library do WordPress do site** via REST API (ver [seção 37](#37-wordpress-rest-api)) — Fase 7. A cópia local pode ser mantida como histórico ou removida depois do envio confirmado.

Object storage (S3 ou equivalente) fica registrado como possível revisão futura, se o volume de imagens/sites (Fase 9 — escala) tornar o disco local um gargalo — não é decisão para a primeira versão.

### 37. WordPress REST API

Responsável por: consultar categorias, consultar autores, enviar mídia, criar artigos, definir categorias, definir autores, agendar, verificar publicação.

> **Implementado na Fase 7.** `src/Integrations/WordPress/` (`WordPressClient` + `Config`/`Exception`, `InternalLinkResolver`, `BodyImageInjector`); credencial por site cifrada com libsodium (`site_wordpress_connections`, `App\Support\Crypto`). Autenticação por Application Password (Basic). Post criado como `future` (`date_gmt`) ou `publish`. Ver [CHANGELOG — Fase 7](../../CHANGELOG.md).

Documentação:
- Handbook: https://developer.wordpress.org/rest-api/
- Referência: https://developer.wordpress.org/rest-api/reference/
- Posts: https://developer.wordpress.org/rest-api/reference/posts/
- Media: https://developer.wordpress.org/rest-api/reference/media/
- Categories: https://developer.wordpress.org/rest-api/reference/categories/
- Users: https://developer.wordpress.org/rest-api/reference/users/

### 38. MySQL (uso na integração)

Banco oficial da plataforma.

- Reference Manual: https://dev.mysql.com/doc/refman/8.4/en/
- Tutorial: https://dev.mysql.com/doc/refman/8.4/en/tutorial.html

### 39. Beekeeper Studio (uso na integração)

Ferramenta utilizada pela equipe para administrar e consultar o MySQL. O Beekeeper **não** participa como servidor ou camada da aplicação — a aplicação se conecta diretamente ao MySQL.

```
Navegador
    ↓
PHP (PDO)
    ↓
MySQL
    ↑
Beekeeper Studio
```

Documentação: https://docs.beekeeperstudio.io/

### 40. NotebookLM / Gemini Notebook Enterprise

> **Status da integração: planejamento.** Não implementar nesta primeira etapa.

A plataforma poderá utilizar o ecossistema NotebookLM como uma camada de conhecimento e pesquisa baseada em fontes.

**Importante:** o NotebookLM **não** será o banco de dados da plataforma e **não** substituirá o MySQL. Também não deve ser tratado como o motor principal de geração dos artigos. Sua função será fornecer um ambiente estruturado de conhecimento para fontes e documentos utilizados durante a pesquisa editorial.

**40.1 Arquitetura conceitual**

```
Site
 ↓
Base de conhecimento
 ↓
Notebook
 ↓
Fontes
 ↓
Pesquisa editorial
 ↓
Gemini
 ↓
Artigo
```

**40.2 Organização por site**

Cada site poderá possuir seu próprio notebook/base de conhecimento (ex.: `Valorizei → Notebook Editorial Valorizei`, `Nizelo → Notebook Editorial Nizelo`, `Gavsy → Notebook Editorial Gavsy`), evitando mistura de fontes entre sites diferentes.

**40.3 Fontes**

Poderão incluir: documentos, PDFs, arquivos de texto, Markdown, CSV, URLs, conteúdo web, documentos Google, apresentações, vídeos públicos do YouTube, outros formatos suportados oficialmente. A documentação oficial do NotebookLM informa suporte a diversos tipos de fontes, incluindo documentos, PDFs, URLs, arquivos de texto, imagens e vídeos públicos do YouTube.

**40.4 Possíveis fontes por site**

Um notebook de um site financeiro poderá conter: Banco Central, Receita Federal, CVM, documentos de bancos, documentos de produtos, políticas, manuais, estudos, documentos internos, fontes editoriais aprovadas — permitindo que a pesquisa editorial seja feita utilizando fontes previamente selecionadas e confiáveis.

**40.5 Gerenciamento programático**

Caso seja utilizado o Gemini Notebook Enterprise, a plataforma poderá utilizar as APIs oficiais do Google Cloud para: criar, recuperar, listar, compartilhar e excluir notebooks; adicionar, recuperar e remover fontes. A documentação oficial apresenta métodos como `notebooks.create`, `notebooks.get`, `notebooks.listRecentlyViewed`, `notebooks.share` e APIs para gerenciamento de fontes.

**40.6 Autenticação**

A integração programática do Gemini Notebook Enterprise utiliza autenticação do Google Cloud e permissões IAM. Não armazenar credenciais pessoais de usuários diretamente no código. A documentação oficial mostra o uso de credenciais do Google Cloud e permissões IAM para acessar e compartilhar notebooks.

**40.7 Importação de fontes** (fluxo futuro)

```
Administrador
 ↓
Seleciona site
 ↓
Seleciona notebook
 ↓
Adiciona fonte
 ↓
Sistema registra fonte
 ↓
Notebook recebe fonte
```

As fontes também devem possuir registro interno na plataforma para rastreabilidade.

**40.8 Registro interno**

Mesmo utilizando NotebookLM/Gemini Notebook, o sistema deverá manter seu próprio registro: `knowledge_sources`, com campos conceituais: id, site_id, notebook_id, source_id, nome, tipo, URL ou referência, status, data de inclusão, data de atualização, responsável. O Notebook não deve ser usado como substituto do banco MySQL da plataforma.

**40.9 Isolamento por site**

Nunca utilizar automaticamente fontes de um site em outro (ex.: `Valorizei → fontes financeiras`, `Nizelo → fontes de tecnologia`, `Gavsy → fontes do respectivo nicho`). A aplicação deve sempre saber: `site_id → notebook_id → source_ids`.

**40.10 Papel no processo editorial**

```
META
 ↓
TEMA
 ↓
PESQUISA
 ↓
FONTES DO SITE
 ↓
NOTEBOOK
 ↓
CONTEXTUALIZAÇÃO
 ↓
GEMINI
 ↓
BRIEF
 ↓
ARTIGO
```

A geração textual continuará sendo uma responsabilidade do serviço de conteúdo/Gemini definido pela plataforma.

**40.11 Importante sobre escopo**

Não tornar a plataforma dependente do NotebookLM. Se a integração estiver indisponível, a produção editorial principal deve continuar funcionando através dos demais serviços. O NotebookLM deve ser uma integração desacoplada, através de uma camada **Knowledge Provider** que permita futuramente `NotebookLM`, `Outro provedor` ou `Base própria de documentos` sem alterar o restante do sistema.

**40.12 Documentação oficial**

- NotebookLM — Central de ajuda: https://support.google.com/gemininotebook/
- NotebookLM — fontes: https://support.google.com/notebooklm/answer/16215270
- Gemini Notebook Enterprise — APIs de notebooks: https://docs.cloud.google.com/gemini/enterprise/notebooklm-enterprise/docs/api-notebooks
- Gemini Notebook Enterprise — APIs de fontes: https://docs.cloud.google.com/gemini/enterprise/notebooklm-enterprise/docs/api-notebooks-sources
- Gemini Notebook Enterprise — gerenciamento e compartilhamento: https://docs.cloud.google.com/gemini/enterprise/notebooklm-enterprise/docs/api-notebooks

**40.13 Status da integração — antes de implementar, confirmar:**

- qual produto do NotebookLM será utilizado;
- se a empresa possui Gemini Notebook Enterprise;
- licenças disponíveis;
- projeto Google Cloud;
- permissões IAM;
- região;
- método de autenticação;
- quais tipos de fontes serão utilizados.

A integração só deverá ser implementada depois dessa validação.

### 41. Abstração de provedores

A plataforma deverá utilizar interfaces para serviços externos. Exemplo conceitual:

```
AIProvider
├── generateContent()
├── reviewContent()
└── generateStructuredOutput()
```

Implementação atual: `GeminiProvider`. Futuramente, outro fornecedor poderá ser adicionado sem alterar os módulos editoriais. O mesmo princípio deve ser aplicado para `ImageProvider`, `WordPressProvider`, `StorageProvider`.

### 42. Organização das integrações no código

Ver estrutura de diretórios em [seção 34](#34-visão-geral-das-integrações). Todas as integrações devem possuir **documentação interna** em:

```
docs/
├── integrations/
│   ├── gemini.md
│   ├── images.md
│   ├── wordpress.md
│   ├── mysql.md
│   └── beekeeper.md
```

Cada documento deve conter: objetivo, como funciona, configuração, variáveis necessárias, endpoints utilizados, tratamento de erros, limitações, documentação oficial, exemplos sem credenciais reais.

### 43. Google Identity Services (Login com Google)

Responsável por: autenticação alternativa de usuários já cadastrados (sem auto-cadastro) via conta Google, na tela `/login`.

> **Implementado nesta sessão.** Front-end: Google Identity Services (`accounts.google.com/gsi/client`, carregado em `src/Views/layout/auth.php`), botão em modo redirect-POST (`data-login_uri="/callback.php"`, `src/Views/auth/login.php`). Back-end: `public/callback.php` (standalone, fora do `Router` — exceção deliberada) + `google/apiclient` (`Google\Client::verifyIdToken`) + `AuthService::attemptGoogle()`. CSRF via double-submit `g_csrf_token` (`App\Support\GoogleCsrf`). Vínculo em `users.google_id` (migration `0022_users_google_id.sql`). Ver [docs/technical/requisitos.md §64.2](requisitos.md#642-autenticação).

**Configuração no Google Cloud Console** (achado real 2026-09-15: faltou isso
na primeira configuração e deu `Erro 400: redirect_uri_mismatch`) — como
`data-ux_mode="redirect"` faz o GIS usar o mecanismo de redirect do OAuth de
verdade por baixo dos panos, o `data-login_uri` precisa estar cadastrado em
**"URIs de redirecionamento autorizados"** do Client ID (Credenciais → OAuth
2.0 Client ID), não só em "Origens JavaScript autorizadas":
```
http://localhost:8000/callback.php   (dev)
https://SEU-DOMINIO/callback.php     (produção, quando existir)
```

Documentação:
- Sign In With Google (HTML API): https://developers.google.com/identity/gsi/web/guides/overview
- Modos de UX (popup vs. redirect — é aqui que o requisito de redirect URI está documentado): https://developers.google.com/identity/gsi/web/guides/UX-modes
- Verificação do ID token no servidor: https://developers.google.com/identity/sign-in/web/backend-auth
- `google/apiclient` (Packagist): https://packagist.org/packages/google/apiclient

## Ver também

- [Segurança — credenciais por site](seguranca.md#46-credenciais-específicas-por-site)
- [Fluxo editorial — Imagens](../editorial/fluxo-editorial.md#25-imagens) e [Integração WordPress](../editorial/fluxo-editorial.md#31-integração-wordpress)
- [Referências oficiais](../referencias.md) — links de documentação de cada tecnologia
- [ADR-004 — Separação Gemini/Nano Banana](../decisions/adr-004-gemini-nano-banana.md) e [ADR-005 — WordPress REST API](../decisions/adr-005-wordpress-rest-api.md)

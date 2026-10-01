# Parte 7 — Segurança e Credenciais

### 43. Gerenciamento de credenciais, APIs e secrets — regras gerais

A plataforma utilizará diferentes serviços externos, bancos de dados e APIs. Todas as credenciais devem ser tratadas como informações sensíveis.

Nenhuma senha, API Key, token ou credencial deve ser:

- escrita diretamente no código;
- colocada no README;
- colocada em prompts;
- enviada para o frontend;
- armazenada em arquivos versionados pelo Git;
- exibida em logs.

As credenciais deverão ser fornecidas através de variáveis de ambiente ou de um mecanismo seguro de gerenciamento de secrets.

> Os valores reais de credenciais deste projeto (host do banco, usuário, etc.) não ficam neste documento — ver [docs/technical/credenciais-privadas.md](credenciais-privadas.md), arquivo local que não vai pro Git.

### 44. Arquivos de ambiente (.env / .env.example)

O projeto deverá possuir `.env` e `.env.example`.

**`.env`** — contém os valores reais utilizados localmente ou no ambiente de execução. **Este arquivo não deve ser versionado.** Os valores reais atuais ficam em [docs/technical/credenciais-privadas.md](credenciais-privadas.md) (arquivo local, fora do Git) — a estrutura de variáveis é a mesma do `.env.example` abaixo.

**`.env.example`** — deverá conter somente os nomes das variáveis necessárias, sem valores reais:

```env
APP_ENV=
APP_URL=

DATABASE_HOST=
DATABASE_PORT=
DATABASE_NAME=
DATABASE_USER=
DATABASE_PASSWORD=
DATABASE_SSL=

GEMINI_API_KEY=

REDIS_URL=

IMAGE_API_KEY=
```

### 45. Informações da conexão do banco (ambiente de desenvolvimento)

Os dados reais de conexão (host, porta, nome do banco, usuário) ficam em [docs/technical/credenciais-privadas.md](credenciais-privadas.md) — arquivo local, fora do Git, não neste documento.

A senha deverá existir somente em variável de ambiente ou em um sistema seguro de secrets (ver seção 44).

**Nunca** escrever `password = ...` dentro do código, e **nunca** commitar `.env` no Git.

### 46. Credenciais específicas por site

A plataforma possui aproximadamente 60+ sites, e cada site pode possuir suas próprias credenciais e integrações. Portanto, **não** armazenar todas as credenciais de WordPress no `.env` global.

A arquitetura deve permitir:

```
SITE
│
├── Configuração editorial
├── Categorias
├── Regras
├── Compliance
│
└── Integrações
    │
    └── WordPress
        ├── URL
        ├── usuário
        └── credencial
```

Exemplo: `Valorizei → WordPress próprio`, `Nizelo → WordPress próprio`, `Gavsy → WordPress próprio`.

A aplicação deve sempre utilizar a credencial correspondente ao site do artigo.

### 47. Modelo de armazenamento de credenciais

Credenciais específicas de cada site não devem aparecer na interface comum do Redator-Chefe. O Administrador poderá configurar a integração. O sistema deve armazenar as credenciais de forma segura.

A arquitetura deverá prever futuramente a utilização de: secret manager, criptografia de dados sensíveis, rotação de credenciais, controle de acesso administrativo.

O banco **não** deve armazenar senhas ou tokens em texto puro quando houver necessidade de armazenamento persistente.

### 48. Política de segurança para credenciais

Toda integração deverá seguir estas regras:

1. Nunca colocar credenciais diretamente no código.
2. Nunca colocar credenciais no README.
3. Nunca colocar credenciais em prompts enviados para ferramentas de IA.
4. Nunca retornar credenciais para o frontend.
5. Nunca registrar credenciais em logs.
6. Nunca enviar credenciais para ferramentas externas sem necessidade.
7. Credenciais específicas de cada site devem ser isoladas.
8. Acesso às credenciais deve ser limitado ao backend e aos administradores autorizados.

### 49. Controle de acesso às integrações

Somente o backend pode acessar: Gemini API, banco de dados, Redis, credenciais WordPress, serviços externos.

O navegador (HTML/JavaScript entregue ao usuário) **nunca** deve receber `GEMINI_API_KEY`, `DATABASE_PASSWORD`, `WORDPRESS_PASSWORD` ou qualquer outro secret — mesmo sendo uma aplicação PHP única, essas credenciais ficam restritas à camada de Services/Integrations, nunca chegam a uma View ou resposta enviada ao navegador.

*(Até 2026-09-30 havia uma exceção aqui: o `GOOGLE_CLIENT_ID` do Login com Google, público por natureza. O login com Google foi removido e a variável saiu junto.)*

Fluxo correto:
```
Navegador → PHP (Controller/Service) → Serviço externo
```

Fluxo proibido:
```
Navegador → API Key externa → Serviço externo
```

> **Reduzir login direto no wp-admin** (decisão do responsável 2026-09-28, motivada por invasão real de site antes). O COMPOST fala com o WordPress via Application Password (`site_wordpress_connections`, §46-47) — um caminho de autenticação separado do login normal por sessão/cookie. Quanto menos gente abre o wp-admin no navegador, menor a exposição a ataques que dependem exatamente disso (credencial roubada por phishing, plugin malicioso/XSS disparando ao logar, sequestro de sessão) — não é uma segunda via independente do WordPress (se a REST API dele estiver fora do ar, o COMPOST também não consegue nada), é reduzir QUEM precisa abrir o painel de verdade. Por isso a tela "Todos os posts" (`fluxo-editorial.md §31`) cobre listar, copiar link e **editar** qualquer post — inclusive os feitos direto no WordPress — sem sair do COMPOST, e guarda uma cópia local do conteúdo (`wordpress_posts_mirror`) como backup adicional contra uma invasão futura. Isto não substitui as práticas básicas de segurança do WordPress em si (senha forte, 2FA, plugins atualizados) — é redução de exposição, não blindagem.

### 50. Checklist de segurança antes de cada deploy

Antes de colocar qualquer versão em produção. Auditado de verdade contra o
código em 2026-09-17 (não é só um template — cada item abaixo foi checado
com grep/leitura real do repositório nesta data; refazer a checagem sempre
que uma dessas áreas mudar, não confiar no ✅ indefinidamente):

- [x] `.env` não está versionado — confirmado em `.gitignore` + `git ls-files` (não rastreado).
- [x] Nenhuma API Key aparece no código — grep por padrões de chave (`AIza...`, `GOCSPX-...`, chave privada PEM) em `src/`, `public/`, `bin/`, `routes/`: nenhuma ocorrência.
- [x] Nenhuma senha aparece no código — mesma varredura, nenhuma ocorrência; `DATABASE_PASSWORD` só é lido via `Env::get()` em `Connection.php`.
- [x] Nenhuma credencial aparece nos logs — os 2 únicos `error_log()` do projeto (`Connection.php:50`, `HomeController.php:57`) logam `$e->getMessage()` de falha de conexão, que não inclui senha (comentário no código já documenta essa preocupação).
- [x] Credenciais WordPress estão separadas por site — cada site tem sua própria linha de conexão WordPress cifrada (§46/§47), nunca um valor global no `.env`.
- [x] Frontend não possui secrets — nenhuma View lê o `.env` pra mandar ao navegador (o `GOOGLE_CLIENT_ID` do antigo login com Google saiu em 2026-09-30).
- [x] CORS — não há nenhum header `Access-Control-Allow-*` no projeto, **de propósito**: é uma app PHP server-rendered same-origin, sem API consumida por outra origem no browser. Ausência de CORS é o estado correto aqui, não uma lacuna.
- [ ] Acesso ao banco está restrito — depende da infraestrutura (allowlist de IP/"trusted sources" no cluster gerenciado), não do código; **confirmar manualmente na hospedagem escolhida antes do deploy**, não dá pra auditar por aqui. `DATABASE_SSL=true` já está configurado (TLS ligado), mas com `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT = false` — verificação de certificado do servidor desligada de propósito (decisão do responsável, 2026-09-17: não é prioridade agora).
- [x] Usuários possuem permissões adequadas — `Router::dispatch()` centraliza a checagem (`admin: true` em 19 rotas, incluindo a mais destrutiva, `/sites/{id}/delete`); nenhuma rota sensível depende de checagem manual espalhada por Controller.
- [x] Integrações externas possuem tratamento de erro — WordPress (13 pontos de `catch (WordPressException)` entre Controllers/Services); Gemini/Nano Banana (`GeminiException`/`ImageException` estendem `AIException`, capturadas via `catch (Throwable)` em `ArticlePipeline` — cada imagem/etapa falha isoladamente com warning, nunca derruba o pipeline inteiro — e em `ArticleJobHandlers`, que sempre marca o artigo como `ERROR` e notifica antes de relançar).
- [x] Credenciais antigas foram removidas/rotacionadas quando necessário — `GA_CLIENT_SECRET`/`GA_REFRESH_TOKEN` (colados em texto puro numa conversa anteriormente) já foram rotacionados pelo responsável.

**Único item que fica de fato pendente pra quando a hospedagem for decidida:**
acesso ao banco restrito por rede/firewall — não é algo que o código resolva.

## Ver também

- [Credenciais privadas](credenciais-privadas.md) — arquivo local, fora do Git, com os valores reais
- [Requisitos — Autenticação](requisitos.md#642-autenticação)
- [Regras para o Claude Code](../ai/regras-claude-code.md) — o que precisa de aprovação antes de mexer em segurança
- [Setup e operações — CI (proposta)](setup-e-operacoes.md#781-cideploy-proposta)

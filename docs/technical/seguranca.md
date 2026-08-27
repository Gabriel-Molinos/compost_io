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

Fluxo correto:
```
Navegador → PHP (Controller/Service) → Serviço externo
```

Fluxo proibido:
```
Navegador → API Key externa → Serviço externo
```

### 50. Checklist de segurança antes de cada deploy

Antes de colocar qualquer versão em produção:

- [ ] `.env` não está versionado.
- [ ] Nenhuma API Key aparece no código.
- [ ] Nenhuma senha aparece no código.
- [ ] Nenhuma credencial aparece nos logs.
- [ ] Credenciais WordPress estão separadas por site.
- [ ] Frontend não possui secrets.
- [ ] CORS está configurado corretamente.
- [ ] Acesso ao banco está restrito.
- [ ] Usuários possuem permissões adequadas.
- [ ] Integrações externas possuem tratamento de erro.
- [ ] Credenciais antigas foram removidas/rotacionadas quando necessário.

## Ver também

- [Credenciais privadas](credenciais-privadas.md) — arquivo local, fora do Git, com os valores reais
- [Requisitos — Autenticação](requisitos.md#642-autenticação)
- [Regras para o Claude Code](../ai/regras-claude-code.md) — o que precisa de aprovação antes de mexer em segurança
- [Setup e operações — CI (proposta)](setup-e-operacoes.md#781-cideploy-proposta)

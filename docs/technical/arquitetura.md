# Parte 2 — Stack e Arquitetura Técnica

### 5. Stack do projeto — Frontend

> **Decisão registrada:** o frontend **não** utilizará Node.js em nenhuma etapa (nem build, nem runtime). Ver [ADR-008](../decisions/adr-008-frontend-php-puro.md): esta seção substitui a definição anterior (Next.js/React/TypeScript).

- **PHP puro** — o mesmo backend PHP (ver [seção 6](#6-stack-do-projeto--backend)) também é responsável por montar e devolver o HTML das telas (Views), usando arquivos `.php` com HTML e `<?= ?>` como template — sem biblioteca de templates (ex. Twig) e sem framework de frontend.
- **Tailwind CSS** — mantido para estilização, mas compilado através do **Tailwind CLI standalone** (executável binário oficial, sem depender de Node.js/npm). Documentação: https://tailwindcss.com/blog/standalone-cli
- **JavaScript vanilla** — usado apenas onde houver necessidade real de interatividade no navegador (ex.: chamadas AJAX a endpoints internos, componentes de UI simples). Sem React, Vue ou qualquer framework de componentes.

### 6. Stack do projeto — Backend

> **Decisão registrada:** o backend será **PHP puro com PDO**, sem framework — conexão e queries manuais. Ver [ADR-002](../decisions/adr-002-php-pdo.md) e a [Regra de transparência](../ai/regras-claude-code.md#59-regra-de-transparência): esta seção substitui a definição anterior (Node.js/NestJS). Este mesmo PHP também é responsável por renderizar as Views (ver [seção 5](#5-stack-do-projeto--frontend) e [seção 10](#10-padrão-mvc-adaptado)) — não é apenas uma API que responde JSON.

- **PHP** — linguagem utilizada para estruturar toda a aplicação: rotas, regras de negócio e renderização das telas.
- **PDO (PHP Data Objects)** — extensão nativa do PHP usada para conectar e executar queries no MySQL, com suporte a prepared statements (essencial para evitar SQL injection, já que não há ORM intermediando as queries).
- **Redis** — utilizado para a fila de processamento assíncrono das produções de IA (ver [seção 26](../editorial/fluxo-editorial.md#26-processamento-assíncrono)). Deixou de ser algo "futuro" — faz parte da stack confirmada.
- Sem framework — rotas, controllers e services serão organizados manualmente (ver [seção 10 — Padrão MVC adaptado](#10-padrão-mvc-adaptado) e [seção 13 — Organização do backend](#13-organização-do-backend)).
- **Composer** será usado apenas para autoload de classes (PSR-4) e gerenciamento de dependências pontuais (ex.: cliente HTTP para chamadas às APIs externas, cliente Redis), não para trazer um framework completo.

### 7. Banco de dados — MySQL

O banco oficial da aplicação será **MySQL**. Ele armazenará:

- usuários;
- sites;
- permissões;
- categorias;
- metas;
- regras editoriais;
- artigos;
- versões;
- feedbacks;
- imagens;
- agendamentos;
- execuções de IA;
- custos;
- logs;
- relatórios.

A documentação oficial do MySQL contém referência de SQL, administração, segurança, índices, transações, chaves estrangeiras e conectores, que serão utilizados durante o desenvolvimento.

### 8. Beekeeper Studio

O Beekeeper Studio **não é o banco de dados**. Ele será utilizado como ferramenta para:

- conectar ao MySQL;
- visualizar tabelas;
- executar SQL;
- consultar dados;
- inspecionar registros;
- auxiliar na administração do banco durante o desenvolvimento.

O Beekeeper Studio suporta MySQL e oferece uma interface gráfica para gerenciamento de bancos.

### 9. Arquitetura geral

```
Navegador
   ↓
PHP (Controllers/Services/Views + PDO)
   ↓
MySQL
   ↑
Beekeeper Studio
```

```
                         USUÁRIO
                            │
                            ▼
                       Navegador
                    (HTML + Tailwind CSS
                     + JavaScript vanilla)
                            │
                          HTTP
                            │
                            ▼
                ┌─────────────────────┐
                │     PHP (puro)      │
                │     Controllers     │
                │      Services       │
                │        Views        │
                │      PDO/MySQL      │
                └─────┬──────┬────────┘
                      │      │
             ┌────────┘      └─────────────┐
             ▼                              ▼
        ┌─────────┐                   ┌────────────┐
        │  MySQL  │                   │ IA/Serviços│
        └─────────┘                   └─────┬──────┘
                                             │
                                  ┌──────────┼──────────┐
                                  ▼          ▼          ▼
                               Gemini    Nano       WordPress
                                         Banana        API
```

### 10. Padrão MVC adaptado

O backend seguirá uma organização inspirada no padrão MVC, implementada manualmente em **PHP puro** (sem framework) — um roteador simples direciona a requisição ao Controller correspondente, que chama o Service, que executa as queries via PDO.

**Model** — Representa os dados e entidades do sistema (classes simples, sem ORM). Exemplos: `User`, `Site`, `Article`, `Goal`, `Category`, `EditorialRule`, `Feedback`, `Schedule`.

**View** — Arquivos PHP (`.php`) com HTML e Tailwind CSS, responsáveis por mostrar: dashboards, artigos, metas, calendários, relatórios, configurações. O Controller monta os dados e inclui a View correspondente — não há biblioteca de templates, apenas PHP puro. Endpoints internos que precisem responder JSON (ex.: chamadas AJAX de JavaScript vanilla) continuam existindo, mas a maioria das rotas responde HTML diretamente.

**Controller** — Responsável por receber requisições HTTP e encaminhá-las aos serviços. Exemplo conceitual de rotas (mapeadas manualmente para funções/classes de Controller):
```
GET  /sites
POST /goals
GET  /articles
POST /articles/:id/approve
```

**Service** — Contém as regras de negócio e chama a camada de acesso a dados (PDO). Exemplo: `GoalService`, `ArticleService`, `SiteService`, `EditorialRuleService`, `AIService`, `WordPressService`, `ReportService`.

Fluxo de uma requisição:

```
Requisição HTTP
      ↓
Router (public/index.php)
      ↓
Controller (valida entrada, chama Service)
      ↓
Service (regra de negócio)
      ↓
Model / PDO (query preparada no MySQL)
      ↓
Controller (inclui a View)
      ↓
Resposta HTML (ou JSON, em endpoints AJAX)
```

### 11. Arquitetura de diretórios

A estrutura inicial planejada será uma **aplicação PHP única** (sem separação `frontend/`/`backend/`):

```
editorial-dashboard/
│
├── public/
│   ├── index.php               ← front controller / ponto de entrada das requisições
│   └── assets/
│       ├── css/                ← CSS compilado pelo Tailwind CLI standalone
│       └── js/                 ← JavaScript vanilla
│
├── src/
│   │
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── SiteController.php
│   │   ├── PermissionController.php
│   │   ├── CategoryController.php
│   │   ├── GoalController.php
│   │   ├── ArticleController.php
│   │   ├── EditorialRuleController.php
│   │   ├── FeedbackController.php
│   │   ├── ImageController.php
│   │   ├── SchedulingController.php
│   │   └── ReportController.php
│   │
│   ├── Services/
│   │   ├── AuthService.php
│   │   ├── UserService.php
│   │   ├── SiteService.php
│   │   ├── PermissionService.php
│   │   ├── GoalService.php
│   │   ├── ArticleService.php
│   │   ├── EditorialRuleService.php
│   │   ├── FeedbackService.php
│   │   ├── AIService.php
│   │   ├── ImageService.php
│   │   ├── WordPressService.php
│   │   ├── SchedulingService.php
│   │   └── ReportService.php
│   │
│   ├── Views/
│   │   ├── layout/              ← layout base (header, sidebar, footer)
│   │   ├── dashboard/
│   │   ├── production/
│   │   ├── planning/
│   │   ├── calendar/
│   │   ├── reports/
│   │   ├── settings/
│   │   └── sites/
│   │
│   ├── Models/
│   ├── Integrations/
│   │   ├── Gemini/
│   │   ├── ImageGeneration/
│   │   ├── WordPress/
│   │   └── Redis/
│   │
│   ├── Database/
│   │   └── Connection.php       ← instância PDO centralizada
│   │
│   ├── Config/
│   └── Router.php
│
├── routes/
│   └── web.php
│
├── vendor/                      ← gerado pelo Composer
├── composer.json
├── composer.lock
│
├── database/
│   ├── migrations/
│   └── seeds/
│
├── docs/
│   ├── product/
│   ├── editorial/
│   ├── technical/
│   └── ai/
│
├── .env.example
├── .gitignore
└── README.md
```

> Esta estrutura substitui a antiga separação `frontend/` (Next.js) + `backend/` (PHP) — ver [ADR-008](../decisions/adr-008-frontend-php-puro.md). A separação por domínio continua existindo através de `Controllers/`, `Services/` e agora também `Views/`, todos dentro da mesma aplicação PHP.

> A estrutura acima é uma arquitetura planejada — não significa que todos os diretórios precisam ser criados imediatamente.

> **Fase 1:** o esqueleto já existe — `public/index.php` (front controller), `src/Router.php`, `src/Config/Env.php`, `src/Database/Connection.php`, `src/View.php`, `src/Controllers/`, `src/Views/layout/` + `home/`, `routes/web.php`, `database/migrate.php`. Ver [Getting Started (Parte 13)](setup-e-operacoes.md#84-passo-a-passo-de-instalação). Alguns nomes diferem levemente do desenho acima (ex.: `src/Router.php` na raiz de `src/`, não em `src/Config/`).

### 12. Organização das Views

As Views (`src/Views/`) têm responsabilidade exclusivamente de apresentação — recebem dados já prontos do Controller e não contêm regra de negócio.

| Diretório | Responsabilidade |
|---|---|
| `src/Views/dashboard/` | Dashboard principal |
| `src/Views/production/` | Produção de artigos |
| `src/Views/planning/` | Metas e planejamento |
| `src/Views/calendar/` | Calendário editorial |
| `src/Views/reports/` | Relatórios |
| `src/Views/settings/` | Configurações editoriais |

### 13. Organização do backend

O backend será dividido por domínio, cada um com seu par Controller + Service (PHP puro, sem módulos de framework).

| Domínio | Controller / Service | Responsabilidade |
|---|---|---|
| Sites | `SiteController` / `SiteService` | Sites |
| Usuários | `UserController` / `UserService` | Usuários |
| Permissões | `PermissionController` / `PermissionService` | Acessos por site |
| Metas | `GoalController` / `GoalService` | Metas editoriais |
| Artigos | `ArticleController` / `ArticleService` | Artigos |
| Regras editoriais | `EditorialRuleController` / `EditorialRuleService` | Interesses, não-interesses e regras |
| IA | `AIService` (`Integrations/Gemini/`) | Orquestração das IAs |
| Imagens | `ImageController` / `ImageService` (`Integrations/ImageGeneration/`) | Geração de imagens |
| WordPress | `WordPressService` (`Integrations/WordPress/`) | Integração com WordPress |
| Agendamento | `SchedulingController` / `SchedulingService` | Agendamentos |
| Relatórios | `ReportController` / `ReportService` | Relatórios |

## Ver também

- [Convenções de código](padroes-de-codigo.md) — como o padrão MVC acima vira código de verdade
- [ADR-002 — PHP puro com PDO](../decisions/adr-002-php-pdo.md) e [ADR-008 — Frontend em PHP puro](../decisions/adr-008-frontend-php-puro.md)
- [Setup e operações](setup-e-operacoes.md) — como rodar essa arquitetura localmente
- [Requisitos — contrato da API](requisitos.md#67-contrato-da-api-endpoints)

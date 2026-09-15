# Parte 11 — Operações e Infraestrutura do Projeto

### 76. Estratégia de backup e recuperação

- **Banco:** backup periódico.
- **Arquivos:** backup de imagens.
- **Configurações:** versionadas.
- **Restore:** processo documentado.

### 77. Ambiente de desenvolvimento

Ferramentas necessárias:

- PHP (sempre a versão estável mais recente disponível no momento da criação do projeto) — única linguagem da aplicação (backend + Views)
- Composer — gerenciador de dependências do PHP (autoload das classes)
- Tailwind CLI standalone — executável binário para compilar o CSS, sem Node.js/npm (ver [seção 5](arquitetura.md#5-stack-do-projeto--frontend))
- Servidor web para rodar o PHP (servidor embutido `php -S` em dev, ou Apache/Nginx + PHP-FPM em produção)
- Redis — para a fila de processamento assíncrono (ver [seção 26](../editorial/fluxo-editorial.md#26-processamento-assíncrono))
- Docker (se for usar)
- MySQL
- Beekeeper Studio
- Git
- VS Code
- Claude Code

#### 77.1 Redis local (dev)

Sem instância gerenciada ainda (ao contrário do MySQL, que já aponta pra um cluster DigitalOcean — ver `.env`); localmente, qualquer uma destas opções sobe um Redis em `127.0.0.1:6379`, batendo com o `REDIS_URL` de exemplo do `.env.example`:

- **Docker** (mais simples se já estiver instalado): `docker run -d --name dashredatora-redis -p 6379:6379 redis:7-alpine`
- **WSL:** `sudo apt install redis-server && redis-server`
- **Memurai** (Redis nativo pra Windows, sem WSL/Docker): https://www.memurai.com/

Com o Redis no ar, `REDIS_URL=redis://127.0.0.1:6379` no `.env` e `QUEUE_DRIVER=redis` (na linha de comando ou no `.env`), `php bin/queue_smoke.php` e `php bin/worker.php` testam o push→reserve→execute de ponta a ponta (ver `docs/technical/fila-ia.md`).

> Node.js **não** faz parte do ambiente de desenvolvimento — ver [ADR-008](../decisions/adr-008-frontend-php-puro.md).

Checklist de configuração:

- Como instalar
- Como configurar `.env`
- Como iniciar a aplicação
- Como compilar o CSS (Tailwind CLI)
- Como conectar banco

### 78. Git / versionamento

Branches planejadas:

- `main`
- `develop`
- `feature/*`
- `fix/*`

### 78.1 CI/deploy `[PROPOSTA]`

> **Precisa aprovação.** GitHub Actions no free tier (lint + PHPUnit em cada PR, ver [seção 91](padroes-de-codigo.md#91-padrão-de-branches-detalhamento)) — sem serviço pago. Deploy segue manual (`git pull` + restart) por enquanto; nada mais elaborado se justifica no tamanho atual do projeto.

---

> ⚠️ **As seções ainda marcadas `[PROPOSTA]` daqui em diante** (partes 15, 17–19 e trechos de 16) preenchem lacunas comuns de documentação inicial e devem ser revisadas antes de virar regra — [Regra de não-invenção](../ai/regras-claude-code.md#58-regra-de-não-invenção). A **Parte 13** (getting started) e o **schema da Parte 14** já foram implementados e validados na Fase 1.

## Parte 13 — Getting Started (Ambiente Local)

> A Fase 1 (Fundação) já foi montada — os comandos abaixo são reais e testados.

### 83. Pré-requisitos

- **PHP 8.1+** com as extensões `pdo_mysql`, `openssl`, `mbstring`, `curl` e
  `sodium` habilitadas (`sodium` cifra as credenciais WordPress — ver Fase 7).
  No Windows, editar `C:\php\php.ini` e descomentar `extension=pdo_mysql`,
  `extension=curl` e `extension=sodium` (`openssl` e `mbstring` já costumam vir
  ligadas). Conferir com `php -m`.
  No Windows, definir também `extension_dir` com **caminho absoluto**
  (`extension_dir = "C:\php\ext"`) — com o valor relativo padrão (`"ext"`) o
  `php -S` iniciado de dentro da pasta do projeto não encontra as DLLs e as
  extensões silenciosamente não carregam.
- **Composer** (autoload PSR-4 — ver [ADR-002](../decisions/adr-002-php-pdo.md)).
- **Tailwind CLI standalone** (binário, sem Node.js — ver [seção 5](arquitetura.md#5-stack-do-projeto--frontend)).
- Acesso ao **MySQL** de dev (banco `redacao` na DigitalOcean — valores em [credenciais-privadas.md](credenciais-privadas.md)).
- **Git**.
- Beekeeper Studio (opcional, inspeção do banco).
- Gemini API Key (só a partir da Fase 4).

> Node.js **não** é necessário — ver [ADR-008](../decisions/adr-008-frontend-php-puro.md).

### 84. Passo a passo de instalação

```bash
# 1. Repositório
git clone <url-do-repositorio>
cd dashredatora

# 2. Ambiente — copiar e preencher (valores reais em docs/technical/credenciais-privadas.md)
cp .env.example .env

# 3. Dependências PHP + autoload
composer install

# 4. Tailwind CLI standalone — baixar o binário para tools/ (uma vez)
mkdir -p tools
curl -L -o tools/tailwindcss.exe \
  https://github.com/tailwindlabs/tailwindcss/releases/download/v3.4.17/tailwindcss-windows-x64.exe

# 5. Compilar o CSS
composer css            # build único (--minify)
# ou:  composer css:watch   (recompila ao salvar as Views)

# 6. Migrations — aplica as pendentes de database/migrations/
php database/migrate.php

# 7. Subir a aplicação (servidor embutido do PHP)
composer serve          # = php -S localhost:8080 -t public
```

Abrir <http://localhost:8080> — a home mostra o status da conexão com o banco.

### 85. Comandos úteis

| Comando | O que faz |
|---|---|
| `composer serve` | Sobe a aplicação em `localhost:8080` (servidor embutido do PHP) |
| `composer install` | Instala dependências PHP e gera o autoload |
| `composer css` / `composer css:watch` | Compila o CSS (Tailwind CLI standalone) |
| `php database/migrate.php` | Aplica migrations `.sql` pendentes; registra em `schema_migrations` |
| `php database/migrate.php --status` | Lista migrations pendentes sem aplicar |
| `curl localhost:8080/api/health/db` | Checagem de saúde da conexão com o banco (JSON) |
| `vendor/bin/phpunit` | Roda os testes (quando existirem) |
| `vendor/bin/php-cs-fixer fix` | Linter/formatter PSR-12 (quando configurado) |

> Sem framework, não há `migration:generate` de ORM — as migrations são SQL escrito à mão em `database/migrations/`, aplicadas por [`database/migrate.php`](../../database/migrate.php) em ordem alfabética. A tabela `schema_migrations` guarda quais já rodaram.

## Ver também

- [Arquitetura](arquitetura.md) — stack completa que este ambiente sobe
- [Segurança — checklist de deploy](seguranca.md#50-checklist-de-segurança-antes-de-cada-deploy)
- [Roadmap — Fase 1](../product/roadmap.md#71-roadmap-completo-fases-19)

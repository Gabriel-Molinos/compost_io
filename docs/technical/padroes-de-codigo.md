# Parte 15 — Convenções de Código `[PROPOSTA]`

### 88. Nomenclatura e estilo `[PROPOSTA]`

> **Decisão registrada:** identificadores de código (classes, métodos, variáveis, colunas/tabelas do banco) são sempre em **inglês**. Português é usado apenas em conteúdo voltado ao usuário final (textos de tela, mensagens de erro exibidas na interface, dados editoriais).

**PHP (backend + Views)** — conforme já definido na [seção 6](arquitetura.md#6-stack-do-projeto--backend):
- Seguir o padrão **PSR-12** (estilo de código) e **PSR-4** (autoload) da comunidade PHP.
- Classes em `PascalCase` com sufixo do papel: `SiteController.php`, `SiteService.php`.
- Métodos e variáveis em `camelCase`, em inglês (ex.: `$articleService`, `findById()`).
- Arquivos de View em `snake_case` ou `kebab-case`, refletindo a rota (ex.: `src/Views/dashboard/index.php`).
- Nada de lógica de negócio dentro de controllers — controllers só validam entrada e orquestram, services concentram as regras (ver [seção 10](arquitetura.md#10-padrão-mvc-adaptado)).
- Toda query ao banco passa por PDO com **prepared statements** — nunca concatenar valores diretamente na string SQL.

**JavaScript vanilla** — usado nas Views para interatividade (ver [seção 5](arquitetura.md#5-stack-do-projeto--frontend)):
- Funções e variáveis em `camelCase`, em inglês.
- Sem build step — arquivos servidos diretamente de `public/assets/js/`.

### 88.1 Padrão MVC do backend — exemplo de implementação `[PROPOSTA]`

Complementa a [seção 10 — Padrão MVC adaptado](arquitetura.md#10-padrão-mvc-adaptado) com um exemplo concreto de como Controller, Service e Model/PDO se conectam em PHP puro, sem framework.

```php
// src/Database/Connection.php — conexão PDO centralizada (Model / acesso a dados)
<?php

namespace App\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $host = $_ENV['DATABASE_HOST'];
            $port = $_ENV['DATABASE_PORT'];
            $db   = $_ENV['DATABASE_NAME'];
            $user = $_ENV['DATABASE_USER'];
            $pass = $_ENV['DATABASE_PASSWORD'];

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                // nunca expor detalhes de conexão/credenciais no erro (ver seção 48)
                throw new PDOException('Falha ao conectar ao banco de dados.');
            }
        }

        return self::$instance;
    }
}
```

```php
// src/Services/SiteService.php — regra de negócio + acesso a dados via PDO
<?php

namespace App\Services;

use App\Database\Connection;

class SiteService
{
    public function findById(int $id): ?array
    {
        $pdo = Connection::get();

        $stmt = $pdo->prepare('SELECT * FROM sites WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        $site = $stmt->fetch();

        return $site ?: null;
    }

    public function create(array $data): int
    {
        $pdo = Connection::get();

        $stmt = $pdo->prepare(
            'INSERT INTO sites (name, niche, language, created_at)
             VALUES (:name, :niche, :language, NOW())'
        );

        $stmt->execute([
            'name'     => $data['name'],
            'niche'    => $data['niche'],
            'language' => $data['language'],
        ]);

        return (int) $pdo->lastInsertId();
    }
}
```

```php
// src/Controllers/SiteController.php — recebe a requisição, valida, chama o Service
<?php

namespace App\Controllers;

use App\Services\SiteService;

class SiteController
{
    public function __construct(private SiteService $siteService)
    {
    }

    public function show(int $id): void
    {
        $site = $this->siteService->findById($id);

        if ($site === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Site não encontrado.']);
            return;
        }

        echo json_encode($site);
    }
}
```

Fluxo desse exemplo: `Router → SiteController::show() → SiteService::findById() → Connection::get() (PDO) → MySQL → resposta`. Este exemplo específico responde JSON (endpoint interno, ver [seção 68](requisitos.md#68-contrato-de-endpoints-internos-ajax-exemplo)); a maioria das rotas da aplicação inclui uma View em PHP e responde HTML (ver [seção 10](arquitetura.md#10-padrão-mvc-adaptado)).

### 88.2 Clean Code — princípios adotados `[PROPOSTA]`

Princípios que devem guiar todo o código PHP (backend, Services e Views) e o JavaScript vanilla usado nas Views:

- **Nomes descritivos** — variáveis, funções e classes com nomes que explicam o que fazem, sem abreviações obscuras (`$articleService`, não `$as`).
- **Funções pequenas, uma responsabilidade** — cada função/método faz uma coisa. Se o nome precisa de "e" (`validarEEnviar`), provavelmente deveria ser duas funções.
- **Evitar aninhamento profundo** — preferir *early return* a encadear vários `if` aninhados.
- **DRY sem over-engineering** — evitar duplicação de lógica, mas sem criar abstrações genéricas para casos que só acontecem uma vez.
- **Comentários só quando o código não se explica sozinho** — comentário bom explica o *porquê*, não o *o quê* (o código já diz o quê).
- **Nada de números/strings mágicos** — usar constantes nomeadas (ex.: `const MAX_REGENERATION_ATTEMPTS = 3;`, coerente com a [seção 29 — Regeneração](../editorial/fluxo-editorial.md#29-regeneração)).
- **Tratamento de erros explícito** — nunca engolir exceção silenciosamente; sempre logar ou propagar (reforça a [Regra de transparência, seção 59](../ai/regras-claude-code.md#59-regra-de-transparência)).
- **Controllers finos, Services gordos** — controller não decide regra de negócio, só orquestra (ver [seção 10](arquitetura.md#10-padrão-mvc-adaptado) e o exemplo em [88.1](#881-padrão-mvc-do-backend--exemplo-de-implementação-proposta)).

### 88.3 Padrão de código — estrutura e formato de resposta `[PROPOSTA]`

**Padrão de resposta da API (JSON):**

```json
// Sucesso
{
  "data": { "id": 1, "name": "Valorizei" }
}

// Erro
{
  "error": {
    "message": "Site não encontrado.",
    "code": "SITE_NOT_FOUND"
  }
}
```

Manter esse formato consistente em todos os endpoints facilita o consumo pelo JavaScript vanilla (ver [Contrato de endpoints internos, seção 68](requisitos.md#68-contrato-de-endpoints-internos-ajax-exemplo)).

**Tratamento de erros centralizado** — um único ponto (ex.: no `Router.php` ou num `ErrorHandler.php`) captura exceções não tratadas, formata a resposta de erro padrão acima, define o `http_response_code` correto, e loga o erro (ver [seção 94 — Logging](testes-e-observabilidade.md#94-logging-proposta)) sem nunca expor stack trace ou dados sensíveis ao cliente.

**Checklist de padrão de código antes de um PR:**

- [ ] Segue PSR-12 (validado por `vendor/bin/php-cs-fixer fix --dry-run`, ver [seção 89](#89-lint--format-proposta)).
- [ ] Nenhuma query concatena valores diretamente — só prepared statements.
- [ ] Controller não contém regra de negócio.
- [ ] Erros são tratados, nunca silenciados.
- [ ] Nomes de variáveis/funções/classes em inglês (ver [seção 88](#88-nomenclatura-e-estilo-proposta)).
- [ ] Mudança de View passou pelo [checklist de UI (Parte 20, seção 106)](ui-ux-frontend.md#106-checklist-de-ui-antes-de-um-pr).

### 88.4 Frontend / Views — convenções `[PROPOSTA]`

Convenções de código para as Views PHP (`src/Views/`), o CSS compilado pelo Tailwind e o JavaScript vanilla. O detalhe de UX, componentes e acessibilidade está na [Parte 20 — UI/UX & Frontend Standards](ui-ux-frontend.md).

- **HTML semântico primeiro** — usar o elemento nativo (`<button>`, `<a>`, `<label>`, `<nav>`, `<table>`) antes de recriar com `<div>` + ARIA.
- **Tailwind sem CSS custom desnecessário** — preferir utility classes; CSS próprio só para o que o Tailwind não cobre (ex.: regra global de `:focus-visible`, `prefers-reduced-motion`).
- **Foco visível global** — o CSS compilado deve garantir indicador de foco visível em todo elemento interativo (não remover `outline` sem substituto).
- **Escape de saída** — `htmlspecialchars` em todo dado dinâmico renderizado na View (reforça a [ADR-008](../decisions/adr-008-frontend-php-puro.md); Views em PHP puro não têm sandboxing de XSS).
- **JS vanilla progressivo** — onde for viável, a tela funciona sem JavaScript; o JS acrescenta interatividade, não é pré-requisito para o conteúdo aparecer.
- **Componentes reutilizáveis** — partials em `src/Views/layout/`, um único lugar por componente (ver [Parte 20, seção 105](ui-ux-frontend.md#105-componentes--catálogo-mínimo-e-consistência)).

### 89. Lint / Format `[PROPOSTA]`

> **Decisão registrada:** ferramenta escolhida é o **PHP-CS-Fixer**, configurado para PSR-12 — corrige automaticamente em vez de só apontar problemas, reduzindo a superfície de ferramentas (coerente com a filosofia das ADRs 002/006).

- **PHP:** PHP-CS-Fixer, configurado para PSR-12.
- Rodar lint como parte do CI antes de merge, quando o CI existir.

### 90. Padrão de commits `[PROPOSTA]`

Sugestão: [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `docs:`, `refactor:`, `chore:`), o que facilita gerar changelog automaticamente (ver [seção 99](../../CHANGELOG.md)).

### 91. Padrão de branches (detalhamento)

Complementa a [seção 78](setup-e-operacoes.md#78-git--versionamento):

- `main` → produção.
- `develop` → integração.
- `feature/<nome-curto>` → uma feature por branch.
- `fix/<nome-curto>` → correções.
- Pull Request obrigatório para `develop` e `main` — nenhum push direto, especialmente considerando as [regras de aprovação de alterações significativas](../ai/regras-claude-code.md#51-regra-de-aprovação-e-controle-de-alterações).

## Ver também

- [Arquitetura](arquitetura.md) — o padrão MVC que essas convenções seguem
- [Parte 20 — UI/UX & Frontend Standards](ui-ux-frontend.md) — UX, componentes e acessibilidade das Views
- [Setup e operações — CI (proposta)](setup-e-operacoes.md#781-cideploy-proposta)
- [CHANGELOG](../../CHANGELOG.md)

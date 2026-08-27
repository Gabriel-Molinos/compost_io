# Changelog

> Estrutura sugerida — movida da antiga Parte 20 / seção 99 do README (`[PROPOSTA]`). Manter este arquivo atualizado ao final de cada fase do [Roadmap](docs/product/roadmap.md#71-roadmap-completo-fases-19).

## [Fase 1] - Fundação
### Adicionado
- Estrutura inicial do projeto (frontend, backend, banco)
- Conexão frontend/backend
- Documentação técnica inicial
- 2026-08-27 — `database/migrations/0001_initial_schema.sql` aplicada ao banco `redacao` (19 tabelas; ver [schema.md](docs/technical/schema.md))
- 2026-08-27 — docs: [Parte 20 — UI/UX & Frontend Standards](docs/technical/ui-ux-frontend.md) e [SEO On-Page & Checklist Editorial](docs/editorial/seo.md)
- 2026-08-27 — esqueleto da aplicação: `composer.json` (autoload PSR-4), front controller (`public/index.php`) + roteador manual, `Env` + `Connection` (PDO/SSL), View em PHP puro, layout base acessível + Tailwind CLI standalone, runner de migrations (`database/migrate.php`), `.env.example`. Rota `/` mostrando status do banco.

## [Fase 2] - Usuários e sites
### Adicionado
- Login e autenticação
- CRUD de usuários e sites
- Sistema de permissões

> Pode ser gerado manualmente ou, se o [padrão de commits](docs/technical/padroes-de-codigo.md#90-padrão-de-commits-proposta) for adotado, automatizado com ferramentas como `standard-version` ou `release-please`.

## Ver também

- [Roadmap](docs/product/roadmap.md) — fases que geram entradas aqui
- [Registro de alterações (regra 62.1)](docs/ai/regras-claude-code.md#621-registro-de-alterações-novo--adicionado-nesta-reorganização-não-fazia-parte-da-numeração-original-do-readme)

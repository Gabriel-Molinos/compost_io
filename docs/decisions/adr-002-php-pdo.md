# ADR-002 — PHP puro com PDO (sem framework) para o backend

**Contexto:** o backend precisa se conectar ao MySQL, renderizar as telas da aplicação (ver [ADR-008](adr-008-frontend-php-puro.md)) e orquestrar as integrações externas (Gemini, WordPress, Nano Banana). Frameworks como Laravel trazem ORM, roteamento, autenticação e outras camadas prontas, mas também trazem convenções, mágica (auto-resolução de dependências, service container) e uma curva de aprendizado própria.

**Decisão:** usar **PHP puro com PDO**, sem framework. Rotas, controllers e services organizados manualmente (ver [seção 10](../technical/arquitetura.md#10-padrão-mvc-adaptado)), autoload via Composer/PSR-4.

**Motivos:**
- Controle total sobre cada query SQL — importante numa aplicação com muitas regras de negócio específicas (metas, permissões por site, máquina de estados do artigo) que não se encaixam bem num ORM genérico.
- Menos "mágica" para o Claude Code auxiliar no desenvolvimento — cada camada (Router → Controller → Service → PDO) é explícita, o que é coerente com a [Regra de não-invenção](../ai/regras-claude-code.md#58-regra-de-não-invenção) e a [Regra de transparência](../ai/regras-claude-code.md#59-regra-de-transparência): menos abstração escondida, menos chance de o Claude Code "adivinhar" comportamento de framework.
- Menor superfície de dependências para manter atualizadas/seguras ao longo do tempo, num projeto que já tem 60+ integrações WordPress para cuidar.

**Trade-offs aceitos:**
- Mais código boilerplate (roteamento, validação, tratamento de erro) precisa ser escrito à mão — mitigado pelas convenções da [Parte 15 — Convenções de Código](../technical/padroes-de-codigo.md).
- Sem ecossistema de pacotes prontos (auth, filas, validação) que um framework traria — mitigado escolhendo bibliotecas pontuais via Composer quando necessário (ex.: cliente Redis, ver [ADR-006](adr-006-fila-redis.md)), sem trazer um framework completo.

**Documentação oficial:**
- PDO (PHP Data Objects): https://www.php.net/manual/pt_BR/book.pdo.php

## Ver também

- [ADR-008 — Frontend em PHP puro](adr-008-frontend-php-puro.md)
- [Arquitetura — padrão MVC](../technical/arquitetura.md#10-padrão-mvc-adaptado)
- [Convenções de código](../technical/padroes-de-codigo.md)

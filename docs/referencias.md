# Referências

### 81. Documentação oficial por tecnologia (aprendizado)

O desenvolvimento deverá utilizar prioritariamente documentação oficial das tecnologias.

> **Nota:** esta seção não menciona mais React, Next.js ou TypeScript — ver [ADR-008](decisions/adr-008-frontend-php-puro.md), que substitui o frontend por PHP puro renderizando as Views.

**Frontend — Tailwind CSS (CLI standalone)**
Aprender: utility classes; responsive design; configuração; uso do executável standalone (sem Node.js) para compilar o CSS a partir das classes usadas nas Views PHP.
→ https://tailwindcss.com/docs · Standalone CLI: https://tailwindcss.com/blog/standalone-cli

**Frontend — JavaScript vanilla**
Aprender: manipulação do DOM; eventos; `fetch`/AJAX para os endpoints internos (ver [seção 68](technical/requisitos.md#68-contrato-de-endpoints-internos-ajax-exemplo)). Usado apenas onde a View PHP precisar de interatividade no navegador.
→ MDN Web Docs: https://developer.mozilla.org/pt-BR/docs/Web/JavaScript

**Design & Acessibilidade — UI/UX**
Base normativa dos padrões de interface (ver [Parte 20 — UI/UX & Frontend Standards](technical/ui-ux-frontend.md)).
- **Apple HIG** — princípios de design, foundations, tipografia, motion e UX writing; adaptar os princípios para web (não copiar componentes nativos de Apple).
- **W3C WCAG 2.2** — aprender os critérios de nível **AA**: contraste (1.4.3 / 1.4.11), uso de cor (1.4.1), navegação e foco por teclado (2.1.1 / 2.4.7 / 2.4.11), alvos de toque (2.5.8), reflow responsivo (1.4.10).
- **Material Design 3** — padrões de componente, layout, estados e navegação, usado **apenas como guia** (a biblioteca Material Web Components não é usada — exige Node.js, ver [ADR-008](decisions/adr-008-frontend-php-puro.md)).
→ https://developer.apple.com/design/human-interface-guidelines/ · https://www.w3.org/TR/WCAG22/ · https://m3.material.io/

**SEO — Google Search Central**
Aprender: título e meta descrição, hierarquia de headings, links internos/externos, texto âncora, imagens (alt, formato, tamanho), dados estruturados (FAQ), conteúdo útil e original. Base do [Checklist SEO On-Page](editorial/seo.md).
→ https://developers.google.com/search/docs

**Backend — PHP**
Aprender: sintaxe; tipos; funções; classes; namespaces; autoload (PSR-4); exceções; superglobais (`$_SERVER`, `$_POST`); manipulação de JSON (`json_encode`/`json_decode`); Composer.
O manual oficial do PHP cobre sintaxe, tipos, classes, namespaces, exceções e recursos de desenvolvimento web.
→ Manual oficial do PHP: https://www.php.net/manual/pt_BR/

**Backend — PDO (PHP Data Objects)**
Aprender: conexão (`PDO`), prepared statements (`prepare`/`execute`), binding de parâmetros, tratamento de exceções (`PDOException`), transações (`beginTransaction`/`commit`/`rollBack`).
A documentação oficial do PDO cobre a camada de abstração de acesso a banco de dados usada para conectar o backend ao MySQL com segurança (proteção contra SQL injection via prepared statements).
→ https://www.php.net/manual/pt_BR/book.pdo.php

**Banco de dados — MySQL**
Aprender: SQL; databases; tables; relationships; primary keys; foreign keys; indexes; transactions; queries; security.
A documentação oficial possui capítulos específicos sobre criação de tabelas, consultas, chaves estrangeiras e administração do servidor.
→ MySQL 8.4 Reference Manual: https://dev.mysql.com/doc/refman/8.4/en/ · Tutorial oficial (mesmo domínio)

**Beekeeper Studio**
Aprender: conexão; SQL; visualização de tabelas; edição de dados; criação/modificação de tabelas; consultas; importação/exportação.
O Beekeeper Studio é um gerenciador visual de bancos/SQL e possui suporte completo para MySQL.
→ Docs: https://docs.beekeeperstudio.io/ · Guia para iniciantes (mesmo domínio)

> **Nota:** a versão anterior deste documento tratava PHP como "conhecimento complementar" apenas para entender o WordPress (temas/plugins). Com a decisão de usar PHP puro no backend (ver [seção 6](technical/arquitetura.md#6-stack-do-projeto--backend)), o PHP passa a ser **linguagem principal do backend** — o conhecimento de PHP para entender temas/plugins do WordPress continua válido e relevante, mas deixa de ser o único motivo de aprendê-lo.

**WordPress**
O principal conhecimento necessário será: WordPress REST API; posts; categorias; mídia; usuários; autenticação; respostas JSON; endpoints; HTTP methods.
A documentação oficial explica que a REST API permite que aplicações externas consultem e alterem conteúdo WordPress usando JSON e endpoints REST.
→ WordPress REST API Handbook / Reference: https://developer.wordpress.org/rest-api/

### 82. Referências oficiais (links)

As referências principais para o desenvolvimento são:

| Tecnologia | Link |
|---|---|
| W3C WCAG 2.2 | https://www.w3.org/TR/WCAG22/ |
| Apple Human Interface Guidelines | https://developer.apple.com/design/human-interface-guidelines/ |
| HIG — Design Principles | https://developer.apple.com/design/human-interface-guidelines/design-principles |
| HIG — Foundations | https://developer.apple.com/design/human-interface-guidelines/foundations |
| Material Design 3 | https://m3.material.io/ |
| Google Search Central — SEO docs | https://developers.google.com/search/docs |
| Tailwind CSS | https://tailwindcss.com/docs |
| Tailwind CSS — Standalone CLI | https://tailwindcss.com/blog/standalone-cli |
| JavaScript (MDN) | https://developer.mozilla.org/pt-BR/docs/Web/JavaScript |
| PHP (backend + Views) | https://www.php.net/manual/pt_BR/ |
| PDO | https://www.php.net/manual/pt_BR/book.pdo.php |
| MySQL 8.4 Reference Manual | https://dev.mysql.com/doc/refman/8.4/en/ |
| Beekeeper Studio | https://docs.beekeeperstudio.io/ |
| WordPress REST API Handbook | https://developer.wordpress.org/rest-api/ |
| Gemini API — documentação | https://ai.google.dev/gemini-api/docs |
| Gemini API — quickstart | https://ai.google.dev/gemini-api/docs/quickstart |
| Gemini API — geração de texto | https://ai.google.dev/gemini-api/docs/text-generation |
| Gemini API — geração de imagens | https://ai.google.dev/gemini-api/docs/image-generation |
| Gemini API — modelos | https://ai.google.dev/gemini-api/docs/models |
| Gemini API — autenticação | https://ai.google.dev/gemini-api/docs/api-key |
| Gemini API — Structured Outputs | https://ai.google.dev/gemini-api/docs/structured-output |
| NotebookLM — Central de ajuda | https://support.google.com/gemininotebook/ |
| NotebookLM — fontes | https://support.google.com/notebooklm/answer/16215270 |
| Gemini Notebook Enterprise — APIs de notebooks | https://docs.cloud.google.com/gemini/enterprise/notebooklm-enterprise/docs/api-notebooks |
| Gemini Notebook Enterprise — APIs de fontes | https://docs.cloud.google.com/gemini/enterprise/notebooklm-enterprise/docs/api-notebooks-sources |

## Ver também

- [Integrações](technical/integracoes.md) — como cada tecnologia listada aqui é usada no projeto
- [Arquitetura](technical/arquitetura.md)
- [Parte 20 — UI/UX & Frontend Standards](technical/ui-ux-frontend.md) — onde as referências de design/acessibilidade viram regra
- [SEO On-Page & Checklist Editorial](editorial/seo.md) — onde a referência de SEO vira checklist

# ADR-008 — Frontend em PHP puro, sem Node.js (revoga ADR-001)

**Contexto:** a definição original do frontend (Next.js + React + TypeScript, ver antiga [seção 5](../technical/arquitetura.md#5-stack-do-projeto--frontend)) exigia Node.js tanto para build quanto para desenvolvimento local. O responsável pelo projeto decidiu remover completamente a dependência de Node.js do projeto, o que torna Next.js/React inviáveis, já que dependem do ecossistema Node.js em toda a cadeia de build.

**Decisão:** o próprio backend **PHP puro** (já definido na [ADR-002](adr-002-php-pdo.md)) passa a ser responsável também pela renderização das Views (HTML), usando arquivos `.php` como template — sem framework de frontend e sem biblioteca de templates. O **Tailwind CSS** é mantido, mas compilado via **Tailwind CLI standalone** (binário oficial, sem Node.js/npm). Interatividade no navegador usa **JavaScript vanilla**. A estrutura de diretórios deixa de separar `frontend/` e `backend/` e passa a ser uma aplicação única (ver [seção 11](../technical/arquitetura.md#11-arquitetura-de-diretórios)).

**Motivos:**
- Elimina Node.js como dependência do ambiente de desenvolvimento e produção — uma única linguagem de runtime (PHP) para toda a aplicação.
- Reduz a superfície de dependências a manter atualizadas (sem `node_modules`, sem cadeia de pacotes npm), coerente com a filosofia "sem dependência pesada" já adotada nas ADRs 002 e 006.
- Simplifica o deploy: um único processo/servidor PHP, sem precisar orquestrar build de frontend e API separadamente.

**Trade-offs aceitos:**
- Perda do ecossistema React/Next.js: sem componentização declarativa, sem Server/Client Components, sem hot module replacement de frontend moderno — interatividade fica limitada ao que JavaScript vanilla oferece de forma direta.
- Sem renderização client-side reativa "de fábrica" — atualizações dinâmicas de tela exigem AJAX manual contra endpoints internos (ver [seção 68](../technical/requisitos.md#68-contrato-de-endpoints-internos-ajax-exemplo)) em vez de um framework de estado reativo.
- Views em PHP puro não têm sandboxing automático contra XSS como um template engine (ex. Twig) ofereceria — reforça a necessidade de escapar saída (`htmlspecialchars`) manualmente em todo dado exibido.

## Ver também

- [ADR-002 — PHP puro com PDO](adr-002-php-pdo.md)
- [Arquitetura — stack frontend](../technical/arquitetura.md#5-stack-do-projeto--frontend)
- [Identidade visual](../product/identidade-visual.md) — como isso se traduz nas Views

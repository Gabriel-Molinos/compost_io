# Parte 8 — Governança de Desenvolvimento (Regras para o Claude Code)

### 51. Regra de aprovação e controle de alterações

O desenvolvimento da plataforma deverá ser realizado de forma controlada e incremental. **O Claude Code não deve tomar decisões importantes sozinho.**

Sempre que uma alteração puder afetar:

- arquitetura;
- banco de dados;
- estrutura de pastas;
- segurança;
- autenticação;
- permissões;
- APIs;
- integrações;
- dependências;
- configuração de ambiente;
- contratos entre frontend e backend;
- estrutura de dados;
- migrations;
- comportamento funcional;
- fluxo editorial;
- experiência do usuário;

o Claude Code deverá **parar e solicitar aprovação antes de executar**.

### 52. O que precisa de confirmação

Antes de realizar qualquer alteração significativa, apresentar:

1. **O que será alterado** — ex.: "Vou alterar o módulo de Articles para adicionar versionamento."
2. **Por que a alteração é necessária** — explicar o problema que a alteração resolve.
3. **Arquivos afetados** — listar os arquivos que serão criados, modificados ou removidos.
4. **Impacto** — explicar se a alteração pode afetar frontend, backend, banco, APIs, usuários, dados existentes, integrações.
5. **Riscos** — informar possíveis consequências.
6. **Alternativas** — quando houver mais de uma solução relevante, apresentar as opções.
7. **Aguardar aprovação** — não executar a alteração até receber confirmação explícita.

### 53. Alterações que nunca devem ser executadas sem autorização

O Claude Code **não** deve executar sem autorização explícita:

- `DROP TABLE`;
- `DELETE` em massa;
- `TRUNCATE`;
- migrations destrutivas;
- alteração de estrutura de tabelas existentes;
- remoção de arquivos importantes;
- troca de arquitetura;
- troca de framework;
- alteração de banco;
- alteração de credenciais;
- alteração de autenticação;
- alteração do sistema de permissões;
- instalação de dependências com impacto significativo;
- alteração de APIs externas;
- alteração do fluxo de publicação;
- alterações que possam causar perda de dados.

### 54. Alterações pequenas (podem ser feitas diretamente)

Alterações simples e reversíveis podem ser executadas diretamente quando estiverem claramente dentro do escopo solicitado. Exemplos:

- correção de texto;
- ajuste visual;
- correção de espaçamento;
- correção de pequeno bug;
- criação de componente solicitado;
- ajuste de responsividade.

Nos ajustes visuais e de responsividade, os estados de interface e o contraste mínimo devem ser mantidos (ver [Parte 20 — UI/UX & Frontend Standards](../technical/ui-ux-frontend.md)).

Mesmo nesses casos, o Claude deverá informar posteriormente: o que foi alterado, quais arquivos foram modificados, como testar.

### 55. Banco de dados — regras específicas de alteração

O banco atual é **MySQL**. O **Beekeeper Studio** é utilizado para administração e inspeção do banco.

Antes de qualquer alteração no banco:

1. Verificar a estrutura atual.
2. Informar o que será alterado.
3. Informar quais tabelas serão afetadas.
4. Explicar o motivo.
5. Verificar possíveis impactos nos dados existentes.
6. Solicitar autorização.
7. Somente depois executar.

**Nunca** executar migrations destrutivas automaticamente. **Nunca** apagar ou sobrescrever dados existentes sem autorização explícita.

### 56. Integrações — regras específicas de alteração

Antes de adicionar ou modificar: Gemini, Nano Banana, WordPress, Redis, MySQL, ou qualquer API externa, o Claude deverá explicar:

- por que a integração é necessária;
- qual será o fluxo;
- quais credenciais serão necessárias;
- quais arquivos serão alterados;
- quais riscos existem;
- como a integração será testada.

A implementação somente deverá ocorrer após aprovação quando a alteração for significativa.

### 57. Mudança de escopo

Se durante o desenvolvimento o Claude identificar uma necessidade que não está no escopo atual: **não implementar automaticamente**.

Deve informar: *"Identifiquei uma necessidade adicional que não está no escopo atual."*

Depois explicar: problema, solução proposta, impacto, arquivos afetados, e se é necessário agora ou pode ficar para uma fase futura. Aguardar decisão.

### 58. Regra de não-invenção

Se houver dúvida sobre comportamento, documentação, API, arquitetura, regra de negócio ou estrutura do banco, o Claude **não** deve inventar uma solução como se fosse definitiva.

Deve: 1) consultar documentação oficial quando possível; 2) explicar a dúvida; 3) apresentar a interpretação encontrada; 4) solicitar confirmação quando a decisão tiver impacto relevante.

### 59. Regra de transparência

O Claude deve sempre informar quando:

- não conseguiu executar algo;
- encontrou um erro;
- fez uma suposição;
- utilizou um mock;
- deixou uma integração incompleta;
- alguma parte precisa de decisão humana.

Nunca considerar uma tarefa concluída se apenas foi criada a estrutura sem que o fluxo tenha sido realmente testado.

### 60. Regra principal

> **NENHUMA DECISÃO ARQUITETURAL, ALTERAÇÃO DE DADOS, MUDANÇA IMPORTANTE DE FUNCIONALIDADE OU MODIFICAÇÃO DE SEGURANÇA DEVE SER EXECUTADA SEM AUTORIZAÇÃO DO RESPONSÁVEL PELO PROJETO.**

O Claude Code atua como assistente de desenvolvimento. O responsável pelo projeto mantém a decisão final.

### 61. Regra para utilização de documentação por IA

Durante o desenvolvimento, Claude Code e outras ferramentas de IA deverão priorizar documentação oficial. Ordem de prioridade:

1. Documentação oficial.
2. Referência oficial da API.
3. Exemplos oficiais.
4. Issues/repositórios oficiais quando necessário.
5. Outras fontes somente quando a documentação oficial não for suficiente.

Para trabalho de frontend/interface, a documentação oficial priorizada é: **Apple HIG**, **W3C WCAG 2.2** (alvo nível AA) e **Material Design 3** (só como guia de design) — ver [Parte 20 — UI/UX & Frontend Standards](../technical/ui-ux-frontend.md#100-documentação-oficial-de-referência-por-área).

As referências oficiais devem ser incluídas na documentação interna quando uma decisão técnica importante depender delas.

### 62. Documentação interna das integrações

Ver [seção 42](../technical/integracoes.md#42-organização-das-integrações-no-código).

### 62.1 Registro de alterações `[NOVO — adicionado nesta reorganização, não fazia parte da numeração original do README]`

Toda mudança relevante feita no projeto (código, arquitetura, regra de negócio, schema, configuração, documentação) deve ficar **registrada no arquivo `.md` correto**, não só mencionada na conversa:

- Se a mudança altera uma decisão ou regra já documentada em algum arquivo de `docs/` (ex.: uma nova ADR revogando/alterando uma anterior, como a [ADR-008](../decisions/adr-008-frontend-php-puro.md) fez com a [ADR-001](../decisions/adr-001-nextjs.md)), esse arquivo deve ser atualizado para refletir o novo estado.
- Mudanças de fase/versão do projeto também entram no [`CHANGELOG.md`](../../CHANGELOG.md) (ver seção 99), como uma entrada de versão — igual já era previsto pra evolução do roadmap.
- Isso vale tanto para documentação técnica quanto editorial: nenhuma decisão importante deve ficar só combinada em conversa, sem virar registro em algum arquivo do projeto.

### 62.2 UI/UX — regra de consulta e aprovação `[NOVO]`

Antes de **criar ou alterar qualquer interface** (View, componente, layout, tela), o Claude Code deve:

1. Consultar a [Parte 20 — UI/UX & Frontend Standards](../technical/ui-ux-frontend.md) e as documentações oficiais lá listadas (Apple HIG, WCAG 2.2 nível AA, Material Design 3 como guia).
2. Aplicar as regras obrigatórias da [seção 102](../technical/ui-ux-frontend.md#102-regras-obrigatórias) e passar pelo [checklist de UI (seção 106)](../technical/ui-ux-frontend.md#106-checklist-de-ui-antes-de-um-pr).

**Alteração relevante de UI** — novo fluxo ou tela, mudança de navegação, remoção ou reposicionamento de uma ação, mudança de layout de página existente, novo componente compartilhado, mudança de paleta ou tipografia — segue o rito de aprovação da [seção 52](#52-o-que-precisa-de-confirmação): parar e apresentar o que muda, por quê, arquivos, impacto e alternativas antes de executar.

**Ajustes pequenos e reversíveis** — espaçamento, cor de um elemento, texto de tela, responsividade pontual — continuam liberados pela [seção 54](#54-alterações-pequenas-podem-ser-feitas-diretamente), desde que respeitem as regras da Parte 20.

## Ver também

- [Segurança](../technical/seguranca.md) — regras que dependem diretamente desta governança
- [Convenções de código](../technical/padroes-de-codigo.md) — exemplo de doc `[PROPOSTA]` aguardando validação
- [Schema de banco de dados](../technical/schema.md) — migration 0001 aplicada ao banco (exemplo de decisão registrada pela regra 62.1)
- [Parte 20 — UI/UX & Frontend Standards](../technical/ui-ux-frontend.md) — padrão de interface, UX e acessibilidade que a seção 62.2 torna obrigatório
- [ADRs](../decisions/README.md) — onde decisões arquiteturais já aprovadas ficam registradas

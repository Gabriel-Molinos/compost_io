# Parte 10 — Roadmap e Escopo

### 70. Fase 1 — Fundação (entra / não entra)

**Entra:**
- Frontend
- Backend
- API
- MySQL
- Login
- Usuários
- Permissões
- Sites
- Metas
- Interface editorial
- Estrutura de IA
- Estrutura WordPress

**Não entra:**
- aprendizado avançado
- analytics externo
- automações avançadas
- produção totalmente autônoma

### 71. Roadmap completo (Fases 1–9)

**Fase 1 — Fundação**
PHP (PDO); Tailwind CSS (CLI standalone); estrutura de Views; estrutura MySQL; arquitetura de diretórios; documentação.

**Fase 2 — Usuários e sites**
Login; Administrador; Redator-Chefe; permissões; cadastro de sites; configuração editorial.

**Fase 3 — Planejamento**
Metas; categorias; diretrizes; interesses; não-interesses.

**Fase 4 — IA**
Prompt Base; Gemini; pesquisa; planejamento; produção; SEO; compliance.

**Fase 5 — Imagens**
Nano Banana; múltiplas opções; identidade visual; escolha do redator.

**Fase 6 — Revisão**
Aprovação; rejeição; feedback; regeneração; memória editorial.

**Fase 7 — Publicação**
Calendário; autores; WordPress; agendamento.

**Fase 8 — Inteligência Editorial**
Relatório mensal; evolução; problemas; aprendizados; recomendações.

**Fase 9 — Escala**
60+ sites; otimização; filas; controle de custos; observabilidade; melhorias de performance.

### 72. Critério de sucesso da primeira versão

A primeira versão será considerada funcional quando for possível:

```
Entrar na plataforma
      ↓
Visualizar um site
      ↓
Visualizar dashboard
      ↓
Consultar produção
      ↓
Consultar planejamento
      ↓
Visualizar calendário
      ↓
Visualizar relatórios
      ↓
Editar configurações
```

Tudo deverá passar pela API do backend. Nenhuma informação principal da dashboard deverá depender de dados hardcoded diretamente nos componentes.

### 73. Regra de desenvolvimento incremental

O projeto deverá ser desenvolvido de forma incremental. **Não construir tudo de uma vez.**

Cada fase deve: ser implementada → ser testada → ser revisada → ser documentada → somente depois dar lugar à próxima fase.

O Claude Code deverá:

- explicar antes de implementar;
- não apagar código existente sem necessidade;
- não alterar banco de dados sem autorização;
- não instalar dependências desnecessárias;
- consultar documentação oficial quando houver dúvida;
- manter o projeto organizado;
- explicar os arquivos criados e modificados.

### 74. Objetivo final / fluxo resumido

A Editorial Dashboard deverá funcionar como uma redação digital centralizada.

- **O Administrador controla:** Sites, Usuários, Permissões, Estrutura.
- **O Redator-Chefe controla:** Estratégia, Metas, Diretrizes, Revisão, Aprovação, Agendamento.
- **A IA executa:** Pesquisa, Planejamento, Produção, SEO, Imagem, Validação.
- **O WordPress executa:** Publicação.

Resultado:

```
60+ sites
      ↓
Uma plataforma
      ↓
Uma operação editorial organizada
      ↓
IA produz
      ↓
Humano aprova
      ↓
WordPress publica
```

### 75. Organograma de responsabilidades

```
ADMIN
│
├── Sites
├── Usuários
├── Permissões
└── Visão geral
        │
        ▼
REDATOR-CHEFE
│
├── Planejamento
├── Metas
├── Produção
├── Revisão
├── Calendário
└── Relatórios
        │
        ▼
IA
│
├── Planejador
├── Pesquisa
├── Escrita
├── SEO
├── Compliance
└── Imagens
        │
        ▼
WORDPRESS
```

## Ver também

- [Visão geral do produto](visao-geral.md) — princípios que guiam o roadmap
- [CHANGELOG](../../CHANGELOG.md) — registro do que foi entregue por fase
- [Setup e operações](../technical/setup-e-operacoes.md) — Fase 1 na prática (ambiente de dev)
- [Regra de desenvolvimento incremental (seção 73)](#73-regra-de-desenvolvimento-incremental)

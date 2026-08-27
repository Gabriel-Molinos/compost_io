# Onboarding de um Novo Usuário `[PROPOSTA — não existia no README original; espelha o mesmo estilo do onboarding de site]`

Complementa o [Onboarding de um Novo Site](onboarding-site.md) — este documento cobre a entrada de uma **pessoa** (Administrador ou Redator-Chefe) na plataforma, não de um site.

### Fluxo de cadastro de usuário

```
Administrador acessa Usuários
      ↓
Cadastra nome, e-mail e papel (ADMIN | REDATOR_CHEFE)
      ↓
Define senha inicial ou envia convite
      ↓
Vincula o usuário a um ou mais sites (ver seção 14)
      ↓
Usuário recebe acesso e faz o primeiro login (ver seção 64.2 — Autenticação)
      ↓
Usuário só enxerga os sites aos quais foi vinculado
```

> Este fluxo segue a [Tabela de permissões (seção 64.1)](../technical/requisitos.md#641-tabela-de-permissões-por-recurso-proposta) e o isolamento por site descrito na [seção 14 — Usuários e permissões](../editorial/fluxo-editorial.md#14-usuários-e-permissões). Assim como o onboarding de site, o vínculo usuário↔site é independente da função — um Redator-Chefe pode estar vinculado a vários sites, e um site pode ter mais de um Redator-Chefe.

### Desligamento/revogação `[PROPOSTA — não detalhado no documento original]`

O README original nunca definiu o fluxo inverso (remover acesso de um usuário). Fica registrado como lacuna a decidir: revogação imediata de sessão ativa, o que acontece com artigos em produção atribuídos a esse usuário, e se o histórico de ações do usuário permanece visível após a remoção.

## Ver também

- [Onboarding de site](onboarding-site.md)
- [Tabela de permissões](../technical/requisitos.md#641-tabela-de-permissões-por-recurso-proposta)
- [Autenticação (proposta)](../technical/requisitos.md#642-autenticação-proposta)

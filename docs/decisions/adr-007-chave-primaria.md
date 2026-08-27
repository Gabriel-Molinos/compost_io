# ADR-007 — Chave primária das tabelas

**Contexto:** era preciso decidir entre `INT`/`BIGINT AUTO_INCREMENT` (sequencial) ou `UUID` (identificador aleatório) como chave primária das tabelas do rascunho de schema (seção 87).

**Decisão:** usar **`BIGINT UNSIGNED AUTO_INCREMENT`**.

**Motivos:**
- A plataforma é uma ferramenta interna (Administrador e Redator-Chefe), os IDs nunca são expostos publicamente como em um e-commerce ou API pública — o principal motivo de se usar UUID (evitar que alguém de fora adivinhe ou enumere registros) não se aplica aqui.
- Chaves sequenciais são mais simples de trabalhar manualmente com PDO puro (sem ORM gerando UUIDs automaticamente), mais legíveis em debug/log, e geram índices menores e joins mais rápidos no MySQL — coerente com o princípio de [Simplicidade (seção 3)](../product/visao-geral.md#3-princípios-do-produto) definido para o produto.
- `BIGINT` (em vez de `INT`) evita qualquer preocupação de estouro de limite mesmo com 60+ sites gerando artigos continuamente por anos.

**Trade-offs aceitos:**
- Caso a plataforma precise futuramente sincronizar dados entre múltiplos bancos/ambientes (ex.: merge de dados de instâncias diferentes), UUID teria sido mais adequado — não é um cenário previsto hoje, mas fica registrado como possível revisão futura do ADR.

## Ver também

- [Schema de banco de dados](../technical/schema.md)
- [Visão geral — princípio de Simplicidade](../product/visao-geral.md#3-princípios-do-produto)

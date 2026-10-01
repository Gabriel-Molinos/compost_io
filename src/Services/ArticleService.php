<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\HtmlSanitizer;

/**
 * Artigos e seus dados diretos (versões de corpo, fontes). A máquina de estados
 * (requisitos §65) é dirigida pelo `ArticlePipeline` e, mais tarde, pela revisão
 * humana (Fase 6). Soft delete via `deleted_at`.
 */
final class ArticleService
{
    /** Limite de gerações (manuais + automáticas) por site a cada 24h — guarda de custo (requisitos §95). */
    public const DAILY_LIMIT = 15;

    /** Itens por página na listagem da Produção (paginação — requisitos/testes-e-observabilidade §94.1). */
    public const PER_PAGE = 20;

    /**
     * Tamanho do pedido livre do redator ("Rascunho específico"). O mínimo evita
     * um "faz um post de skincare" que não diz nada — o ponto do botão é o redator
     * dizer EXATAMENTE o que quer; o máximo mantém o prompt (e o custo) sob controle.
     */
    /** Valor especial do filtro por meta: "sem meta" (0 nunca é um id real). */
    public const GOAL_NONE = 0;

    public const WRITER_REQUEST_MIN = 40;
    public const WRITER_REQUEST_MAX = 3000;

    /**
     * Grupos de status usados no filtro da aba Produção — mesmo agrupamento
     * de `production/index.php` (antes calculado em PHP puro sobre a lista
     * inteira; agora também vira `WHERE status IN (...)` no SQL, então
     * precisa estar centralizado aqui pra não desalinhar dos dois lados).
     *
     * @var array<string, list<string>>
     */
    private const STATUS_GROUPS = [
        'done'      => ['APPROVED', 'SCHEDULED', 'PUBLISHED'],
        'attention' => ['BLOCKED', 'ERROR'],
        'discarded' => ['DISCARDED'],
        'progress'  => ['PLANNED', 'IN_PROGRESS', 'IN_REVIEW', 'REVISION_REQUESTED'],
    ];

    /** @return array<string, list<string>> mesmo mapa de STATUS_GROUPS, exposto pra view montar os sub-filtros de status exato. */
    public static function statusGroups(): array
    {
        return self::STATUS_GROUPS;
    }

    /** @return list<string> todo status real de artigo, em qualquer grupo — pra validar o filtro `exact_status`. */
    public static function allStatuses(): array
    {
        return array_merge(...array_values(self::STATUS_GROUPS));
    }

    /** Status em que a IA ainda está trabalhando no rascunho (pipeline rodando ou na fila). */
    public const GENERATING_STATUSES = ['PLANNED', 'IN_PROGRESS'];

    /**
     * Depois disso gerando, é quase certo que o worker parou — o rascunho
     * deixa de ficar trancado (dá pra abrir e ver os passos da IA) e a lista
     * mostra o aviso de "demorando mais que o esperado".
     */
    public const GENERATION_STALE_MINUTES = 15;

    /** @param array<string,mixed> $article */
    public static function isGenerating(array $article): bool
    {
        return in_array($article['status'] ?? null, self::GENERATING_STATUSES, true);
    }

    /** @param array<string,mixed> $article */
    public static function isGenerationStale(array $article): bool
    {
        return self::isGenerating($article)
            && strtotime((string) $article['created_at']) <= strtotime('-' . self::GENERATION_STALE_MINUTES . ' minutes');
    }

    /**
     * Rascunho ainda sendo gerado — não abre até ficar 100% pronto (pedido do
     * responsável, 2026-09-29: antes a tela do post recarregava sozinha a cada
     * 5s mostrando ele pela metade). Travado há muito tempo não tranca, senão
     * um worker parado deixaria o rascunho inacessível pra sempre.
     *
     * @param array<string,mixed> $article
     */
    public static function isLockedForGeneration(array $article): bool
    {
        return self::isGenerating($article) && !self::isGenerationStale($article);
    }

    /**
     * Situação de geração de vários artigos do site, pro polling da lista
     * (`assets/js/production-status.js`). Id que não existe/não é do site
     * simplesmente não volta.
     *
     * @param list<int> $ids
     * @return array<int, array{status: string, created_at: string}>
     */
    public function generationStates(int $siteId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Connection::get()->prepare(
            "SELECT id, status, created_at FROM articles
             WHERE site_id = ? AND deleted_at IS NULL AND id IN ({$placeholders})"
        );
        $stmt->execute([$siteId, ...$ids]);

        $states = [];
        foreach ($stmt->fetchAll() as $row) {
            $states[(int) $row['id']] = ['status' => (string) $row['status'], 'created_at' => (string) $row['created_at']];
        }

        return $states;
    }

    /**
     * Página da listagem de artigos (Produção). Paginado desde 2026-09-15 —
     * antes buscava tudo sem `LIMIT`, o que crescia sem parar com o
     * histórico do site (achado real, registrado como pendência em
     * `docs/technical/testes-e-observabilidade.md`).
     *
     * Filtro melhor (pedido do responsável, 2026-09-17: "tipo um filtro
     * melhor pra posts em produção, em rascunho etc") — as abas
     * `$statusGroup` continuam sendo o filtro rápido de alto nível, mas
     * agora dá pra refinar por status exato (ex.: dentro de "Aprovados",
     * separar Aprovado/Agendado/Publicado), categoria, origem
     * (manual/automático) e busca por título/palavra-chave — todos
     * combináveis (AND), sempre navegação de verdade por querystring
     * (mesma filosofia de `?status=...`, nunca JS escondendo linha).
     *
     * @return list<array<string, mixed>>
     */
    public function allForSite(
        int $siteId,
        string $statusGroup = 'all',
        int $page = 1,
        int $perPage = self::PER_PAGE,
        ?string $exactStatus = null,
        ?int $categoryId = null,
        ?string $origin = null,
        ?string $search = null,
        ?int $goalId = null,
    ): array {
        [$where, $params] = $this->buildFilterWhere($siteId, $statusGroup, $exactStatus, $categoryId, $origin, $search, $goalId);

        $offset = max(0, ($page - 1)) * $perPage;
        $stmt = Connection::get()->prepare(
            "SELECT a.id, a.title, a.status, a.focus_keyword, a.category_id, a.created_at,
                    a.attempt_number, a.lineage_id, a.source,
                    (a.writer_request IS NOT NULL) AS has_writer_request,
                    c.name AS category_name,
                    g.period AS goal_period,
                    COALESCE((SELECT SUM(cost) FROM ai_executions e WHERE e.article_id = a.id), 0) AS ai_cost,
                    (SELECT MAX(word_count) FROM article_versions v WHERE v.article_id = a.id) AS word_count
             FROM articles a
             LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN goals g ON g.id = a.goal_id
             WHERE {$where}
             ORDER BY a.created_at DESC
             LIMIT :lim OFFSET :off"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Total de artigos pra uma combinação de filtros — usado pra paginar
     * corretamente quando `$exactStatus`/categoria/origem/busca reduzem o
     * total além do que `countsByStatusGroup()` (só por grupo) já cobre.
     */
    public function countFiltered(
        int $siteId,
        string $statusGroup = 'all',
        ?string $exactStatus = null,
        ?int $categoryId = null,
        ?string $origin = null,
        ?string $search = null,
        ?int $goalId = null,
    ): int {
        [$where, $params] = $this->buildFilterWhere($siteId, $statusGroup, $exactStatus, $categoryId, $origin, $search, $goalId);
        $stmt = Connection::get()->prepare("SELECT COUNT(*) FROM articles a WHERE {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Contagem por grupo de status (+ 'all') pros badges das abas de filtro
     * — independente de paginação, sempre reflete o total real do site
     * (dentro dos filtros de categoria/origem/busca já ativos, se algum
     * estiver, pra badge bater com o que a lista abaixo realmente mostra).
     *
     * @return array{all: int, done: int, attention: int, progress: int, discarded: int}
     */
    public function countsByStatusGroup(int $siteId, ?int $categoryId = null, ?string $origin = null, ?string $search = null, ?int $goalId = null): array
    {
        [$where, $params] = $this->buildBaseWhere($siteId, $categoryId, $origin, $search, $goalId);

        $cases = [];
        foreach (self::STATUS_GROUPS as $group => $statuses) {
            $quoted = implode(', ', array_map(static fn (string $s): string => "'{$s}'", $statuses));
            $cases[] = "SUM(CASE WHEN a.status IN ({$quoted}) THEN 1 ELSE 0 END) AS {$group}";
        }
        $stmt = Connection::get()->prepare(
            'SELECT COUNT(*) AS all_count, ' . implode(', ', $cases) . "
             FROM articles a
             WHERE {$where}"
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'all'       => (int) ($row['all_count'] ?? 0),
            'done'      => (int) ($row['done'] ?? 0),
            'attention' => (int) ($row['attention'] ?? 0),
            'progress'  => (int) ($row['progress'] ?? 0),
            'discarded' => (int) ($row['discarded'] ?? 0),
        ];
    }

    /**
     * Contagem por status exato dentro de UM grupo — os "sub-chips" que
     * aparecem quando o grupo escolhido tem mais de um status real (ex.:
     * "Aprovados" = Aprovado + Agendado + Publicado). Grupo com um status
     * só (ex. "Descartados") não precisa de sub-filtro, devolve vazio.
     *
     * @return array<string, int> status real => contagem
     */
    public function countsByExactStatus(
        int $siteId,
        string $statusGroup,
        ?int $categoryId = null,
        ?string $origin = null,
        ?string $search = null,
        ?int $goalId = null,
    ): array {
        $statuses = self::STATUS_GROUPS[$statusGroup] ?? [];
        if (count($statuses) < 2) {
            return [];
        }

        [$where, $params] = $this->buildBaseWhere($siteId, $categoryId, $origin, $search, $goalId);
        $stmt = Connection::get()->prepare(
            "SELECT a.status, COUNT(*) AS n FROM articles a WHERE {$where} GROUP BY a.status"
        );
        $stmt->execute($params);

        $counts = array_fill_keys($statuses, 0);
        foreach ($stmt->fetchAll() as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int) $row['n'];
            }
        }

        return $counts;
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function buildBaseWhere(int $siteId, ?int $categoryId, ?string $origin, ?string $search, ?int $goalId = null): array
    {
        $where = ['a.site_id = :s', 'a.deleted_at IS NULL'];
        $params = ['s' => $siteId];

        if ($categoryId !== null) {
            $where[] = 'a.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }
        // Filtro por meta (2026-09-25): GOAL_NONE = artigos gerados sem meta; um id = os daquela meta.
        if ($goalId === self::GOAL_NONE) {
            $where[] = 'a.goal_id IS NULL';
        } elseif ($goalId !== null) {
            $where[] = 'a.goal_id = :goal_id';
            $params['goal_id'] = $goalId;
        }
        if ($origin !== null && in_array($origin, ['AUTO', 'MANUAL'], true)) {
            $where[] = 'a.source = :origin';
            $params['origin'] = $origin;
        }
        if ($search !== null && trim($search) !== '') {
            $where[] = '(a.title LIKE :search1 OR a.focus_keyword LIKE :search2)';
            $params['search1'] = $params['search2'] = '%' . trim($search) . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function buildFilterWhere(
        int $siteId,
        string $statusGroup,
        ?string $exactStatus,
        ?int $categoryId,
        ?string $origin,
        ?string $search,
        ?int $goalId = null,
    ): array {
        [$baseWhere, $params] = $this->buildBaseWhere($siteId, $categoryId, $origin, $search, $goalId);
        $where = [$baseWhere];

        if ($exactStatus !== null && in_array($exactStatus, self::allStatuses(), true)) {
            $where[] = 'a.status = :exact_status';
            $params['exact_status'] = $exactStatus;
        } elseif (isset(self::STATUS_GROUPS[$statusGroup])) {
            $statuses = self::STATUS_GROUPS[$statusGroup];
            $placeholders = [];
            foreach ($statuses as $i => $status) {
                $key = "st{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = $status;
            }
            $where[] = 'a.status IN (' . implode(', ', $placeholders) . ')';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Um artigo em revisão qualquer do site, pro tutorial guiado poder
     * linkar direto pra ele (`data-tour-review-href` em production/index.php)
     * — antes achava isso escaneando a lista inteira em PHP; com paginação,
     * o artigo em revisão pode estar em qualquer página, então vira uma
     * consulta própria, independente da página/filtro atual.
     *
     * @return array{id: int}|null
     */
    public function firstInReview(int $siteId): ?array
    {
        $stmt = Connection::get()->prepare(
            "SELECT id FROM articles
             WHERE site_id = :s AND deleted_at IS NULL AND status = 'IN_REVIEW'
             ORDER BY created_at DESC
             LIMIT 1"
        );
        $stmt->execute(['s' => $siteId]);
        $row = $stmt->fetch();

        return $row === false ? null : ['id' => (int) $row['id']];
    }

    /**
     * Artigos já publicados de verdade no site (com post real no WordPress) —
     * dá ao `PromptBuilder` alvos reais pra link interno no passo `writing`,
     * em vez da IA "chutar" um slug que talvez não exista (fluxo-editorial
     * §24, docs/ai/writing.md). Mais recentes primeiro.
     *
     * @return list<array{title: string, wordpress_post_id: int}>
     */
    public function recentPublishedForLinking(int $siteId, int $limit = 15): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT a.title, s.wordpress_post_id
             FROM articles a
             JOIN schedules s ON s.article_id = a.id AND s.status = 'PUBLISHED' AND s.wordpress_post_id IS NOT NULL
             WHERE a.site_id = :s AND a.status = 'PUBLISHED' AND a.deleted_at IS NULL
             ORDER BY a.updated_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue('s', $siteId, \PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $r): array => ['title' => (string) $r['title'], 'wordpress_post_id' => (int) $r['wordpress_post_id']],
            $stmt->fetchAll()
        );
    }

    /**
     * Mesmo pool de `recentPublishedForLinking()` (artigos publicados de
     * verdade, mais recentes primeiro), mas com id + corpo completo + post do
     * WordPress — usado tanto pela sugestão de link retroativo
     * (`BacklinkSuggestionService`, a IA precisa do texto de verdade do artigo
     * antigo pra achar uma frase real pra virar âncora) quanto pela sugestão
     * de link interno por relevância no editor manual
     * (`InternalLinkSuggestionService`, achado real 2026-09-10: a IA decide
     * quais desses artigos antigos combinam com o conteúdo do artigo atual).
     *
     * @return list<array{id:int, title:string, meta_description:string, focus_keyword:string, content:string, wordpress_post_id:int}>
     */
    public function publishedCandidatesForBacklinks(int $siteId, int $excludeArticleId, int $limit = 15): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT a.id, a.title, a.meta_description, a.focus_keyword, s.wordpress_post_id,
                    (SELECT v.content FROM article_versions v WHERE v.article_id = a.id ORDER BY v.id DESC LIMIT 1) AS content
             FROM articles a
             JOIN schedules s ON s.article_id = a.id AND s.status = 'PUBLISHED' AND s.wordpress_post_id IS NOT NULL
             WHERE a.site_id = :s AND a.status = 'PUBLISHED' AND a.deleted_at IS NULL AND a.id != :ex
             ORDER BY a.updated_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue('s', $siteId, \PDO::PARAM_INT);
        $stmt->bindValue('ex', $excludeArticleId, \PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $r): array => [
                'id' => (int) $r['id'],
                'title' => (string) $r['title'],
                'meta_description' => (string) ($r['meta_description'] ?? ''),
                'focus_keyword' => (string) ($r['focus_keyword'] ?? ''),
                'content' => (string) ($r['content'] ?? ''),
                'wordpress_post_id' => (int) $r['wordpress_post_id'],
            ],
            $stmt->fetchAll()
        );
    }

    /**
     * IDs dos artigos publicados de um site — usado pela revarredura periódica
     * de link rot (bin/worker.php): um link real na publicação pode morrer
     * meses depois, e nada revisitava isso (só geração/edição/publicação
     * checavam, achado real 2026-09-10).
     *
     * @return list<int>
     */
    public function publishedIds(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT id FROM articles WHERE site_id = :s AND status = 'PUBLISHED' AND deleted_at IS NULL ORDER BY id"
        );
        $stmt->execute(['s' => $siteId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    /** @return array<string, mixed>|null */
    public function find(int $siteId, int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM articles WHERE id = :id AND site_id = :s AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id, 's' => $siteId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Últimos artigos já aprovados do site (memória editorial — fatia 6.3):
     * a IA usa para não repetir tema/ângulo e evitar canibalização.
     *
     * @return list<array<string, mixed>>
     */
    public function recentApprovedForSite(int $siteId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = Connection::get()->prepare(
            "SELECT title, focus_keyword
             FROM articles
             WHERE site_id = :s AND deleted_at IS NULL
               AND status IN ('APPROVED','SCHEDULED','PUBLISHED')
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['s' => $siteId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null artigo por id, sem escopo de site (uso interno de serviços) */
    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM articles WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** Artigos criados no site nas últimas 24h (guarda de custo de IA — requisitos §95). */
    public function countCreatedLast24h(int $siteId): int
    {
        $stmt = Connection::get()->prepare(
            'SELECT COUNT(*) FROM articles WHERE site_id = :s AND created_at >= (NOW() - INTERVAL 1 DAY)'
        );
        $stmt->execute(['s' => $siteId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Roda `$create()` só se o limite diário do site ainda não foi atingido —
     * checagem + criação sob um `GET_LOCK` do MySQL escopado por site, pra
     * fechar a race condition de `countCreatedLast24h()`: sem o lock, duas
     * requisições concorrentes podiam ler o mesmo count (ex.: 14) antes de
     * qualquer uma criar a linha, e as duas passavam do limite de 15 (Fase 9,
     * item registrado em testes-e-observabilidade.md §97). O lock é por site
     * (`site_id` na chave) — sites diferentes não se bloqueiam entre si, e
     * dura só o instante do check+create (não a geração inteira via IA).
     *
     * @template T
     * @param callable(): T $create
     * @return T
     * @throws DailyLimitExceededException se o limite já foi atingido, ou se
     *         não foi possível obter o lock a tempo (outra requisição do
     *         mesmo site travada) — melhor errar visível do que travar a
     *         requisição do usuário.
     */
    public function createWithDailyLimit(int $siteId, int $limit, callable $create): mixed
    {
        $pdo = Connection::get();
        $lockKey = "articles:daily_limit:{$siteId}";

        $stmt = $pdo->prepare('SELECT GET_LOCK(:key, :timeout)');
        $stmt->execute(['key' => $lockKey, 'timeout' => 5]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new DailyLimitExceededException(
                'Não foi possível confirmar o limite diário agora (outra geração deste site em andamento) — tente de novo em instantes.'
            );
        }

        try {
            if ($this->countCreatedLast24h($siteId) >= $limit) {
                throw new DailyLimitExceededException(
                    "Limite de {$limit} gerações por dia neste site atingido. Tente amanhã."
                );
            }

            return $create();
        } finally {
            $pdo->prepare('SELECT RELEASE_LOCK(:key)')->execute(['key' => $lockKey]);
        }
    }

    /**
     * Quantos artigos do site precisam de atenção humana agora (fluxo-editorial
     * §33 / testes-e-observabilidade §94.1): `BLOCKED` (linhagem esgotou as
     * tentativas de regeneração) e `ERROR` (falha técnica definitiva do
     * pipeline, retries de IA esgotados). Alimenta o indicador da Visão Geral.
     *
     * @return array{blocked: int, error: int}
     */
    public function attentionCounts(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT status, COUNT(*) AS total FROM articles
             WHERE site_id = :s AND deleted_at IS NULL AND status IN ('BLOCKED', 'ERROR')
             GROUP BY status"
        );
        $stmt->execute(['s' => $siteId]);

        $counts = ['blocked' => 0, 'error' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status'] === 'BLOCKED' ? 'blocked' : 'error'] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Rascunhos "Planejado"/"Em produção" há mais de $minutes sem sair
     * desse estado — sintoma de job parado na fila (achado real, 2026-09-09:
     * job dispatchado no Redis, mas sem bin/worker.php rodando pra consumir,
     * ficava preso em PLANNED indefinidamente, sem nenhum aviso na tela).
     * Diferente de BLOCKED/ERROR (falha que a IA já reportou): aqui a IA
     * nem chegou a rodar — o problema é operacional (worker), não editorial.
     */
    public function staleGeneratingCount(int $siteId, int $minutes = 15): int
    {
        $stmt = Connection::get()->prepare(
            "SELECT COUNT(*) FROM articles
             WHERE site_id = :s AND deleted_at IS NULL AND status IN ('PLANNED', 'IN_PROGRESS')
               AND created_at <= DATE_SUB(NOW(), INTERVAL :m MINUTE)"
        );
        $stmt->execute(['s' => $siteId, 'm' => $minutes]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param 'MANUAL'|'AUTO' $source MANUAL = clique no botão "Gerar rascunho"; AUTO = geração diária automática (bin/worker.php).
     * @param ?string $writerRequest pedido livre do redator ("Rascunho específico") — NULL = a IA escolhe o tema.
     */
    public function create(int $siteId, ?int $goalId, string $source = 'MANUAL', ?string $writerRequest = null): int
    {
        $pdo = Connection::get();
        $pdo->prepare(
            "INSERT INTO articles (site_id, goal_id, status, attempt_number, source, writer_request)
             VALUES (:s, :g, 'PLANNED', 1, :src, :req)"
        )->execute([
            's'   => $siteId,
            'g'   => $goalId,
            'src' => $source === 'AUTO' ? 'AUTO' : 'MANUAL',
            'req' => self::nullable($writerRequest, self::WRITER_REQUEST_MAX),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** O site já teve uma geração automática hoje? (guarda de "1 por dia" — bin/worker.php). */
    public function hasAutoGeneratedToday(int $siteId): bool
    {
        $stmt = Connection::get()->prepare(
            "SELECT COUNT(*) FROM articles WHERE site_id = :s AND source = 'AUTO' AND DATE(created_at) = CURDATE()"
        );
        $stmt->execute(['s' => $siteId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Nova tentativa da mesma linhagem (regeneração — fluxo-editorial §29).
     * `$writerRequest` repassa o pedido do redator da tentativa anterior: a
     * regeneração continua atendendo ao mesmo pedido, não volta a escolher tema livre.
     */
    public function createAttempt(int $siteId, ?int $goalId, int $lineageId, int $attemptNumber, ?string $writerRequest = null): int
    {
        $pdo = Connection::get();
        $pdo->prepare(
            "INSERT INTO articles (site_id, goal_id, status, lineage_id, attempt_number, writer_request)
             VALUES (:s, :g, 'PLANNED', :l, :n, :req)"
        )->execute([
            's'   => $siteId,
            'g'   => $goalId,
            'l'   => $lineageId,
            'n'   => $attemptNumber,
            'req' => self::nullable($writerRequest, self::WRITER_REQUEST_MAX),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Fixa o lineage_id (só se ainda estiver nulo) — o 1º artigo aponta para si mesmo. */
    public function setLineage(int $id, int $lineageId): void
    {
        Connection::get()->prepare(
            'UPDATE articles SET lineage_id = :l WHERE id = :id AND lineage_id IS NULL'
        )->execute(['l' => $lineageId, 'id' => $id]);
    }

    public function applyPlan(int $id, string $title, string $focusKeyword, ?int $categoryId): void
    {
        Connection::get()->prepare(
            'UPDATE articles SET title = :t, focus_keyword = :k, category_id = :c WHERE id = :id'
        )->execute([
            't' => mb_substr($title, 0, 255),
            'k' => mb_substr($focusKeyword, 0, 191),
            'c' => $categoryId,
            'id' => $id,
        ]);
    }

    public function applyWriting(int $id, int $siteId, string $title, string $slug, string $focusKeyword, string $metaDescription): void
    {
        Connection::get()->prepare(
            "UPDATE articles
             SET title = :t, slug = :sl, focus_keyword = :k, meta_description = :m, status = 'IN_PROGRESS'
             WHERE id = :id"
        )->execute([
            't' => mb_substr($title, 0, 255),
            'sl' => $this->uniqueSlug($siteId, $slug, $id),
            'k' => mb_substr($focusKeyword, 0, 191),
            'm' => mb_substr($metaDescription, 0, 320),
            'id' => $id,
        ]);
    }

    public function setStatus(int $id, string $status): void
    {
        // Carimba a janela de revisão humana para o relatório mensal (Fase 8, §32).
        $extra = match ($status) {
            'IN_REVIEW'                        => ', review_started_at = COALESCE(review_started_at, NOW())',
            'APPROVED', 'REVISION_REQUESTED'   => ', reviewed_at = NOW()',
            default                            => '',
        };

        Connection::get()->prepare("UPDATE articles SET status = :st{$extra} WHERE id = :id")
            ->execute(['st' => $status, 'id' => $id]);
    }

    public function softDelete(int $id): void
    {
        Connection::get()->prepare("UPDATE articles SET deleted_at = NOW(), status = 'DISCARDED' WHERE id = :id")
            ->execute(['id' => $id]);
    }

    /** Canibalização: a palavra-chave já está em outro artigo vivo do site? (§21, seo.md) */
    public function keywordExists(int $siteId, string $focusKeyword, int $ignoreArticleId): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT COUNT(*) FROM articles
             WHERE site_id = :s AND focus_keyword = :k AND id <> :ig AND deleted_at IS NULL'
        );
        $stmt->execute(['s' => $siteId, 'k' => $focusKeyword, 'ig' => $ignoreArticleId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function addVersion(int $articleId, string $contentHtml, int $wordCount): int
    {
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO article_versions (article_id, content, word_count) VALUES (:a, :c, :w)'
        )->execute(['a' => $articleId, 'c' => HtmlSanitizer::clean($contentHtml), 'w' => $wordCount]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function latestVersion(int $articleId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM article_versions WHERE article_id = :a ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['a' => $articleId]);

        return $stmt->fetch() ?: null;
    }

    /** @param array<string, mixed> $source */
    public function addSource(int $articleId, array $source): void
    {
        Connection::get()->prepare(
            'INSERT INTO article_sources (article_id, url, title, publisher, accessed_at)
             VALUES (:a, :u, :t, :p, :d)'
        )->execute([
            'a' => $articleId,
            'u' => mb_substr((string) ($source['source_url'] ?? ''), 0, 1024),
            't' => self::nullable($source['source_title'] ?? null, 255),
            'p' => self::nullable($source['publisher'] ?? null, 191),
            'd' => self::validDate($source['accessed_at'] ?? null),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function sources(int $articleId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT url, title, publisher, accessed_at FROM article_sources WHERE article_id = :a ORDER BY id'
        );
        $stmt->execute(['a' => $articleId]);

        return $stmt->fetchAll();
    }

    /** Garante slug único por site (`uq_articles_site_slug`), somando -2, -3… se preciso. */
    private function uniqueSlug(int $siteId, string $slug, int $articleId): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($slug)) ?? '', '-');
        $base = $base !== '' ? mb_substr($base, 0, 180) : 'artigo-' . $articleId;

        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM articles WHERE site_id = :s AND slug = :sl AND id <> :id'
        );

        $candidate = $base;
        $n = 2;
        while (true) {
            $stmt->execute(['s' => $siteId, 'sl' => $candidate, 'id' => $articleId]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $candidate;
            }
            $candidate = $base . '-' . $n++;
        }
    }

    private static function nullable(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private static function validDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value . ' 00:00:00' : null;
    }
}

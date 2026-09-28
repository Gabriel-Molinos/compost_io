<?php

declare(strict_types=1);

namespace App\Services;

use App\Cache\CacheService;
use App\Database\Connection;

/**
 * Leitura/gravação de `wordpress_posts_mirror` (migration 0031) — a cópia
 * local de TODOS os posts do WordPress conectado de um site, COMPOST ou não
 * (pedido do responsável 2026-09-28: tudo pelo COMPOST, cópia local pra não
 * perder conteúdo numa invasão). Quem fala com o WordPress de verdade é
 * `WordPressSyncService::syncPostsMirror()` — este serviço só lê/grava a
 * tabela local.
 */
final class WordPressPostMirrorService
{
    private CacheService $cache;

    public function __construct(?CacheService $cache = null)
    {
        $this->cache = $cache ?? new CacheService();
    }

    public const ORIGIN_COMPOST = 'compost';
    public const ORIGIN_EXTERNAL = 'external';
    /** Sentinela pro filtro de autor "sem autor" — nunca um nome de verdade (`WordPressPostMirrorService::listForSite()`). */
    public const AUTHOR_NONE = '__none__';

    /** Colunas da lista/tela — de propósito SEM `content` (ver comentário em `listForSite()`). */
    private const LIST_COLUMNS = 'id, site_id, wordpress_post_id, article_id, title, slug, link, status, excerpt,
        featured_image_url, wordpress_author_name, wordpress_category_names,
        wordpress_published_at, wordpress_modified_at, last_synced_at, created_at';

    private const PER_PAGE = 20;

    /**
     * Lista filtrada e PAGINADA, mais recentes primeiro. Todo filtro é
     * opcional/combinável (mesmo espírito do filtro de Produção —
     * `ArticleService::allForSite()`).
     *
     * Achado real 2026-09-28 (perf, testado na Gavsy — 171 posts): esta
     * consulta chegava a ~2s porque trazia `content` (o HTML inteiro de CADA
     * post, ~13 KB em média aqui) pra TODOS os posts, embora a lista só
     * mostre título/miniatura/badges — `content` só é usado na tela de
     * EDIÇÃO de um post por vez (`find()`, que continua `SELECT *`). Tirar
     * essa coluna daqui + paginar (como a Produção já fazia) reduz a
     * consulta a uma fração disso e o HTML renderizado de ~170 cards pra 20.
     *
     * @param array{
     *   origin?: 'compost'|'external'|null,
     *   author?: string|null,
     *   hasImage?: bool|null,
     *   search?: string|null,
     * } $filters
     * @return list<array<string, mixed>>
     */
    public function listForSite(int $siteId, array $filters = [], int $page = 1, int $perPage = self::PER_PAGE): array
    {
        [$where, $params] = $this->buildWhere($siteId, $filters);
        $perPage = max(1, min(100, $perPage));
        $offset = max(0, ($page - 1)) * $perPage;

        $stmt = Connection::get()->prepare(
            "SELECT " . self::LIST_COLUMNS . " FROM wordpress_posts_mirror
             WHERE {$where}
             ORDER BY wordpress_published_at IS NULL, wordpress_published_at DESC, id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Total de posts que batem no filtro (pra montar a paginação) — mesmo WHERE de `listForSite()`, sem LIMIT. */
    public function countFiltered(int $siteId, array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($siteId, $filters);
        $stmt = Connection::get()->prepare("SELECT COUNT(*) FROM wordpress_posts_mirror WHERE {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** Separador improvável de aparecer num nome de autor de verdade — usado só pra "desmontar" o GROUP_CONCAT abaixo. */
    private const AUTHOR_SEP = "\x01";

    /**
     * Cabeçalho da tela (pílulas de origem, `<select>` de autor, "última
     * sincronização") numa consulta SÓ. Achado real de performance
     * 2026-09-28 (testado na Gavsy): cada consulta paga ~290ms de ida-e-volta
     * até o banco remoto (mesma latência documentada em
     * `docs/technical/testes-e-observabilidade.md §97`) — carregar essa tela
     * fazia `lastSyncedAt()` + `originCounts()` + `distinctAuthors()` como 3
     * consultas SEPARADAS (~870ms só nisso, antes de qualquer post ser
     * listado). Cacheado por 2 min (mesmo TTL de `ReportService::currentSpend()`)
     * porque só muda quando alguém sincroniza — não precisa ser em tempo real.
     *
     * @return array{lastSyncedAt: ?string, origins: array{all:int, compost:int, external:int}, authors: list<string>}
     */
    public function summary(int $siteId): array
    {
        return $this->cache->remember("wpposts:summary:{$siteId}", 120, function () use ($siteId): array {
            $stmt = Connection::get()->prepare(
                "SELECT
                    MAX(last_synced_at) AS last_synced_at,
                    COUNT(*) AS total,
                    SUM(article_id IS NOT NULL) AS compost,
                    GROUP_CONCAT(DISTINCT NULLIF(wordpress_author_name, '')
                                 ORDER BY wordpress_author_name SEPARATOR '" . self::AUTHOR_SEP . "') AS authors
                 FROM wordpress_posts_mirror WHERE site_id = :s"
            );
            $stmt->execute(['s' => $siteId]);
            $row = $stmt->fetch() ?: [];

            $total = (int) ($row['total'] ?? 0);
            $compost = (int) ($row['compost'] ?? 0);
            $authorsRaw = (string) ($row['authors'] ?? '');

            return [
                'lastSyncedAt' => isset($row['last_synced_at']) && $row['last_synced_at'] !== null ? (string) $row['last_synced_at'] : null,
                'origins'      => ['all' => $total, 'compost' => $compost, 'external' => $total - $compost],
                'authors'      => $authorsRaw !== '' ? explode(self::AUTHOR_SEP, $authorsRaw) : [],
            ];
        });
    }

    /**
     * Chamado por `WordPressSyncService::syncPostsMirror()` depois de um sync bem-sucedido
     * — sem isso, "Sincronizar agora" salva os posts novos mas a tela continua mostrando a
     * "última sincronização"/contagens antigas até o cache de `summary()` expirar (até 2 min).
     */
    public function invalidateSummaryCache(int $siteId): void
    {
        $this->cache->forget("wpposts:summary:{$siteId}");
    }

    /**
     * @param array{origin?: ?string, author?: ?string, hasImage?: ?bool, search?: ?string} $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(int $siteId, array $filters): array
    {
        $where = ['site_id = :s'];
        $params = ['s' => $siteId];

        $origin = $filters['origin'] ?? null;
        if ($origin === self::ORIGIN_COMPOST) {
            $where[] = 'article_id IS NOT NULL';
        } elseif ($origin === self::ORIGIN_EXTERNAL) {
            $where[] = 'article_id IS NULL';
        }

        $author = $filters['author'] ?? null;
        if ($author === self::AUTHOR_NONE) {
            $where[] = "(wordpress_author_name IS NULL OR wordpress_author_name = '')";
        } elseif ($author !== null && $author !== '') {
            $where[] = 'wordpress_author_name = :author';
            $params['author'] = $author;
        }

        $hasImage = $filters['hasImage'] ?? null;
        if ($hasImage === true) {
            $where[] = "(featured_image_url IS NOT NULL AND featured_image_url <> '')";
        } elseif ($hasImage === false) {
            $where[] = "(featured_image_url IS NULL OR featured_image_url = '')";
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            // :search1/:search2 (não o mesmo nome duas vezes): com EMULATE_PREPARES desligado
            // (prepare de verdade no servidor, ver Connection::get()), reusar o mesmo parâmetro
            // nomeado mais de uma vez na mesma query dá "Invalid parameter number" — achado real
            // 2026-09-28, pego pelo próprio teste (testFiltersCombine/testFilterBySearch...).
            $where[] = '(title LIKE :search1 OR excerpt LIKE :search2)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $params['search1'] = $like;
            $params['search2'] = $like;
        }

        return [implode(' AND ', $where), $params];
    }

    /** @return array<string, mixed>|null */
    public function find(int $siteId, int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM wordpress_posts_mirror WHERE id = :id AND site_id = :s LIMIT 1'
        );
        $stmt->execute(['id' => $id, 's' => $siteId]);

        return $stmt->fetch() ?: null;
    }

    public function countForSite(int $siteId): int
    {
        $stmt = Connection::get()->prepare('SELECT COUNT(*) FROM wordpress_posts_mirror WHERE site_id = :s');
        $stmt->execute(['s' => $siteId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Grava (ou atualiza) um post no espelho — `article_id` é resolvido por
     * quem chama (`WordPressSyncService`, via `schedules.wordpress_post_id`).
     *
     * @param array{
     *   id:int, link:string, slug:string, status:string, date_gmt:string, modified_gmt:string,
     *   title:string, excerpt:string, content:string, featured_image_url:?string,
     *   author_name:?string, category_names:list<string>
     * } $post
     */
    public function upsert(int $siteId, array $post, ?int $articleId): void
    {
        Connection::get()->prepare(
            'INSERT INTO wordpress_posts_mirror
                (site_id, wordpress_post_id, article_id, title, slug, link, status, excerpt, content,
                 featured_image_url, wordpress_author_name, wordpress_category_names,
                 wordpress_published_at, wordpress_modified_at, last_synced_at)
             VALUES
                (:site_id, :wp_id, :article_id, :title, :slug, :link, :status, :excerpt, :content,
                 :featured_image_url, :author_name, :category_names,
                 :published_at, :modified_at, NOW())
             ON DUPLICATE KEY UPDATE
                article_id = VALUES(article_id), title = VALUES(title), slug = VALUES(slug),
                link = VALUES(link), status = VALUES(status), excerpt = VALUES(excerpt),
                content = VALUES(content), featured_image_url = VALUES(featured_image_url),
                wordpress_author_name = VALUES(wordpress_author_name),
                wordpress_category_names = VALUES(wordpress_category_names),
                wordpress_published_at = VALUES(wordpress_published_at),
                wordpress_modified_at = VALUES(wordpress_modified_at),
                last_synced_at = NOW()'
        )->execute([
            'site_id'            => $siteId,
            'wp_id'              => $post['id'],
            'article_id'         => $articleId,
            'title'              => mb_substr($post['title'], 0, 500),
            'slug'               => $post['slug'] !== '' ? mb_substr($post['slug'], 0, 255) : null,
            'link'               => mb_substr($post['link'], 0, 1024),
            'status'             => mb_substr($post['status'], 0, 20),
            'excerpt'            => $post['excerpt'] !== '' ? $post['excerpt'] : null,
            'content'            => $post['content'],
            'featured_image_url' => $post['featured_image_url'] !== null ? mb_substr($post['featured_image_url'], 0, 1024) : null,
            'author_name'        => $post['author_name'] !== null ? mb_substr($post['author_name'], 0, 191) : null,
            'category_names'     => $post['category_names'] !== [] ? mb_substr(implode(', ', $post['category_names']), 0, 500) : null,
            'published_at'       => self::toLocalDatetime($post['date_gmt']),
            'modified_at'        => self::toLocalDatetime($post['modified_gmt']),
        ]);
    }

    /** Espelho de posts que somem do WordPress (apagados de vez lá) — tira do espelho. */
    public function pruneMissing(int $siteId, array $seenWordpressPostIds): void
    {
        if ($seenWordpressPostIds === []) {
            return; // sync vazio/falhou no meio — nunca apaga tudo por segurança
        }
        $in = implode(',', array_fill(0, count($seenWordpressPostIds), '?'));
        $params = array_merge([$siteId], array_values($seenWordpressPostIds));
        Connection::get()->prepare(
            "DELETE FROM wordpress_posts_mirror WHERE site_id = ? AND wordpress_post_id NOT IN ({$in})"
        )->execute($params);
    }

    /** `date_gmt`/`modified_gmt` do WordPress vêm em UTC — grava convertido pro fuso do site (mesmo padrão do resto do app, ver Connection::get()). */
    private static function toLocalDatetime(string $gmtIso): ?string
    {
        if (trim($gmtIso) === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($gmtIso, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }
}

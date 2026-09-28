<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\ArticleService;
use App\Services\SiteService;
use App\Services\WordPressPostMirrorService;
use App\Services\WordPressSyncService;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * `WordPressSyncService::syncPostsMirror()` (pedido do responsável 2026-09-28:
 * cópia local de TODOS os posts do WordPress, COMPOST ou não — segurança/
 * resiliência contra invasão). `$fetchPostsPage` (closure injetada) substitui
 * a chamada real ao WordPress.
 */
final class WordPressSyncServicePostsMirrorTest extends TestCase
{
    private ?int $siteId = null;
    private WordPressPostMirrorService $mirror;

    protected function setUp(): void
    {
        try {
            Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }
        $this->mirror = new WordPressPostMirrorService();
        $this->siteId = (new SiteService())->create(['name' => 'Site de teste MirrorWP ' . bin2hex(random_bytes(4)), 'language' => 'pt-BR']);
    }

    protected function tearDown(): void
    {
        if ($this->siteId !== null) {
            Connection::get()->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $this->siteId]);
            $this->siteId = null;
        }
    }

    /** @return array<string, mixed> post "cru" no mesmo formato que WordPressClient::listAllPostsPage() devolve */
    private function fakePost(int $wpId, string $title, string $status = 'publish'): array
    {
        return [
            'id' => $wpId, 'link' => 'https://exemplo.com/p/' . $wpId, 'slug' => 'post-' . $wpId,
            'status' => $status, 'date_gmt' => '2026-09-20T12:00:00', 'modified_gmt' => '2026-09-21T12:00:00',
            'title' => $title, 'excerpt' => 'Resumo de ' . $title, 'content' => '<p>' . $title . '</p>',
            'featured_image_url' => null, 'author_name' => 'Fulano', 'category_names' => ['Categoria X'],
        ];
    }

    private function onePageOf(array $posts): \Closure
    {
        return static function (int $page, int $perPage) use ($posts): array {
            return $page === 1 ? $posts : [];
        };
    }

    public function testSyncStoresEveryPostLocally(): void
    {
        $sync = new WordPressSyncService(fetchPostsPage: $this->onePageOf([
            $this->fakePost(101, 'Post externo'),
            $this->fakePost(102, 'Outro post externo'),
        ]));

        $result = $sync->syncPostsMirror($this->siteId);

        $this->assertSame(['synced' => 2, 'compost' => 0, 'external' => 2], $result);
        $rows = $this->mirror->listForSite($this->siteId);
        $this->assertCount(2, $rows);
        // listForSite() de propósito NÃO traz `content` (achado de performance 2026-09-28 —
        // ver WordPressPostMirrorService::LIST_COLUMNS); quem quer o conteúdo guardado de
        // verdade usa find(), que continua SELECT *.
        $this->assertArrayNotHasKey('content', $rows[1]);
        $found = $this->mirror->find($this->siteId, (int) $rows[1]['id']);
        $this->assertSame('<p>Post externo</p>', $found['content']); // conteúdo de verdade guardado local
    }

    public function testPostCreatedByCompostGetsLinkedToItsArticle(): void
    {
        $articleId = (new ArticleService())->create($this->siteId, null);
        // Um agendamento PUBLISHED com wordpress_post_id = "o COMPOST publicou este post".
        Connection::get()->prepare(
            "INSERT INTO schedules (article_id, scheduled_date, status, wordpress_post_id) VALUES (:a, NOW(), 'PUBLISHED', :wid)"
        )->execute(['a' => $articleId, 'wid' => 555]);

        $sync = new WordPressSyncService(fetchPostsPage: $this->onePageOf([
            $this->fakePost(555, 'Post feito pelo COMPOST'),
            $this->fakePost(556, 'Post feito direto no WP'),
        ]));

        $result = $sync->syncPostsMirror($this->siteId);

        $this->assertSame(['synced' => 2, 'compost' => 1, 'external' => 1], $result);
        $rows = $this->mirror->listForSite($this->siteId);
        $byWpId = [];
        foreach ($rows as $r) {
            $byWpId[(int) $r['wordpress_post_id']] = $r;
        }
        $this->assertSame($articleId, (int) $byWpId[555]['article_id']);
        $this->assertNull($byWpId[556]['article_id']);
    }

    public function testResyncUpdatesExistingRowInsteadOfDuplicating(): void
    {
        $sync = new WordPressSyncService(fetchPostsPage: $this->onePageOf([$this->fakePost(201, 'Título original')]));
        $sync->syncPostsMirror($this->siteId);

        $sync2 = new WordPressSyncService(fetchPostsPage: $this->onePageOf([$this->fakePost(201, 'Título atualizado')]));
        $sync2->syncPostsMirror($this->siteId);

        $rows = $this->mirror->listForSite($this->siteId);
        $this->assertCount(1, $rows);
        $this->assertSame('Título atualizado', $rows[0]['title']);
    }

    public function testPostDeletedForGoodOnWordPressIsPrunedFromMirror(): void
    {
        $sync = new WordPressSyncService(fetchPostsPage: $this->onePageOf([
            $this->fakePost(301, 'Fica'),
            $this->fakePost(302, 'Vai sumir'),
        ]));
        $sync->syncPostsMirror($this->siteId);
        $this->assertCount(2, $this->mirror->listForSite($this->siteId));

        $sync2 = new WordPressSyncService(fetchPostsPage: $this->onePageOf([$this->fakePost(301, 'Fica')]));
        $sync2->syncPostsMirror($this->siteId);

        $rows = $this->mirror->listForSite($this->siteId);
        $this->assertCount(1, $rows);
        $this->assertSame(301, (int) $rows[0]['wordpress_post_id']);
    }

    public function testLastSyncedAtReflectsTheSyncEvenThoughSummaryIsCached(): void
    {
        // summary() é cacheado (2 min, achado de performance 2026-09-28) — este teste também
        // cobre que syncPostsMirror() invalida esse cache (invalidateSummaryCache()), senão a
        // 2ª leitura abaixo veria o "null" de antes do sync até o cache expirar sozinho.
        $this->assertNull($this->mirror->summary($this->siteId)['lastSyncedAt']);

        (new WordPressSyncService(fetchPostsPage: $this->onePageOf([$this->fakePost(401, 'X')])))
            ->syncPostsMirror($this->siteId);

        $this->assertNotNull($this->mirror->summary($this->siteId)['lastSyncedAt']);
    }

    public function testDeletingTheSiteCascadesTheMirror(): void
    {
        (new WordPressSyncService(fetchPostsPage: $this->onePageOf([$this->fakePost(501, 'X')])))
            ->syncPostsMirror($this->siteId);
        $this->assertSame(1, $this->mirror->countForSite($this->siteId));

        Connection::get()->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $this->siteId]);
        $this->assertSame(0, $this->mirror->countForSite($this->siteId));
        $this->siteId = null; // já apagado, tearDown não precisa fazer de novo
    }
}

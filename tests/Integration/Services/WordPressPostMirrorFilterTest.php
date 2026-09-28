<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\ArticleService;
use App\Services\SiteService;
use App\Services\WordPressPostMirrorService;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Filtros de `WordPressPostMirrorService::listForSite()` (pedido do
 * responsável 2026-09-28, redesign de "Todos os posts"): origem
 * (COMPOST/direto no WordPress), autor (incl. "sem autor"), imagem
 * destacada (com/sem) e busca por título/resumo — combináveis.
 */
final class WordPressPostMirrorFilterTest extends TestCase
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
        $this->siteId = (new SiteService())->create(['name' => 'Site de teste FiltroMirror ' . bin2hex(random_bytes(4)), 'language' => 'pt-BR']);

        // 4 posts: com/sem autor, com/sem imagem, um do COMPOST.
        $articleId = (new ArticleService())->create($this->siteId, null);
        Connection::get()->prepare(
            "INSERT INTO schedules (article_id, scheduled_date, status, wordpress_post_id) VALUES (:a, NOW(), 'PUBLISHED', 1)"
        )->execute(['a' => $articleId]);

        $this->mirror->upsert($this->siteId, $this->post(1, 'Guia de viagem econômica', 'Maria', 'https://x/img1.webp'), $articleId);
        $this->mirror->upsert($this->siteId, $this->post(2, 'Receita de bolo simples', 'João', null), null);
        $this->mirror->upsert($this->siteId, $this->post(3, 'Post sem autor nenhum', null, 'https://x/img3.webp'), null);
        $this->mirror->upsert($this->siteId, $this->post(4, 'Outro post do João', 'João', null), null);
    }

    protected function tearDown(): void
    {
        if ($this->siteId !== null) {
            Connection::get()->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $this->siteId]);
            $this->siteId = null;
        }
    }

    /** @return array<string, mixed> */
    private function post(int $wpId, string $title, ?string $author, ?string $image): array
    {
        return [
            'id' => $wpId, 'link' => 'https://exemplo.com/' . $wpId, 'slug' => 'p' . $wpId, 'status' => 'publish',
            'date_gmt' => '2026-09-20T12:00:00', 'modified_gmt' => '2026-09-20T12:00:00',
            'title' => $title, 'excerpt' => 'resumo de ' . $title, 'content' => '<p>x</p>',
            'featured_image_url' => $image, 'author_name' => $author, 'category_names' => [],
        ];
    }

    private function titles(array $filters): array
    {
        return array_column($this->mirror->listForSite($this->siteId, $filters), 'title');
    }

    public function testNoFiltersReturnsEverything(): void
    {
        $this->assertCount(4, $this->titles([]));
    }

    public function testFilterByOriginCompost(): void
    {
        $this->assertSame(['Guia de viagem econômica'], $this->titles(['origin' => 'compost']));
    }

    public function testFilterByOriginExternal(): void
    {
        $this->assertCount(3, $this->titles(['origin' => 'external']));
    }

    public function testFilterByAuthorName(): void
    {
        $titles = $this->titles(['author' => 'João']);
        sort($titles);
        $this->assertSame(['Outro post do João', 'Receita de bolo simples'], $titles);
    }

    public function testFilterByNoAuthor(): void
    {
        $this->assertSame(['Post sem autor nenhum'], $this->titles(['author' => WordPressPostMirrorService::AUTHOR_NONE]));
    }

    public function testFilterByHasImage(): void
    {
        $titles = $this->titles(['hasImage' => true]);
        sort($titles);
        $this->assertSame(['Guia de viagem econômica', 'Post sem autor nenhum'], $titles);
    }

    public function testFilterByNoImage(): void
    {
        $titles = $this->titles(['hasImage' => false]);
        sort($titles);
        $this->assertSame(['Outro post do João', 'Receita de bolo simples'], $titles);
    }

    public function testFilterBySearchMatchesTitleOrExcerpt(): void
    {
        $this->assertSame(['Receita de bolo simples'], $this->titles(['search' => 'bolo']));
    }

    public function testFiltersCombine(): void
    {
        $this->assertSame(['Outro post do João'], $this->titles(['author' => 'João', 'hasImage' => false, 'search' => 'outro']));
    }

    public function testSearchWithPercentDoesNotActAsWildcard(): void
    {
        // Achado real (proteção): sem escapar % e _, uma busca literal por "%" viraria
        // "tudo" (LIKE '%%%') — confirma que o escape em buildWhere() está funcionando.
        $this->assertSame([], $this->titles(['search' => '%_%']));
    }

    public function testOriginCountsReflectTheWholeSiteNotTheFilteredResult(): void
    {
        $counts = $this->mirror->originCounts($this->siteId);
        $this->assertSame(['all' => 4, 'compost' => 1, 'external' => 3], $counts);
    }

    public function testDistinctAuthorsListsEachNameOnce(): void
    {
        $this->assertSame(['João', 'Maria'], $this->mirror->distinctAuthors($this->siteId));
    }
}

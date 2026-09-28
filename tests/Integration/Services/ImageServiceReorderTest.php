<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\ArticleService;
use App\Services\ImageService;
use App\Services\SiteService;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * `ImageService::reorderBody()` (pedido do responsável 2026-09-28 — Redator-Chefe
 * escolhe a ordem das imagens de corpo, e com isso onde cada uma vai ficar no
 * artigo). Guarda principal: só aplica se a lista dada bater EXATAMENTE com o
 * conjunto de imagens BODY do artigo — nada de meio-aplicado.
 */
final class ImageServiceReorderTest extends TestCase
{
    private ?int $siteId = null;
    private ?int $articleId = null;
    private ImageService $images;

    protected function setUp(): void
    {
        try {
            Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }
        $this->images = new ImageService();
        $sites = new SiteService();
        $this->siteId = $sites->create(['name' => 'Site de teste ReorderImg ' . bin2hex(random_bytes(4)), 'language' => 'pt-BR']);
        $this->articleId = (new ArticleService())->create($this->siteId, null);
    }

    protected function tearDown(): void
    {
        if ($this->siteId !== null) {
            Connection::get()->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $this->siteId]);
            $this->siteId = null;
        }
    }

    /** @return list<int> ids das 3 imagens BODY criadas, na ordem de criação */
    private function seedThreeBodyImages(): array
    {
        return [
            $this->images->add($this->articleId, 'BODY', '/a.webp', null, 'A', 'webp'),
            $this->images->add($this->articleId, 'BODY', '/b.webp', null, 'B', 'webp'),
            $this->images->add($this->articleId, 'BODY', '/c.webp', null, 'C', 'webp'),
        ];
    }

    public function testDefaultOrderIsCreationOrder(): void
    {
        [$a, $b, $c] = $this->seedThreeBodyImages();

        $ordered = array_column($this->images->bodyImagesOrdered($this->articleId), 'id');

        $this->assertSame([$a, $b, $c], $ordered);
    }

    public function testReorderChangesTheOrderReturned(): void
    {
        [$a, $b, $c] = $this->seedThreeBodyImages();

        $ok = $this->images->reorderBody($this->articleId, [$c, $a, $b]);

        $this->assertTrue($ok);
        $this->assertSame([$c, $a, $b], array_column($this->images->bodyImagesOrdered($this->articleId), 'id'));
        // A galeria completa (forArticle, usada pela tela) também respeita a nova ordem.
        $bodyFromGallery = array_column(
            array_filter($this->images->forArticle($this->articleId), static fn ($i) => $i['role'] === 'BODY'),
            'id'
        );
        $this->assertSame([$c, $a, $b], array_values($bodyFromGallery));
    }

    public function testRejectsListMissingAnImage(): void
    {
        [$a, $b, ] = $this->seedThreeBodyImages();

        $ok = $this->images->reorderBody($this->articleId, [$a, $b]); // falta a 3ª

        $this->assertFalse($ok);
        // Nada mudou — continua na ordem de criação.
        $this->assertSame([$a, $b], array_slice(array_column($this->images->bodyImagesOrdered($this->articleId), 'id'), 0, 2));
    }

    public function testRejectsListWithAnIdFromOutside(): void
    {
        [$a, $b, $c] = $this->seedThreeBodyImages();
        $other = (new ArticleService())->create($this->siteId, null);
        $foreignId = $this->images->add($other, 'BODY', '/x.webp', null, 'X', 'webp');

        $ok = $this->images->reorderBody($this->articleId, [$a, $b, $foreignId]);

        $this->assertFalse($ok);
        $this->assertSame([$a, $b, $c], array_column($this->images->bodyImagesOrdered($this->articleId), 'id'));

        Connection::get()->prepare('DELETE FROM articles WHERE id = :id')->execute(['id' => $other]);
    }

    public function testFeaturedImageIsNeverTouchedByBodyReorder(): void
    {
        [$a, $b, $c] = $this->seedThreeBodyImages();
        $featured = $this->images->add($this->articleId, 'FEATURED', '/f.webp', null, 'F', 'webp');

        $this->images->reorderBody($this->articleId, [$c, $b, $a]);

        $row = $this->images->find($this->articleId, $featured);
        $this->assertNull($row['sort_order']);
    }

    public function testEmptySiteHasNoBodyImagesToReorder(): void
    {
        $this->assertSame(false, $this->images->reorderBody($this->articleId, []));
        $this->assertSame([], $this->images->bodyImagesOrdered($this->articleId));
    }
}

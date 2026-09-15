<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SiteLogoLibraryService;
use PHPUnit\Framework\TestCase;

/**
 * Bate no diretório real `public/assets/site-logos-library/` (assets
 * versionados, não gerados em runtime) — determinístico, sem banco/HTTP, por
 * isso mora em Unit mesmo lendo do disco.
 */
final class SiteLogoLibraryServiceTest extends TestCase
{
    private SiteLogoLibraryService $library;

    protected function setUp(): void
    {
        $this->library = new SiteLogoLibraryService();
    }

    public function testMatchesExactLowercaseDomain(): void
    {
        $match = $this->library->findForDomain('https://gavsy.com');

        $this->assertNotNull($match);
        $this->assertStringEndsWith('gavsy.com.png', strtolower($match));
    }

    public function testMatchesCaseInsensitiveUppercaseFile(): void
    {
        // Arquivo real é "VALORIZEI.COM.png".
        $match = $this->library->findForDomain('https://valorizei.com');

        $this->assertNotNull($match);
    }

    public function testStripsWwwAndPathBeforeMatching(): void
    {
        $match = $this->library->findForDomain('https://www.gavsy.com/algum/caminho?query=1');

        $this->assertNotNull($match);
        $this->assertStringEndsWith('gavsy.com.png', strtolower($match));
    }

    public function testMatchesDomainWithoutScheme(): void
    {
        $match = $this->library->findForDomain('gavsy.com');

        $this->assertNotNull($match);
    }

    public function testMatchesFileWithDuplicateDownloadSuffix(): void
    {
        // Arquivo real: "actiow.com (44).png".
        $match = $this->library->findForDomain('https://actiow.com');

        $this->assertNotNull($match);
    }

    public function testReturnsNullForUnknownDomain(): void
    {
        $match = $this->library->findForDomain('https://dominio-que-nao-existe-nesta-biblioteca.invalid');

        $this->assertNull($match);
    }

    public function testReturnsNullForEmptyOrNullUrl(): void
    {
        $this->assertNull($this->library->findForDomain(null));
        $this->assertNull($this->library->findForDomain(''));
        $this->assertNull($this->library->findForDomain('   '));
    }

    public function testAllReturnsEveryFileWithDomainAndUrl(): void
    {
        $all = $this->library->all();

        $this->assertCount(89, $all);
        $gavsy = array_values(array_filter($all, static fn (array $l): bool => $l['domain'] === 'gavsy.com'));
        $this->assertCount(1, $gavsy);
        $this->assertSame('gavsy.com.png', $gavsy[0]['filename']);
        $this->assertSame('/assets/site-logos-library/gavsy.com.png', $gavsy[0]['url']);
    }

    public function testAllUrlEncodesFilenamesWithSpaces(): void
    {
        $all = $this->library->all();
        $withSuffix = array_values(array_filter($all, static fn (array $l): bool => $l['domain'] === 'actiow.com'));

        $this->assertCount(1, $withSuffix);
        $this->assertStringNotContainsString(' ', $withSuffix[0]['url']);
    }

    public function testAllExcludesFilenamesAlreadyInUse(): void
    {
        // Achado real 2026-09-15: sem exclusão, a mesma logo (gavsy.com.png)
        // podia ser escolhida pra dois sites diferentes.
        $all = $this->library->all(['gavsy.com.png', 'penazo.com.png']);

        $domains = array_column($all, 'domain');
        $this->assertNotContains('gavsy.com', $domains);
        $this->assertNotContains('penazo.com', $domains);
        $this->assertCount(87, $all);
    }

    public function testFindByFilenameResolvesRealFile(): void
    {
        $match = $this->library->findByFilename('gavsy.com.png');

        $this->assertNotNull($match);
        $this->assertStringEndsWith('gavsy.com.png', $match);
    }

    public function testFindByFilenameRejectsPathTraversal(): void
    {
        // basename() já neutraliza isso, mas confirma que nunca escapa da
        // biblioteca pra um arquivo arbitrário do servidor.
        $this->assertNull($this->library->findByFilename('../../../../etc/passwd'));
        $this->assertNull($this->library->findByFilename('arquivo-que-nao-existe.png'));
    }

    public function testFindByFilenameReturnsNullForEmptyOrNull(): void
    {
        $this->assertNull($this->library->findByFilename(null));
        $this->assertNull($this->library->findByFilename(''));
        $this->assertNull($this->library->findByFilename('   '));
    }
}

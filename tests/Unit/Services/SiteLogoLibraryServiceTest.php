<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SiteLogoLibraryService;
use PHPUnit\Framework\TestCase;

/**
 * Bate no diretório real `storage/site-logos-library/` (assets versionados,
 * não gerados em runtime) — determinístico, sem banco/HTTP, por isso mora em
 * Unit mesmo lendo do disco.
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
}

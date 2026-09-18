<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SiteListing;
use PHPUnit\Framework\TestCase;

final class SiteListingTest extends TestCase
{
    /** @param array<string, mixed> $over */
    private function site(array $over = []): array
    {
        return $over + [
            'id' => 1, 'name' => 'Alfa', 'niche' => 'Viagens', 'language' => 'English', 'wordpress_url' => 'https://alfa.com',
            'is_active' => 1, 'wp_configured' => true, 'wp_status' => 'OK',
            'in_review' => 0, 'attention' => 0, 'done' => 0, 'total' => 0,
        ];
    }

    public function testWpState(): void
    {
        $this->assertSame('OK', SiteListing::wpState($this->site()));
        $this->assertSame('FAILED', SiteListing::wpState($this->site(['wp_status' => 'FAILED'])));
        $this->assertSame('UNVERIFIED', SiteListing::wpState($this->site(['wp_status' => 'UNVERIFIED'])));
        $this->assertSame('NONE', SiteListing::wpState($this->site(['wp_configured' => false, 'wp_status' => null])));
    }

    public function testToneFollowsPriority(): void
    {
        $this->assertSame('success', SiteListing::tone($this->site()));
        $this->assertSame('cyan', SiteListing::tone($this->site(['in_review' => 2])));
        $this->assertSame('warning', SiteListing::tone($this->site(['wp_configured' => false])));
        $this->assertSame('danger', SiteListing::tone($this->site(['attention' => 1, 'in_review' => 3])));
        $this->assertSame('danger', SiteListing::tone($this->site(['wp_status' => 'FAILED'])));
        // Inativo vence qualquer outro estado.
        $this->assertSame('muted', SiteListing::tone($this->site(['is_active' => 0, 'attention' => 5])));
    }

    public function testFilterCombinesCriteria(): void
    {
        $sites = [
            $this->site(['id' => 1, 'name' => 'Alfa']),
            $this->site(['id' => 2, 'name' => 'Beta', 'niche' => 'Finanças', 'language' => 'pt-BR', 'is_active' => 0]),
            $this->site(['id' => 3, 'name' => 'Gama', 'wp_configured' => false, 'wp_status' => null, 'attention' => 2]),
        ];

        $ids = static fn (array $l): array => array_column($l, 'id');

        $this->assertSame([1, 2, 3], $ids(SiteListing::filter($sites)));
        $this->assertSame([1, 3], $ids(SiteListing::filter($sites, status: 'active')));
        $this->assertSame([2], $ids(SiteListing::filter($sites, status: 'inactive')));
        $this->assertSame([3], $ids(SiteListing::filter($sites, status: 'attention')));
        $this->assertSame([3], $ids(SiteListing::filter($sites, wp: 'none')));
        $this->assertSame([1, 2], $ids(SiteListing::filter($sites, wp: 'ok')));
        $this->assertSame([2], $ids(SiteListing::filter($sites, lang: 'pt')));
        $this->assertSame([1, 3], $ids(SiteListing::filter($sites, lang: 'en')));
        $this->assertSame([2], $ids(SiteListing::filter($sites, q: 'FINAN')));
        $this->assertSame([1], $ids(SiteListing::filter($sites, q: 'alfa.com', status: 'active', wp: 'ok')));
        $this->assertSame([], $ids(SiteListing::filter($sites, q: 'nada disso')));
    }

    public function testCustomLanguageKeyIsLowercasedText(): void
    {
        $this->assertSame('français', SiteListing::languageKey($this->site(['language' => 'Français'])));
        $this->assertSame('pt', SiteListing::languageKey($this->site(['language' => 'pt-BR'])));
    }

    public function testSortOrders(): void
    {
        $sites = [
            $this->site(['id' => 1, 'name' => 'Beta']),
            $this->site(['id' => 2, 'name' => 'alfa', 'in_review' => 1]),
            $this->site(['id' => 3, 'name' => 'Gama', 'attention' => 1]),
        ];
        $ids = static fn (array $l): array => array_column($l, 'id');

        $this->assertSame([2, 1, 3], $ids(SiteListing::sort($sites, 'name')));
        $this->assertSame([3, 2, 1], $ids(SiteListing::sort($sites, 'attention')));
        $this->assertSame([2, 1, 3], $ids(SiteListing::sort($sites, 'review')));
        $this->assertSame([3, 2, 1], $ids(SiteListing::sort($sites, 'newest')));
    }
}

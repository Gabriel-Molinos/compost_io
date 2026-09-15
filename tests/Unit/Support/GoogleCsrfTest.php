<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\GoogleCsrf;
use PHPUnit\Framework\TestCase;

final class GoogleCsrfTest extends TestCase
{
    public function testMatchesWhenTokensAreEqual(): void
    {
        $this->assertTrue(GoogleCsrf::tokensMatch('abc123', 'abc123'));
    }

    public function testFailsWhenTokensDiffer(): void
    {
        $this->assertFalse(GoogleCsrf::tokensMatch('abc123', 'xyz789'));
    }

    public function testFailsWhenCookieIsNull(): void
    {
        $this->assertFalse(GoogleCsrf::tokensMatch(null, 'abc123'));
    }

    public function testFailsWhenBodyIsNull(): void
    {
        $this->assertFalse(GoogleCsrf::tokensMatch('abc123', null));
    }

    public function testFailsWhenBothAreNull(): void
    {
        $this->assertFalse(GoogleCsrf::tokensMatch(null, null));
    }

    public function testFailsWhenBothAreEmptyStrings(): void
    {
        // Guarda contra o caso trivial: cookie vazio "batendo" com campo vazio.
        $this->assertFalse(GoogleCsrf::tokensMatch('', ''));
    }
}

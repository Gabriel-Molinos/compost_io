<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ArticleReviewService;
use PHPUnit\Framework\TestCase;

/**
 * Só a parte pura de ArticleReviewService (checklistPassed é `static` e não
 * toca banco). checklist()/approve()/requestRevision() precisam de
 * Connection::get() de verdade — cobertos em tests/Integration.
 */
final class ArticleReviewServiceTest extends TestCase
{
    public function testPassesWhenAllItemsOk(): void
    {
        $checklist = [
            'category'       => ['ok' => true, 'label' => 'Categoria válida', 'detail' => 'Definida.'],
            'internal_links' => ['ok' => true, 'label' => 'Links internos (3 a 5)', 'detail' => '4 encontrado(s).'],
            'external_links' => ['ok' => true, 'label' => 'Links externos (máx. 2)', 'detail' => '1 encontrado(s).'],
        ];

        $this->assertTrue(ArticleReviewService::checklistPassed($checklist));
    }

    public function testFailsWhenAnySingleItemFails(): void
    {
        $checklist = [
            'category'       => ['ok' => true, 'label' => 'Categoria válida', 'detail' => 'Definida.'],
            'internal_links' => ['ok' => false, 'label' => 'Links internos (3 a 5)', 'detail' => '0 encontrado(s).'],
            'external_links' => ['ok' => true, 'label' => 'Links externos (máx. 2)', 'detail' => '1 encontrado(s).'],
        ];

        $this->assertFalse(ArticleReviewService::checklistPassed($checklist));
    }

    public function testEmptyChecklistPassesVacuously(): void
    {
        $this->assertTrue(ArticleReviewService::checklistPassed([]));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ArticleService;
use App\Support\Labels;
use PHPUnit\Framework\TestCase;

/**
 * Rascunho trancado enquanto gera (2026-09-29): não abre até ficar pronto,
 * mas destrava sozinho se passar do tempo — senão um worker parado deixaria
 * o rascunho inacessível pra sempre.
 */
final class ArticleGenerationLockTest extends TestCase
{
    /** @return array<string,mixed> */
    private function article(string $status, string $createdAgo): array
    {
        return ['status' => $status, 'created_at' => date('Y-m-d H:i:s', strtotime($createdAgo))];
    }

    public function testGeneratingArticleIsLocked(): void
    {
        $this->assertTrue(ArticleService::isLockedForGeneration($this->article('PLANNED', '-1 minute')));
        $this->assertTrue(ArticleService::isLockedForGeneration($this->article('IN_PROGRESS', '-5 minutes')));
    }

    public function testFinishedOrFailedArticleIsNotLocked(): void
    {
        foreach (['IN_REVIEW', 'ERROR', 'APPROVED', 'BLOCKED'] as $status) {
            $this->assertFalse(ArticleService::isLockedForGeneration($this->article($status, '-1 minute')), $status);
        }
    }

    public function testStaleGenerationUnlocksSoItCanBeInspected(): void
    {
        $stale = $this->article('IN_PROGRESS', '-' . (ArticleService::GENERATION_STALE_MINUTES + 1) . ' minutes');

        $this->assertTrue(ArticleService::isGenerating($stale));
        $this->assertTrue(ArticleService::isGenerationStale($stale));
        $this->assertFalse(ArticleService::isLockedForGeneration($stale));
    }

    public function testGenerationStepLabels(): void
    {
        $this->assertSame('Na fila, aguardando a IA', Labels::generationStep(null));
        $this->assertSame('Escrevendo o texto', Labels::generationStep('writing'));
        $this->assertSame('Gerando as imagens', Labels::generationStep('image'));
        $this->assertSame('Finalizando', Labels::generationStep('passo_novo'));
    }
}

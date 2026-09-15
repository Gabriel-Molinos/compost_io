<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Labels;
use PHPUnit\Framework\TestCase;

final class LabelsTest extends TestCase
{
    public function testRoleTranslatesKnownValues(): void
    {
        $this->assertSame('Administrador', Labels::role('ADMIN'));
        $this->assertSame('Redator-Chefe', Labels::role('REDATOR_CHEFE'));
    }

    public function testRoleFallsBackToRawValueWhenUnknown(): void
    {
        $this->assertSame('ALGO_NOVO', Labels::role('ALGO_NOVO'));
    }

    public function testArticleStatusTranslatesAllKnownStates(): void
    {
        $expected = [
            'PLANNED'            => 'Planejado',
            'IN_PROGRESS'        => 'Em produção',
            'IN_REVIEW'          => 'Em revisão',
            'REVISION_REQUESTED' => 'Revisão pedida',
            'APPROVED'           => 'Aprovado',
            'SCHEDULED'          => 'Agendado',
            'PUBLISHED'          => 'Publicado',
            'DISCARDED'          => 'Descartado',
            'BLOCKED'            => 'Bloqueado',
            'ERROR'              => 'Falha técnica',
        ];

        foreach ($expected as $status => $label) {
            $this->assertSame($label, Labels::articleStatus($status));
        }
    }

    /**
     * R-UI-07: cor nunca é a única pista — todo status de artigo precisa ter
     * um tom mapeado (nem que seja o "muted" default), pra badge sempre
     * conseguir montar classes de cor válidas junto com o texto.
     */
    public function testEveryArticleStatusHasATone(): void
    {
        $statuses = ['PLANNED', 'IN_PROGRESS', 'IN_REVIEW', 'REVISION_REQUESTED', 'APPROVED',
            'SCHEDULED', 'PUBLISHED', 'DISCARDED', 'BLOCKED', 'ERROR'];

        foreach ($statuses as $status) {
            $tone = Labels::articleStatusTone($status);
            $this->assertNotSame('', Labels::toneClasses($tone), "Status sem classes de tom: {$status}");
        }
    }

    public function testDangerToneAppliesToBlockedAndError(): void
    {
        $this->assertSame('danger', Labels::articleStatusTone('BLOCKED'));
        $this->assertSame('danger', Labels::articleStatusTone('ERROR'));
    }

    public function testUnknownToneFallsBackToMutedClasses(): void
    {
        $this->assertSame('bg-border/40 text-text-secondary', Labels::toneClasses('tom-inexistente'));
    }

    public function testArticleStatusBadgeIncludesBothTextAndColorClass(): void
    {
        $badge = Labels::articleStatusBadge('APPROVED');

        $this->assertStringContainsString('Aprovado', $badge);
        $this->assertStringContainsString('text-success', $badge);
    }
}

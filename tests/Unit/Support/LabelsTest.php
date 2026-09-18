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

    /**
     * O card da Produção tem que seguir o MESMO tom da badge: aprovado verde,
     * atenção (bloqueado/falha) vermelho, e todo status cai numa classe de card
     * (nunca vazia — senão o card ficaria sem fundo/borda).
     */
    public function testArticleCardFollowsTheSameToneAsTheBadge(): void
    {
        $this->assertSame('article-card--success', Labels::articleCardTone(Labels::articleStatusTone('APPROVED')));
        $this->assertSame('article-card--success', Labels::articleCardTone(Labels::articleStatusTone('PUBLISHED')));
        $this->assertSame('article-card--danger', Labels::articleCardTone(Labels::articleStatusTone('BLOCKED')));
        $this->assertSame('article-card--danger', Labels::articleCardTone(Labels::articleStatusTone('ERROR')));
        $this->assertSame('article-card--warning', Labels::articleCardTone(Labels::articleStatusTone('REVISION_REQUESTED')));
        $this->assertSame('article-card--info', Labels::articleCardTone(Labels::articleStatusTone('IN_PROGRESS')));
        $this->assertSame('article-card--cyan', Labels::articleCardTone(Labels::articleStatusTone('IN_REVIEW')));
        $this->assertSame('article-card--muted', Labels::articleCardTone(Labels::articleStatusTone('DISCARDED')));
        $this->assertSame('article-card--muted', Labels::articleCardTone('tom-inexistente'));
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

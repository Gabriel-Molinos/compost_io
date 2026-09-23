<?php

declare(strict_types=1);

use App\Support\Icon;
use App\View;

/** Página estática (sem dado de banco) — ver App\Controllers\ComplianceController pro contexto. */

$sectionHeading = static function (string $icon, string $title, ?string $subtitle = null): void {
    echo '<div class="flex items-center gap-2">'
        . '<span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan [&>svg]:h-4 [&>svg]:w-4">' . $icon . '</span>'
        . '<h2 class="font-display text-base font-semibold text-text-primary">' . View::e($title) . '</h2></div>';
    if ($subtitle !== null) {
        echo '<p class="mt-1 text-sm text-text-secondary">' . View::e($subtitle) . '</p>';
    }
};

/** @param list<string> $items */
$checkList = static function (array $items, string $tone = 'success'): void {
    $iconKey = $tone === 'success' ? 'check' : 'close';
    $cls = $tone === 'success' ? 'bg-success/15 text-success' : 'bg-danger/15 text-danger';
    echo '<ul class="mt-3 space-y-2.5">';
    foreach ($items as $item) {
        echo '<li class="flex items-start gap-2.5 text-sm text-text-primary">'
            . '<span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full ' . $cls . ' [&>svg]:h-3 [&>svg]:w-3">' . Icon::nav($iconKey) . '</span>'
            . '<span>' . $item . '</span></li>';
    }
    echo '</ul>';
};
?>
<div class="flex items-start gap-3">
    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan/10 text-cyan"><?= Icon::nav('shield') ?></span>
    <div>
        <h1 class="font-display text-2xl font-bold text-text-primary">Regras de compliance</h1>
        <p class="mt-1 max-w-2xl text-sm text-text-secondary">
            Tudo que um post precisa ter e seguir antes de virar <span class="font-mono text-text-primary">APPROVED</span> —
            leitura obrigatória pra qualquer pessoa que gera ou aprova artigo, não só pra quem
            programou o app. As mesmas regras aqui são as que a IA confere no passo de
            <span class="font-mono text-text-primary">compliance</span> do pipeline; a diferença é que aqui elas estão
            explicadas, não em JSON.
        </p>
    </div>
</div>

<section class="mt-6 article-card article-card--cyan rounded-xl p-5" style="--card-enter: 0ms">
    <?php $sectionHeading(Icon::nav('sources'), 'Elementos obrigatórios de um post', 'Sem isso, o checklist de pré-aprovação não deixa aprovar — a página do artigo mostra exatamente qual item falhou.'); ?>
    <?php $checkList([
        'Título claro, específico, sem clickbait.',
        'Corpo em HTML estruturado: headings, parágrafos curtos, listas quando fizer sentido.',
        'Categoria: exatamente uma das categorias <strong class="font-semibold">já cadastradas</strong> do site — nunca criar categoria nova pra um tema pontual (usar tag, se precisar).',
        'Slug amigável e coerente com o título.',
        '<strong class="font-semibold">3 a 5 links internos</strong> e <strong class="font-semibold">no máximo 2 links externos</strong> no corpo.',
        '<strong class="font-semibold">Extensão mínima de 1500 palavras</strong> — abaixo disso, o checklist reprova.',
        'Autor e imagem destacada — definidos no agendamento, depois da aprovação (não bloqueiam a aprovação em si).',
    ]) ?>
</section>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <section class="article-card article-card--success rounded-xl p-5" style="--card-enter: 60ms">
        <?php $sectionHeading(Icon::nav('check'), 'O artigo deve'); ?>
        <?php $checkList([
            'Trazer informação útil e original pro leitor.',
            'Usar fontes confiáveis quando apresentar dados, estatísticas ou fatos.',
            'Deixar claras afirmações, limitações e condições — nada de deixar subentendido.',
            'Respeitar direitos autorais.',
            'Entregar exatamente o que o título e a meta descrição prometem.',
        ], 'success') ?>
    </section>

    <section class="article-card article-card--danger rounded-xl p-5" style="--card-enter: 90ms">
        <?php $sectionHeading(Icon::nav('close'), 'O artigo não pode'); ?>
        <?php $checkList([
            'Reescrever conteúdo de outro site — precisa ser original.',
            'Trazer informação enganosa ou falsa.',
            'Fazer promessa absoluta ou garantia sem fundamento ("cura definitiva", "garantido").',
            'Usar linguagem sensacionalista.',
            'Existir só pra manipular mecanismo de busca (conteúdo sem propósito real pro leitor).',
            'Repetir palavra-chave artificialmente.',
            'Posicionar link colado a bloco de anúncio.',
        ], 'danger') ?>
    </section>
</div>

<section class="mt-4 article-card rounded-xl p-5" style="--card-enter: 120ms">
    <?php $sectionHeading(Icon::nav('ai'), 'Como isso aparece na hora de revisar', 'Mesmas regras acima, só que já conferidas — pra você não ter que decorar nada.'); ?>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
            <p class="flex items-center gap-2 text-sm font-semibold text-text-primary">
                <span class="rounded-full border border-border bg-surface-2 px-2 py-0.5 text-[10px] font-semibold uppercase text-text-secondary">Checklist</span>
                Mecânico, sempre trava
            </p>
            <p class="mt-2 text-sm text-text-secondary">
                Categoria, extensão mínima e faixa de links são contados direto do artigo salvo —
                não dependem da IA "achar" nada. Se algum item falhar, o botão
                <span class="font-mono text-text-primary">Aprovar artigo</span> fica desabilitado até corrigir.
            </p>
        </div>
        <div>
            <p class="flex items-center gap-2 text-sm font-semibold text-text-primary">
                <span class="rounded-full border border-border bg-surface-2 px-2 py-0.5 text-[10px] font-semibold uppercase text-text-secondary">Compliance (IA)</span>
                Parecer — decisão continua sua
            </p>
            <p class="mt-2 text-sm text-text-secondary">
                A IA lê o artigo contra as regras desta página e aponta
                <span class="rounded-full border border-danger/40 bg-danger/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-danger">bloqueio</span>
                (fere uma regra — mostra o trecho usado como evidência) ou
                <span class="rounded-full border border-warning/40 bg-warning/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-warning">aviso</span>
                (vale revisar). A IA pode errar — por isso um bloqueio nunca trava sozinho, mas também
                não passa despercebido: aprovar com pendência exige marcar
                "revisei e aprovo assim mesmo" antes.
            </p>
        </div>
    </div>
</section>

<section class="mt-4 rounded-xl border border-border bg-surface p-5" style="--card-enter: 150ms">
    <?php $sectionHeading(Icon::nav('globe'), 'Referência oficial', 'As regras acima seguem as políticas de conteúdo do Google AdSense.'); ?>
    <ul class="mt-3 space-y-1.5 text-sm">
        <li><a class="text-cyan hover:text-cyan-bright" href="https://support.google.com/adsense/answer/10502938?hl=pt-BR" target="_blank" rel="noopener">Políticas de qualidade do conteúdo — AdSense</a></li>
        <li><a class="text-cyan hover:text-cyan-bright" href="https://support.google.com/adsense/answer/10008391?hl=pt-BR" target="_blank" rel="noopener">Políticas de programa — AdSense</a></li>
        <li><a class="text-cyan hover:text-cyan-bright" href="https://support.google.com/adsense/answer/48182?hl=pt-BR" target="_blank" rel="noopener">Posicionamento de anúncios — AdSense</a></li>
        <li><a class="text-cyan hover:text-cyan-bright" href="https://support.google.com/adsense/answer/23921?hl=pt-BR" target="_blank" rel="noopener">Conteúdo enganoso — AdSense</a></li>
    </ul>
</section>

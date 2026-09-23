<?php

declare(strict_types=1);

use App\Support\Icon;
use App\View;

/**
 * Página estática (sem dado de banco) — ver App\Controllers\ComplianceController
 * pro contexto. Fonte: docs/editorial/compliance.md + itens de docs/editorial/seo.md
 * que a própria doc marca como "reforço de compliance" (achado do responsável,
 * 2026-09-23: a 1ª versão desta página cobria só compliance.md e ficou rasa —
 * várias regras "firmes" que também decidem se o artigo é aprovável vivem em
 * seo.md e não apareciam aqui).
 */

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

/** @param list<array{0:string,1:string}> $rows rótulo => valor */
$factTable = static function (array $rows): void {
    echo '<table class="mt-3 w-full text-left text-sm">'
        . '<thead class="border-b border-border text-text-muted"><tr>'
        . '<th scope="col" class="px-3 py-2 font-medium">Regra</th>'
        . '<th scope="col" class="px-3 py-2 font-medium">Valor obrigatório</th>'
        . '</tr></thead><tbody class="divide-y divide-border">';
    foreach ($rows as [$label, $value]) {
        echo '<tr><td class="px-3 py-2 text-text-secondary">' . $label . '</td>'
            . '<td class="px-3 py-2 font-mono text-text-primary">' . $value . '</td></tr>';
    }
    echo '</tbody></table>';
};

/** @param list<array{0:string,1:string}> $pairs [ruim, bom] */
$badGood = static function (array $pairs): void {
    echo '<div class="mt-3 space-y-3">';
    foreach ($pairs as [$bad, $good]) {
        echo '<div class="grid gap-2 sm:grid-cols-2">'
            . '<div class="flex items-start gap-2 rounded-lg border border-danger/30 bg-danger/5 px-3 py-2 text-sm">'
            . '<span class="mt-0.5 shrink-0 text-danger">' . Icon::nav('close') . '</span>'
            . '<span class="text-text-secondary">' . $bad . '</span></div>'
            . '<div class="flex items-start gap-2 rounded-lg border border-success/30 bg-success/5 px-3 py-2 text-sm">'
            . '<span class="mt-0.5 shrink-0 text-success">' . Icon::nav('check') . '</span>'
            . '<span class="text-text-secondary">' . $good . '</span></div>'
            . '</div>';
    }
    echo '</div>';
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
            explicadas, com exemplo, não em JSON.
        </p>
    </div>
</div>

<div role="alert" class="mt-6 flex items-start gap-3 rounded-xl border-2 border-warning/60 bg-warning/15 p-4">
    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-warning/20 text-warning [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('alert') ?></span>
    <p class="text-sm text-text-primary">
        <span class="block font-display text-base font-bold text-warning">Lembrete antes de aprovar/agendar</span>
        Confira se o site de destino tem a <strong class="font-semibold">moldura automática de imagens</strong> ativada —
        nem todo site tem essa feature ligada.
    </p>
</div>

<section class="mt-4 rounded-xl border border-border bg-surface p-5" style="--card-enter: 0ms">
    <?php $sectionHeading(Icon::nav('reports'), 'Resumo rápido', 'Os números que decidem se o checklist passa — pra consultar rápido, sem ler tudo de novo.'); ?>
    <?php $factTable([
        ['Extensão do texto', 'no mínimo 1500 palavras'],
        ['Links internos', 'entre 3 e 5'],
        ['Links externos', 'no máximo 2, sempre em nova aba'],
        ['Imagens no corpo', 'cerca de 1 a cada 500 palavras, todas em WebP'],
        ['Parágrafo (texto original)', 'no máximo 2 linhas (por volta de 20 a 25 palavras)'],
        ['Palavra-chave', 'no título/H1 + destacada 1 vez no corpo — sem repetir artificialmente'],
        ['Categoria', 'uma só, já cadastrada no site — nunca criar categoria nova'],
        ['Meta descrição', 'obrigatória, com palavra-chave + chamada pra ação'],
    ]) ?>
</section>

<section class="mt-4 article-card article-card--cyan rounded-xl p-5" style="--card-enter: 30ms">
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

<section class="mt-4 rounded-xl border border-border bg-surface p-5" style="--card-enter: 105ms">
    <?php $sectionHeading(Icon::nav('camera'), 'Imagens', 'Vale tanto pra compliance quanto pra SEO — reforça docs/editorial/seo.md.'); ?>
    <?php $checkList([
        'Todas as imagens em <strong class="font-semibold">formato WebP</strong>.',
        'Imagem destacada e alt text pertinentes ao tema do artigo — não genéricos.',
        'Pelo menos <strong class="font-semibold">1 imagem no corpo a cada 500 palavras, mais ou menos</strong> (um artigo de 1500 palavras tem, na prática, umas 3).',
        'Sempre gerada pela plataforma (Nano Banana) ou de banco com uso liberado — nunca uma imagem "puxada" de outro site.',
    ], 'success') ?>
</section>

<section class="mt-4 rounded-xl border border-border bg-surface p-5" style="--card-enter: 120ms">
    <?php $sectionHeading(Icon::nav('categories'), 'Categorias e conteúdo repetido', 'A categoria errada ou um tema repetido não é só "SEO malfeito" — reforça compliance (evita conteúdo raso/duplicado, ver docs/editorial/seo.md).'); ?>
    <div class="mt-3 space-y-2 text-sm text-text-secondary">
        <p><strong class="font-semibold text-text-primary">Categoria é fixa por site.</strong> Se o tema não encaixa em nenhuma categoria existente, o problema é o tema — não crie categoria nova pra "resolver". Pra agrupar um assunto novo dentro de uma categoria existente, use <strong class="font-semibold text-text-primary">tag</strong>.</p>
        <p><strong class="font-semibold text-text-primary">Canibalização</strong> — se a palavra-chave do artigo já foi usada num artigo anterior do mesmo site, o sistema avisa e o artigo fica em rascunho em vez de ir pra publicação: dois posts competindo pela mesma busca prejudicam os dois, e conteúdo raspado/repetido é justamente o tipo de coisa que a política de qualidade do AdSense mira.</p>
    </div>
</section>

<section class="mt-4 rounded-xl border border-border bg-surface p-5" style="--card-enter: 135ms">
    <?php $sectionHeading(Icon::nav('alert'), 'Erros comuns — exemplo ruim × exemplo bom'); ?>
    <?php $badGood([
        [
            '"Você não vai ACREDITAR no que aconteceu com essa cidade!" — título clickbait, não entrega o tema.',
            '"Chuvas alagam 3 bairros de [cidade]; veja o que abriu hoje" — específico, já entrega do que se trata.',
        ],
        [
            '"Esse chá cura qualquer inflamação, garantido" — promessa absoluta sem fundamento.',
            '"Estudos preliminares associam o chá a menos inflamação, mas resultados variam por pessoa" — deixa clara a limitação.',
        ],
        [
            '"ESCÂNDALO CHOCANTE: veja o que ninguém te conta!!!" — linguagem sensacionalista.',
            '"Relatório aponta irregularidades em [órgão]; entenda o caso" — neutro, factual.',
        ],
        [
            'Repetir "melhor smartphone barato" 8 vezes no texto pra tentar rankear — keyword stuffing.',
            'Usar a expressão 1–2 vezes e variações naturais ("celular custo-benefício", "aparelho de entrada") no resto.',
        ],
        [
            'Colocar um link ancorado bem colado/dentro de onde entra o bloco de anúncio do Google.',
            'Manter espaço entre o texto com link e a área reservada pro anúncio.',
        ],
    ]) ?>
</section>

<section class="mt-4 rounded-xl border border-warning/30 bg-warning/5 p-5" style="--card-enter: 150ms">
    <?php $sectionHeading(Icon::nav('alert'), 'Por que isso importa de verdade'); ?>
    <p class="mt-2 text-sm text-text-secondary">
        Essas regras não são "boa prática" opcional — são as políticas de conteúdo do próprio
        Google AdSense (referências oficiais no fim da página). Artigo fora delas não é só
        "reprovado no app": é o tipo de conteúdo que o Google pode deixar de monetizar, e,
        se for recorrente no site, pode gerar restrição na conta de anúncios inteira — não
        só daquele post. É por isso que compliance trava a aprovação com o mesmo peso que
        um erro técnico, e não como um "detalhe a mais".
    </p>
</section>

<section class="mt-4 article-card rounded-xl p-5" style="--card-enter: 165ms">
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
    <p class="mt-4 text-sm text-text-secondary">
        Se preferir rejeitar em vez de aprovar com pendência, o motivo
        <span class="font-mono text-text-primary">"Problema de compliance"</span> já pré-preenche a justificativa
        com as pendências que a IA apontou — não precisa retranscrever.
    </p>
</section>

<section class="mt-4 rounded-xl border border-border bg-surface p-5" style="--card-enter: 180ms">
    <?php $sectionHeading(Icon::nav('globe'), 'Referência oficial', 'As regras acima seguem as políticas de conteúdo do Google AdSense.'); ?>
    <ul class="mt-3 space-y-1.5 text-sm">
        <li><a class="text-cyan hover:text-cyan-bright" href="https://support.google.com/adsense/answer/10502938?hl=pt-BR" target="_blank" rel="noopener">Políticas de qualidade do conteúdo — AdSense</a></li>
        <li><a class="text-cyan hover:text-cyan-bright" href="https://support.google.com/adsense/answer/10008391?hl=pt-BR" target="_blank" rel="noopener">Políticas de programa — AdSense</a></li>
        <li><a class="text-cyan hover:text-cyan-bright" href="https://support.google.com/adsense/answer/48182?hl=pt-BR" target="_blank" rel="noopener">Posicionamento de anúncios — AdSense</a></li>
        <li><a class="text-cyan hover:text-cyan-bright" href="https://support.google.com/adsense/answer/23921?hl=pt-BR" target="_blank" rel="noopener">Conteúdo enganoso — AdSense</a></li>
    </ul>
</section>

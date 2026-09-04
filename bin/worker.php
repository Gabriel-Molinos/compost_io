<?php

declare(strict_types=1);

/**
 * Worker da fila de IA (ADR-006).
 *
 *   php bin/worker.php
 *
 * Com `QUEUE_DRIVER=sync` (padrão) os jobs rodam inline na própria requisição
 * que os enfileira — não há fila para este worker consumir, ele só avisa e sai.
 *
 * Com `QUEUE_DRIVER=redis`, entra no laço `reserve()`/`execute()` de verdade,
 * bloqueando em BRPOPLPUSH até haver job (ver `RedisQueueDriver`). Sucesso
 * chama `ack()`; falha chama `fail()` (reenfileira até 3 tentativas, depois
 * dead-letter — Fase 9, nunca perde job silenciosamente). Rodar sob
 * `supervisor` em produção; manualmente (este comando) em dev. Se o worker
 * morrer no meio de um job, ele fica visível na lista "em processamento" —
 * recuperar com `bin/queue_requeue_stuck.php`.
 *
 * Handlers: `article.generate`/`article.regenerate` chamam `ArticlePipeline`
 * de verdade (Fase 9.1b) — a criação da linha do artigo (`prepareGenerate()`/
 * `prepareRegenerate()`) já rodou no controller antes de despachar; aqui só
 * roda a parte com IA (`runGenerate()`/`runRegenerate()`). `smoke.echo`
 * continua existindo pra verificação isolada (ver bin/queue_smoke.php).
 *
 * Publicação agendada (Fase 9): a cada ~60s o laço varre
 * `ScheduleService::dueForPublish()` (agendamentos `PENDING` cuja data já
 * chegou) e despacha um `schedule.publish` por um — antes disso, o envio ao
 * WordPress dependia de alguém lembrar de clicar o botão na hora certa.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Env;
use App\Queue\ArticleJobHandlers;
use App\Queue\Job;
use App\Queue\Queue;
use App\Queue\RetryPolicy;
use App\Queue\RetryRunner;
use App\Queue\ScheduleJobHandlers;
use App\Services\ArticleService;
use App\Services\CostBudgetService;
use App\Services\GoalService;
use App\Services\Pipeline\ArticlePipeline;
use App\Services\ReportService;
use App\Services\ScheduleService;
use App\Services\SiteService;
use App\Services\WordPressPublishService;

Env::load(dirname(__DIR__) . '/.env');

// Mesma linha que public/index.php já tinha — faltava aqui. Sem isso, todo
// date()/DateTimeImmutable('now') deste processo roda no fuso padrão do
// servidor (UTC), não no do site (APP_TIMEZONE) — "sempre 9h" do agendamento
// automático viraria 9h UTC (6h em São Paulo), e o recorte "mês atual" da
// geração automática podia cair no mês errado perto da virada.
date_default_timezone_set(Env::get('APP_TIMEZONE') ?: 'America/Sao_Paulo');

$driverName = Env::get('QUEUE_DRIVER', 'sync');

if ($driverName === 'sync' || $driverName === null) {
    fwrite(STDERR, "QUEUE_DRIVER=sync — os jobs rodam inline; não há fila para consumir.\n");
    fwrite(STDERR, "Configure QUEUE_DRIVER=redis para usar este worker.\n");
    exit(0);
}

$queue = new Queue();

// Rodando em segundo plano: backoff longo entre tentativas (30s→2min→10min em
// produção) — diferente do backoff curto que o pipeline usa quando roda
// inline dentro de uma requisição HTTP (ver ProductionController).
$pipeline = new ArticlePipeline(retry: new RetryRunner(RetryPolicy::default()));
ArticleJobHandlers::register($queue, $pipeline, new ArticleService());

$scheduleService = new ScheduleService();
ScheduleJobHandlers::register($queue, new WordPressPublishService(), $scheduleService);

// Geração automática (pedido do responsável 2026-09-04): 1 rascunho por dia,
// por site ativo, sozinho — sem ninguém clicar "Gerar rascunho" (que continua
// existindo à parte). Reaproveita $pipeline/$queue já registrados acima pro
// job 'article.generate', igual ProductionController::generate().
$articleService = new ArticleService();
$goalService = new GoalService();
$siteService = new SiteService();
$reportService = new ReportService();
$costBudgetService = new CostBudgetService();

$queue->register('smoke.echo', function (Job $job): void {
    $message = $job->payload['message'] ?? '(sem mensagem)';
    echo "[smoke.echo] job {$job->id}: {$message}\n";
});

fwrite(STDERR, "Worker no ar (driver: {$queue->driverName()}). Aguardando jobs...\n");

$lastScheduleScan = 0;
const SCHEDULE_SCAN_INTERVAL = 60; // segundos

$lastAutoGenScan = 0;
const AUTO_GEN_SCAN_INTERVAL = 300; // 5 min — granularidade de sobra pra "já gerou hoje?"

// Um agendamento continua `PENDING` (logo, aparece em dueForPublish()) até o
// job dele terminar de verdade — sucesso (PUBLISHED) ou dead-letter (FAILED,
// via ScheduleJobHandlers). Sem essa guarda em memória, um job ainda em
// retry na próxima varredura (60s) seria despachado DE NOVO — dois jobs
// concorrentes podem passar pela checagem de status do WordPressPublishService
// antes de qualquer um confirmar, criando post duplicado no WP. Vale só
// durante a vida deste processo — reinício reavalia do zero (o job em si não
// some: fica em processingKey, recuperável com bin/queue_requeue_stuck.php).
$dispatchedScheduleIds = [];

while (true) {
    if (time() - $lastScheduleScan >= SCHEDULE_SCAN_INTERVAL) {
        $lastScheduleScan = time();
        try {
            $due = array_filter(
                $scheduleService->dueForPublish(),
                static fn (array $s): bool => !isset($dispatchedScheduleIds[$s['id']])
            );
            foreach ($due as $s) {
                $dispatchedScheduleIds[$s['id']] = true;
                $queue->dispatch(new Job('schedule.publish', [
                    'schedule_id' => $s['id'],
                    'article_id'  => $s['article_id'],
                    'site_id'     => $s['site_id'],
                ]));
            }
            if ($due !== []) {
                fwrite(STDERR, count($due) . " agendamento(s) vencido(s) despachado(s).\n");
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Falha ao varrer agendamentos vencidos: ' . $e->getMessage() . "\n");
        }
    }

    if (time() - $lastAutoGenScan >= AUTO_GEN_SCAN_INTERVAL) {
        $lastAutoGenScan = time();
        try {
            $period = date('Y-m');
            $generated = 0;
            foreach ($siteService->all() as $site) {
                $siteId = (int) $site['id'];
                if ((int) $site['is_active'] !== 1 || $articleService->hasAutoGeneratedToday($siteId)) {
                    continue;
                }

                // Trava de segurança: orçamento de IA do mês já estourado pra este
                // site (mesmo cálculo mostrado na Visão Geral) — pula hoje, sem
                // desligar a rotina pros dias seguintes. Geração automática roda
                // sem ninguém olhando, então merece essa guarda a mais que o
                // clique manual não tinha.
                $costBudget = $costBudgetService->evaluate($reportService->currentSpend($siteId, $period));
                if ($costBudget !== null && $costBudget['over']) {
                    fwrite(STDERR, "Site {$siteId}: geração automática pulada hoje (orçamento de IA estourado).\n");
                    continue;
                }

                $goal = $goalService->findByPeriod($siteId, $period);
                $goalId = $goal !== null ? (int) $goal['id'] : null;
                $categoryId = $goalId !== null
                    ? $goalService->mostUnderTargetCategory($siteId, $goalId, $period)
                    : null;

                $articleId = $articleService->createWithDailyLimit(
                    $siteId,
                    ArticleService::DAILY_LIMIT,
                    fn () => $pipeline->prepareGenerate($siteId, $goalId, 'AUTO'),
                );
                $queue->dispatch(new Job('article.generate', [
                    'article_id'  => $articleId,
                    'site_id'     => $siteId,
                    'goal_id'     => $goalId,
                    'category_id' => $categoryId,
                ]));
                $generated++;
            }
            if ($generated > 0) {
                fwrite(STDERR, "{$generated} geração(ões) automática(s) diária(s) despachada(s).\n");
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Falha ao rodar a geração automática diária: ' . $e->getMessage() . "\n");
        }
    }

    try {
        $job = $queue->reserve();
    } catch (\Throwable $e) {
        fwrite(STDERR, 'Falha ao reservar job: ' . $e->getMessage() . "\n");
        sleep(1);
        continue;
    }

    if ($job === null) {
        continue; // timeout do reserve() sem job disponível — volta a esperar
    }

    try {
        $queue->execute($job);
        $queue->ack($job);
    } catch (\App\Services\Pipeline\PipelineException $e) {
        // Falha definitiva do PIPELINE (já esgotou os retries internos de
        // step — RetryRunner) — o handler já marcou o artigo como ERROR
        // (ArticleJobHandlers). Reenfileirar rodaria a IA de novo do zero
        // (custo real) por algo que já está tratado; ack() encerra o job
        // aqui, sem retry nem dead-letter.
        fwrite(STDERR, "Job {$job->id} ({$job->type}) falhou (pipeline, já tratado): " . $e->getMessage() . "\n");
        $queue->ack($job);
    } catch (\Throwable $e) {
        // Qualquer outra falha (bug, infra) é candidata a retry de verdade —
        // fail() reenfileira até 3 tentativas, depois dead-letter.
        fwrite(STDERR, "Job {$job->id} ({$job->type}) falhou: " . $e->getMessage() . "\n");
        $queue->fail($job, $e->getMessage());
    }
}

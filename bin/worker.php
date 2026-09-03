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
use App\Services\Pipeline\ArticlePipeline;
use App\Services\ScheduleService;
use App\Services\WordPressPublishService;

Env::load(dirname(__DIR__) . '/.env');

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

$queue->register('smoke.echo', function (Job $job): void {
    $message = $job->payload['message'] ?? '(sem mensagem)';
    echo "[smoke.echo] job {$job->id}: {$message}\n";
});

fwrite(STDERR, "Worker no ar (driver: {$queue->driverName()}). Aguardando jobs...\n");

$lastScheduleScan = 0;
const SCHEDULE_SCAN_INTERVAL = 60; // segundos

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

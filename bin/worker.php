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
use App\Integrations\WordPress\ExternalLinkVerifier;
use App\Queue\ArticleJobHandlers;
use App\Queue\Job;
use App\Queue\Queue;
use App\Queue\RetryPolicy;
use App\Queue\RetryRunner;
use App\Queue\ScheduleJobHandlers;
use App\Services\ArticleNoteService;
use App\Services\ArticleService;
use App\Services\BacklinkSuggestionService;
use App\Services\CostBudgetService;
use App\Services\GoalService;
use App\Services\NotificationService;
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
$notificationService = new NotificationService();

$queue->register('smoke.echo', function (Job $job): void {
    $message = $job->payload['message'] ?? '(sem mensagem)';
    echo "[smoke.echo] job {$job->id}: {$message}\n";
});

fwrite(STDERR, "Worker no ar (driver: {$queue->driverName()}). Aguardando jobs...\n");

$lastScheduleScan = 0;
const SCHEDULE_SCAN_INTERVAL = 60; // segundos

$lastAutoGenScan = 0;
const AUTO_GEN_SCAN_INTERVAL = 300; // 5 min — granularidade de sobra pra "já gerou hoje?"

// Revarredura de link rot (achado real 2026-09-10): a checagem de link só
// rodava na geração, na edição manual e na publicação — depois de publicado,
// nada revisitava. Um link real na publicação pode morrer meses depois.
// Não edita o post ao vivo sozinho (reescrever conteúdo já publicado sem
// humano decidir é destrutivo demais) — só grava um aviso pra alguém agir.
//
// $lastLinkRotScan começa em time() (não 0!) — achado real 2026-09-28: com 0,
// "tempo desde a última vez" já nasce enorme e as 3 varreduras diárias (link
// rot + backlink retroativo + sync de post apagado, todas abaixo) disparavam
// JUNTAS na primeira volta do laço, TODA vez que o worker sobe — inclusive
// hoje mesmo, travando a fila (jobs de geração automática do dia parados)
// por vários minutos sem logar nada nesse meio tempo, parecendo travado sem
// estar. Começar em time() faz a 1ª varredura de verdade só depois de um
// intervalo completo — correto pra manutenção diária, que não precisa (e não
// deve) rodar de novo a cada reinício do worker.
$lastLinkRotScan = time();
const LINK_ROT_SCAN_INTERVAL = 86400; // 24h
const LINK_ROT_BATCH_PER_SITE = 20; // por site a cada ciclo — evita travar o worker num site com muito conteúdo
$linkRotOffsets = []; // siteId => próximo offset — round-robin em memória entre ciclos deste processo

// Um agendamento continua `PENDING` (logo, aparece em dueForPublish()) até o
// job dele terminar de verdade — sucesso (PUBLISHED) ou dead-letter (FAILED,
// via ScheduleJobHandlers). Sem essa guarda em memória, um job ainda em
// retry na próxima varredura (60s) seria despachado DE NOVO — dois jobs
// concorrentes podem passar pela checagem de status do WordPressPublishService
// antes de qualquer um confirmar, criando post duplicado no WP. Vale só
// durante a vida deste processo — reinício reavalia do zero (o job em si não
// some: fica em processingKey, recuperável com bin/queue_requeue_stuck.php).
$dispatchedScheduleIds = [];

// Sugestão de link interno retroativo (achado real 2026-09-10): o passo
// `writing` já sabe linkar um artigo novo pros antigos, mas nunca o
// contrário — artigo antigo nunca ganhava um link de volta pro artigo novo
// que acabou de publicar. Varre 1 artigo publicado ainda não conferido por
// site a cada ciclo (throttle — cada varredura é uma chamada de IA de
// verdade, custo real). Nunca aplica sozinho: só grava sugestão pro
// Redator-Chefe aprovar na Central de Links (`BacklinkSuggestionService`).
$lastBacklinkScan = time(); // ver comentário em $lastLinkRotScan acima — mesmo achado 2026-09-28
const BACKLINK_SCAN_INTERVAL = 86400; // 24h

// Sincronização de post apagado por fora (achado real 2026-09-14): um post
// marcado PUBLISHED aqui pode ir pra lixeira direto no WordPress, sem passar
// pelo botão "Retirar" — o dashboard nunca ficava sabendo, e esse artigo
// continuava sendo sugerido como link interno (painel do editor, Central de
// Links) mesmo morto. Corrige o estado local pra bater com a realidade —
// mesmo efeito de `WordPressPublishService::retract()`, sem apagar de novo.
$lastPublishSyncScan = time(); // ver comentário em $lastLinkRotScan acima — mesmo achado 2026-09-28
const PUBLISH_SYNC_SCAN_INTERVAL = 86400; // 24h

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

                // Sem meta, sem geração automática (pedido do responsável 2026-09-25): usa a do
                // mês atual, senão a do próximo; se não há nenhuma, avisa a equipe (1x por dia)
                // e pula — volta sozinha na próxima varredura depois que alguém cadastrar a meta.
                $plan = $goalService->findForAutoGeneration($siteId);
                if ($plan === null) {
                    $nextPeriod = (new DateTimeImmutable('first day of next month'))->format('m/Y');
                    $notificationService->notifySiteTeamOncePerDay(
                        $siteId,
                        NotificationService::TYPE_ATTENTION,
                        'Geração automática pausada: falta a meta',
                        'O site "' . $site['name'] . '" não tem meta para ' . date('m/Y') . ' nem para ' . $nextPeriod
                            . '. A geração automática só volta quando você cadastrar uma meta.',
                        '/sites/' . $siteId . '/goals/new',
                    );
                    fwrite(STDERR, "Site {$siteId}: geração automática pulada (sem meta no mês atual nem no próximo).\n");
                    continue;
                }
                $goalId = (int) $plan['goal']['id'];
                $categoryId = $goalService->mostUnderTargetCategory($siteId, $goalId, $plan['period'], $plan['is_next_month']);

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

    if (time() - $lastLinkRotScan >= LINK_ROT_SCAN_INTERVAL) {
        $lastLinkRotScan = time();
        // Log de início (achado real 2026-09-28): esta varredura pode demorar (1 HTTP
        // por link externo, de todos os sites) e roda ANTES do laço voltar a pegar job
        // novo da fila — sem isto, minutos de silêncio pareciam worker travado.
        fwrite(STDERR, "Iniciando revarredura de link rot...\n");
        try {
            $linkVerifier = new ExternalLinkVerifier();
            $notesService = new ArticleNoteService();
            $rotFound = 0;
            foreach ($siteService->all() as $site) {
                $siteId = (int) $site['id'];
                $ids = $articleService->publishedIds($siteId);
                if ($ids === []) {
                    continue;
                }

                $offset = $linkRotOffsets[$siteId] ?? 0;
                if ($offset >= count($ids)) {
                    $offset = 0;
                }
                $batch = array_slice($ids, $offset, LINK_ROT_BATCH_PER_SITE);
                $linkRotOffsets[$siteId] = $offset + count($batch) >= count($ids) ? 0 : $offset + count($batch);

                foreach ($batch as $articleId) {
                    $version = $articleService->latestVersion($articleId);
                    if ($version === null) {
                        continue;
                    }

                    // Conteúdo já publicado não tinha link morto sobrevivendo
                    // (a checagem na publicação já removia) — qualquer `unwrapped`
                    // agora é morte nova desde então, isto é, link rot de verdade.
                    $result = $linkVerifier->verify((string) $version['content']);
                    if ($result['unwrapped'] === 0) {
                        continue;
                    }

                    $note = $notesService->forArticle($articleId)['pipeline'] ?? [];
                    $note['link_rot_detected_at'] = date('Y-m-d H:i:s');
                    $note['link_rot_dead_urls'] = $result['dead'];
                    $notesService->save($articleId, 'pipeline', $note);
                    $rotFound++;
                }
            }
            if ($rotFound > 0) {
                fwrite(STDERR, "{$rotFound} artigo(s) publicado(s) com link rot detectado (link que morreu depois de publicado).\n");
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Falha na revarredura de link rot: ' . $e->getMessage() . "\n");
        }
    }

    if (time() - $lastBacklinkScan >= BACKLINK_SCAN_INTERVAL) {
        $lastBacklinkScan = time();
        // Log de início — ver comentário na varredura de link rot acima: esta faz uma
        // chamada de IA de verdade por site, também antes de voltar a pegar job da fila.
        fwrite(STDERR, "Iniciando varredura de sugestão de link interno retroativo...\n");
        try {
            $notesService = new ArticleNoteService();
            $backlinkService = new BacklinkSuggestionService();
            $scanned = 0;
            foreach ($siteService->all() as $site) {
                $siteId = (int) $site['id'];
                foreach ($articleService->publishedIds($siteId) as $articleId) {
                    $note = $notesService->forArticle($articleId)['pipeline'] ?? [];
                    if (!empty($note['backlink_scan_done'])) {
                        continue;
                    }
                    $backlinkService->scan($articleId, $siteId);
                    $scanned++;
                    break; // só 1 por site a cada ciclo — cada varredura é uma chamada de IA de verdade
                }
            }
            if ($scanned > 0) {
                fwrite(STDERR, "{$scanned} artigo(s) conferido(s) pra sugestão de link interno retroativo.\n");
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Falha na varredura de sugestão de link retroativo: ' . $e->getMessage() . "\n");
        }
    }

    if (time() - $lastPublishSyncScan >= PUBLISH_SYNC_SCAN_INTERVAL) {
        $lastPublishSyncScan = time();
        // Log de início — ver comentário na varredura de link rot acima.
        fwrite(STDERR, "Iniciando sincronização de posts apagados direto no WordPress...\n");
        try {
            $publishService = new \App\Services\WordPressPublishService();
            $fixed = 0;
            foreach ($siteService->all() as $site) {
                try {
                    $fixed += $publishService->syncPublishedState((int) $site['id']);
                } catch (\App\Integrations\WordPress\WordPressException) {
                    continue; // site sem WordPress conectado, ou falha de rede — pula, tenta de novo no próximo ciclo
                }
            }
            if ($fixed > 0) {
                fwrite(STDERR, "{$fixed} artigo(s) corrigido(s) pra APROVADO (post tinha sido apagado/despublicado direto no WordPress).\n");
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Falha na sincronização de posts apagados: ' . $e->getMessage() . "\n");
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

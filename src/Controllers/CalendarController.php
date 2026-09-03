<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ScheduleService;
use App\Support\Csrf;
use App\View;
use DateTimeImmutable;
use Exception;
use RuntimeException;
use Throwable;

/**
 * Calendário mensal dos agendamentos do site (Fase 7.6). Visualização, mais
 * reagendar por arrastar-e-soltar direto no calendário (Fase 9) — só a data,
 * mantendo autor/imagem/horário; o formulário completo continua na tela do
 * artigo (autor, imagem, hora).
 */
final class CalendarController extends Controller
{
    public function index(string $siteId): void
    {
        $site = $this->requireSite($siteId);

        $month = $this->month($_GET['month'] ?? null);
        $start = $month->modify('first day of this month')->setTime(0, 0);
        $end = $start->modify('first day of next month');

        $schedules = (new ScheduleService())->betweenForSite(
            (int) $site['id'],
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        );

        // Agrupa por dia (Y-m-d).
        $byDay = [];
        foreach ($schedules as $s) {
            $byDay[substr((string) $s['scheduled_date'], 0, 10)][] = $s;
        }

        // Grade começando na segunda-feira.
        $gridStart = $start->modify('-' . (((int) $start->format('N')) - 1) . ' days');
        $weeks = [];
        $cursor = $gridStart;
        for ($w = 0; $w < 6; $w++) {
            $row = [];
            for ($d = 0; $d < 7; $d++) {
                $row[] = $cursor;
                $cursor = $cursor->modify('+1 day');
            }
            $weeks[] = $row;
        }

        View::render('sites/calendar/index', [
            'title'     => 'Calendário · ' . $site['name'],
            'site'      => $site,
            'month'     => $start,
            'prevMonth' => $start->modify('-1 month')->format('Y-m'),
            'nextMonth' => $start->modify('+1 month')->format('Y-m'),
            'weeks'     => $weeks,
            'byDay'     => $byDay,
            'todayYmd'  => (new DateTimeImmutable('now'))->format('Y-m-d'),
        ]);
    }

    /**
     * Endpoint interno (JSON, docs/technical/requisitos.md §68) consumido pelo
     * `assets/js/calendar.js` — arrastar um agendamento pra outro dia. CSRF
     * verificado via `Csrf::check()` (não `verify()`: aqui a resposta é JSON,
     * não um redirect).
     */
    public function reschedule(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        header('Content-Type: application/json; charset=utf-8');

        if (!Csrf::check($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'Sessão expirada — recarregue a página.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $articleId = (int) ($_POST['article_id'] ?? 0);
        $newDate = (string) ($_POST['new_date'] ?? '');

        try {
            (new ScheduleService())->rescheduleDate($articleId, (int) $site['id'], $newDate);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        } catch (RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Erro inesperado ao reagendar.'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function month(mixed $raw): DateTimeImmutable
    {
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}$/', $raw)) {
            try {
                return new DateTimeImmutable($raw . '-01');
            } catch (Exception) {
                // cai no mês atual
            }
        }

        return new DateTimeImmutable('now');
    }
}

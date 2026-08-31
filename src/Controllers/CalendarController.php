<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ScheduleService;
use App\View;
use DateTimeImmutable;
use Exception;

/**
 * Calendário mensal dos agendamentos do site (Fase 7.6). Somente visualização —
 * reagendar/cancelar continuam na tela do artigo.
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

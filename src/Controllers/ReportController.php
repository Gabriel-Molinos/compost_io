<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ReportService;
use App\View;
use DateTimeImmutable;
use Exception;

/**
 * Relatórios do site (RF-013, Fase 8). Visível ao Redator-Chefe com acesso ao site.
 */
final class ReportController extends Controller
{
    public function index(string $siteId): void
    {
        $site = $this->requireSite($siteId);

        $period = $this->period($_GET['month'] ?? null);
        $current = new DateTimeImmutable($period . '-01');
        $prevPeriod = $current->modify('-1 month')->format('Y-m');

        $service = new ReportService();
        $report = $service->monthly((int) $site['id'], $period);
        $previous = $service->monthly((int) $site['id'], $prevPeriod);

        View::render('sites/reports/index', [
            'title'      => 'Relatórios · ' . $site['name'],
            'site'       => $site,
            'report'     => $report,
            'comparison' => $service->compare($report, $previous),
            'trend'      => $service->trend((int) $site['id'], $period),
            'prevMonthName' => $this->monthLabel($current->modify('-1 month')),
            'monthName'  => $this->monthLabel($current),
            'prevMonth'  => $prevPeriod,
            'nextMonth'  => $current->modify('+1 month')->format('Y-m'),
        ]);
    }

    private function period(mixed $raw): string
    {
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}$/', $raw)) {
            try {
                new DateTimeImmutable($raw . '-01');
                return $raw;
            } catch (Exception) {
                // cai no mês atual
            }
        }

        return (new DateTimeImmutable('now'))->format('Y-m');
    }

    private function monthLabel(DateTimeImmutable $d): string
    {
        $meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

        return $meses[(int) $d->format('n')] . ' ' . $d->format('Y');
    }
}

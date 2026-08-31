<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Integrations\AIException;
use App\Services\AuthService;
use App\Services\IntelligenceService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\View;
use Throwable;

/**
 * Centro de Inteligência Editorial do site (RF-014, fluxo-editorial §33).
 * Visível ao Redator-Chefe com acesso ao site. A análise é gerada sob demanda
 * (cada geração é uma chamada paga ao Gemini).
 */
final class IntelligenceController extends Controller
{
    /** Teto de gerações por dia por site enquanto não há limite de custo (§95). */
    private const DAILY_LIMIT = 10;

    public function index(string $siteId): void
    {
        $site = $this->requireSite($siteId);

        View::render('sites/intelligence/index', [
            'title'   => 'Inteligência · ' . $site['name'],
            'site'    => $site,
            'insight' => (new IntelligenceService())->latest((int) $site['id']),
        ]);
    }

    public function generate(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        $service = new IntelligenceService();

        if ($service->countLast24h((int) $site['id']) >= self::DAILY_LIMIT) {
            Session::flash('error', 'Limite de ' . self::DAILY_LIMIT . ' análises por dia neste site atingido. Tente amanhã.');
            Http::redirect('/sites/' . $site['id'] . '/intelligence');
            return;
        }

        // A chamada ao Gemini pode passar de 1 min.
        set_time_limit(300);
        @ini_set('max_execution_time', '300');

        try {
            $service->generate((int) $site['id'], AuthService::id());
        } catch (AIException $e) {
            Session::flash('error', 'Gemini: ' . $e->getMessage());
            Http::redirect('/sites/' . $site['id'] . '/intelligence');
            return;
        } catch (Throwable $e) {
            Session::flash('error', 'Não foi possível gerar a análise: ' . $e->getMessage());
            Http::redirect('/sites/' . $site['id'] . '/intelligence');
            return;
        }

        Session::flash('success', 'Análise gerada.');
        Http::redirect('/sites/' . $site['id'] . '/intelligence');
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ArticleReviewService;
use App\Services\AuthService;
use App\Services\EditorialMemoryService;
use App\Services\FeedbackService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\View;

/**
 * Memória editorial curada (Fase 9): CRUD simples, sempre por decisão humana
 * — nunca a IA escrevendo sua própria memória (Regra de não-invenção, §58).
 */
final class EditorialMemoryController extends Controller
{
    private EditorialMemoryService $memory;
    private FeedbackService $feedback;

    public function __construct()
    {
        $this->memory = new EditorialMemoryService();
        $this->feedback = new FeedbackService();
    }

    public function index(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        $promoted = $this->memory->promotedFeedbackIds((int) $site['id']);

        View::render('sites/memory/index', [
            'title'            => 'Memória editorial · ' . $site['name'],
            'site'             => $site,
            'lessons'          => $this->memory->allForSite((int) $site['id']),
            'recentFeedback'   => array_filter(
                $this->feedback->recentForSite((int) $site['id'], 8),
                static fn (array $f): bool => !in_array((int) $f['id'], $promoted, true),
            ),
        ]);
    }

    /** Escrever uma lição do zero. */
    public function store(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        $lesson = trim((string) ($_POST['lesson'] ?? ''));
        if ($lesson === '') {
            Session::flash('error', 'Escreva a lição antes de salvar.');
        } else {
            $this->memory->create((int) $site['id'], $lesson, AuthService::id());
            Session::flash('success', 'Lição adicionada à memória editorial.');
        }

        Http::redirect('/sites/' . $site['id'] . '/memory');
    }

    /** Promove uma rejeição existente a lição duradoura, com 1 clique — texto pré-preenchido, editável depois. */
    public function promote(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
        $feedback = $feedbackId > 0 ? $this->feedback->findForSite((int) $site['id'], $feedbackId) : null;

        if ($feedback === null) {
            Session::flash('error', 'Feedback não encontrado.');
        } else {
            $reason = ArticleReviewService::REJECT_REASONS[$feedback['reason']] ?? (string) $feedback['reason'];
            $lesson = '[' . $reason . '] ' . trim((string) $feedback['justification']);
            $this->memory->create((int) $site['id'], $lesson, AuthService::id(), (int) $feedback['id']);
            Session::flash('success', 'Feedback promovido a lição — pode editar o texto abaixo.');
        }

        Http::redirect('/sites/' . $site['id'] . '/memory');
    }

    public function update(string $siteId, string $memoryId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $lesson = $this->memory->find((int) $site['id'], (int) $memoryId) ?? $this->notFound();

        $text = trim((string) ($_POST['lesson'] ?? ''));
        if ($text === '') {
            Session::flash('error', 'A lição não pode ficar vazia.');
        } else {
            $this->memory->update((int) $lesson['id'], $text);
            Session::flash('success', 'Lição atualizada.');
        }

        Http::redirect('/sites/' . $site['id'] . '/memory');
    }

    public function toggle(string $siteId, string $memoryId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $lesson = $this->memory->find((int) $site['id'], (int) $memoryId) ?? $this->notFound();

        $this->memory->setActive((int) $lesson['id'], !(bool) $lesson['active']);
        Session::flash('success', (bool) $lesson['active'] ? 'Lição desativada.' : 'Lição reativada.');

        Http::redirect('/sites/' . $site['id'] . '/memory');
    }

    public function destroy(string $siteId, string $memoryId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $lesson = $this->memory->find((int) $site['id'], (int) $memoryId) ?? $this->notFound();

        $this->memory->delete((int) $lesson['id']);
        Session::flash('success', 'Lição removida.');
        Http::redirect('/sites/' . $site['id'] . '/memory');
    }
}

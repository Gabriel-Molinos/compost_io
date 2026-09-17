<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\PlatformFeedbackService;
use App\Services\SiteService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\View;

final class FeedbackController extends Controller
{
    private PlatformFeedbackService $feedback;

    public function __construct()
    {
        $this->feedback = new PlatformFeedbackService();
    }

    public function index(): void
    {
        $user = AuthService::user();
        $isAdmin = AuthService::isAdmin();
        $sites = new SiteService();

        View::render('feedback/index', [
            'title'    => 'Feedback',
            'isAdmin'  => $isAdmin,
            'sites'    => $isAdmin ? $sites->all() : $sites->forUser((int) $user['id']),
            'entries'  => $isAdmin ? $this->feedback->listAll() : $this->feedback->listForUser((int) $user['id']),
        ]);
    }

    public function store(): void
    {
        Csrf::verify();

        $message = trim((string) ($_POST['message'] ?? ''));
        if ($message === '') {
            Session::flash('error', 'Escreva alguma coisa antes de enviar.');
            Http::redirect('/feedback');
            return;
        }

        $rating = ($_POST['rating'] ?? '') !== '' ? (int) $_POST['rating'] : null;
        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            $rating = null;
        }

        $siteId = ($_POST['site_id'] ?? '') !== '' ? (int) $_POST['site_id'] : null;
        if ($siteId !== null && !AuthService::canAccessSite($siteId)) {
            $siteId = null;
        }

        $this->feedback->create((int) AuthService::id(), $siteId, $rating, $message);

        $author = (string) (AuthService::user()['name'] ?? 'Alguém');
        (new NotificationService())->notifyAdmins(
            NotificationService::TYPE_FEEDBACK,
            'Novo feedback sobre o COMPOST',
            "{$author} avaliou a plataforma.",
            '/feedback'
        );

        Session::flash('success', 'Feedback enviado — valeu por avaliar o COMPOST!');
        Http::redirect('/feedback');
    }

    public function markReviewed(string $id): void
    {
        Csrf::verify();
        $this->feedback->markReviewed((int) $id, (int) AuthService::id());
        Http::redirect('/feedback');
    }
}

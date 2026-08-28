<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Integrations\WordPress\WordPressException;
use App\Services\WordPressConnectionService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Session;
use App\Support\Validator;
use App\View;

/**
 * Conexão WordPress de um site (RF-016 — somente ADMIN, guard de rota).
 * Fatia 7.1 da Fase 7.
 */
final class WordPressConnectionController extends Controller
{
    private WordPressConnectionService $connections;

    public function __construct()
    {
        $this->connections = new WordPressConnectionService();
    }

    public function edit(string $siteId): void
    {
        $site = $this->requireSite($siteId);

        $this->form($site, $this->connections->forSite((int) $site['id']), []);
    }

    public function update(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        $data = [
            'wordpress_url' => Http::input('wordpress_url'),
            'username'      => Http::input('username'),
            'app_password'  => (string) ($_POST['app_password'] ?? ''),
        ];

        $errors = $this->validate($data, $this->connections->hasCredential((int) $site['id']));
        if ($errors !== []) {
            http_response_code(422);
            $this->form($site, [
                'url'        => $data['wordpress_url'],
                'username'   => $data['username'],
                'status'     => 'UNVERIFIED',
                'configured' => $this->connections->hasCredential((int) $site['id']),
                'last_verified_at' => null,
            ], $errors);
            return;
        }

        $this->connections->save(
            (int) $site['id'],
            $data['wordpress_url'],
            $data['username'],
            $data['app_password'] !== '' ? $data['app_password'] : null,
        );

        Session::flash('success', 'Conexão WordPress salva.');
        Http::redirect('/sites/' . $site['id'] . '/wordpress');
    }

    public function test(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        try {
            $me = $this->connections->client((int) $site['id'])->verifyConnection();
            $this->connections->markVerified((int) $site['id'], true);
            Session::flash('success', sprintf(
                'Conexão OK — autenticado como “%s” (WordPress user #%s).',
                (string) ($me['name'] ?? '?'),
                (string) ($me['id'] ?? '?'),
            ));
        } catch (WordPressException $e) {
            $this->connections->markVerified((int) $site['id'], false);
            Session::flash('error', 'Falha no teste: ' . $e->getMessage());
        }

        Http::redirect('/sites/' . $site['id'] . '/wordpress');
    }

    public function destroy(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        $this->connections->delete((int) $site['id']);
        Session::flash('success', 'Credencial WordPress removida.');
        Http::redirect('/sites/' . $site['id'] . '/wordpress');
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    private function validate(array $data, bool $hasCredential): array
    {
        $errors = (new Validator($data, [
            'wordpress_url' => ['required', 'max:255'],
            'username'      => ['required', 'max:191'],
            'app_password'  => ['max:255'],
        ], [
            'wordpress_url' => 'URL do WordPress',
            'username'      => 'Usuário',
            'app_password'  => 'Application Password',
        ]))->errors();

        if (!isset($errors['wordpress_url'])) {
            $url = $data['wordpress_url'];
            if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
                $errors['wordpress_url'] = 'Informe uma URL completa, incluindo https://.';
            }
        }

        if (!$hasCredential && trim((string) $data['app_password']) === '') {
            $errors['app_password'] = 'Informe a Application Password para criar a conexão.';
        }

        return $errors;
    }

    /** @param array<string,mixed> $site @param array<string,mixed> $connection @param array<string,string> $errors */
    private function form(array $site, array $connection, array $errors): void
    {
        View::render('sites/wordpress/edit', [
            'title'      => 'WordPress · ' . $site['name'],
            'site'       => $site,
            'connection' => $connection,
            'errors'     => $errors,
        ]);
    }
}

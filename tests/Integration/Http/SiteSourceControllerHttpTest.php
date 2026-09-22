<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use App\Database\Connection;
use PDOException;

/**
 * `SiteSourceController` via HTTP real (servidor embutido do PHP, ver
 * `HttpServerTestCase`) — Controller + rota + CSRF + auth juntos, como um
 * usuário/navegador bateria de verdade. Site "Gavsy" (id 2, ver memória do
 * projeto), fonte de teste com URL claramente marcada e removida no
 * `tearDown()`.
 */
final class SiteSourceControllerHttpTest extends HttpServerTestCase
{
    private const TEST_SITE_ID = 2;
    private const TEST_URL = 'https://exemplo-teste-compost.invalid/pagina-de-teste';

    protected function setUp(): void
    {
        try {
            Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }
    }

    protected function tearDown(): void
    {
        Connection::get()->prepare('DELETE FROM site_sources WHERE site_id = :s AND url = :u')
            ->execute(['s' => self::TEST_SITE_ID, 'u' => self::TEST_URL]);
    }

    public function testStoreCreatesSourceAndRedirectsBackToSourcesPage(): void
    {
        $session = $this->forgeAdminSession();

        $response = $this->httpPost('/sites/' . self::TEST_SITE_ID . '/sources', [
            '_token' => $session['token'],
            'url'    => self::TEST_URL,
            'note'   => 'criado pelo SiteSourceControllerHttpTest',
        ], $session['cookie']);

        $this->assertSame(302, $response['status']);
        $this->assertSame('/sites/' . self::TEST_SITE_ID . '/sources', $response['headers']['location'] ?? null);

        $stmt = Connection::get()->prepare('SELECT id, note FROM site_sources WHERE site_id = :s AND url = :u');
        $stmt->execute(['s' => self::TEST_SITE_ID, 'u' => self::TEST_URL]);
        $saved = $stmt->fetch();
        $this->assertNotFalse($saved, 'A fonte devia ter sido gravada no banco.');
        $this->assertSame('criado pelo SiteSourceControllerHttpTest', $saved['note']);
    }

    public function testStoreWithoutCsrfTokenIsRejectedAndNothingIsSaved(): void
    {
        $session = $this->forgeAdminSession();

        $response = $this->httpPost('/sites/' . self::TEST_SITE_ID . '/sources', [
            // sem _token de propósito
            'url' => self::TEST_URL,
        ], $session['cookie']);

        // Csrf::verify() falha -> flash de erro + redirect (302), nunca grava.
        $this->assertSame(302, $response['status']);

        $stmt = Connection::get()->prepare('SELECT id FROM site_sources WHERE site_id = :s AND url = :u');
        $stmt->execute(['s' => self::TEST_SITE_ID, 'u' => self::TEST_URL]);
        $this->assertFalse($stmt->fetch(), 'Sem token CSRF válido, nada deveria ter sido gravado.');
    }

    public function testStoreWithoutValidSessionIsRejectedWith401(): void
    {
        $response = $this->httpPost('/sites/' . self::TEST_SITE_ID . '/sources', [
            'url' => self::TEST_URL,
        ], 'compost_session=sessao-que-nunca-existiu-neste-teste');

        // Rota exige auth (Router::dispatch()); POST sem sessão válida vira 401, não redirect
        // (redirect pro /login é só pra GET — ver Router::dispatch()).
        $this->assertSame(401, $response['status']);

        $stmt = Connection::get()->prepare('SELECT id FROM site_sources WHERE site_id = :s AND url = :u');
        $stmt->execute(['s' => self::TEST_SITE_ID, 'u' => self::TEST_URL]);
        $this->assertFalse($stmt->fetch());
    }

    public function testDestroyRemovesSource(): void
    {
        $session = $this->forgeAdminSession();

        // Cria direto no banco (não é o alvo deste teste) pra testar só o destroy().
        $pdo = Connection::get();
        $pdo->prepare('INSERT INTO site_sources (site_id, url, note) VALUES (:s, :u, :n)')
            ->execute(['s' => self::TEST_SITE_ID, 'u' => self::TEST_URL, 'n' => null]);
        $sourceId = (int) $pdo->lastInsertId();

        $response = $this->httpPost('/sites/' . self::TEST_SITE_ID . '/sources/' . $sourceId . '/delete', [
            '_token' => $session['token'],
        ], $session['cookie']);

        $this->assertSame(302, $response['status']);

        $stmt = $pdo->prepare('SELECT id FROM site_sources WHERE id = :id');
        $stmt->execute(['id' => $sourceId]);
        $this->assertFalse($stmt->fetch(), 'A fonte devia ter sido removida.');
    }
}

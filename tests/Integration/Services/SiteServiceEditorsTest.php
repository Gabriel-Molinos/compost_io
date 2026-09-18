<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\SiteService;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * `SiteService::syncUsers()` / `userIdsFor()` — o vínculo de Redatores-Chefe
 * feito pela tela do site — contra o banco de dev de verdade. Cria um site e
 * três usuários descartáveis (2 Redatores-Chefe + 1 ADMIN) em `setUp()` e
 * remove tudo em `tearDown()`; nunca toca dados reais. Mesmo padrão de
 * skip-se-banco-fora-do-ar dos outros testes de integração.
 */
final class SiteServiceEditorsTest extends TestCase
{
    private SiteService $sites;
    private int $siteId = 0;
    /** @var array<string, int> */
    private array $users = [];

    protected function setUp(): void
    {
        try {
            $pdo = Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }

        $this->sites = new SiteService();
        $tag = bin2hex(random_bytes(5));

        $pdo->prepare("INSERT INTO sites (name, language, is_active) VALUES (:n, 'pt-BR', 1)")
            ->execute(['n' => 'Site de Teste ' . $tag]);
        $this->siteId = (int) $pdo->lastInsertId();

        foreach (['a' => 'REDATOR_CHEFE', 'b' => 'REDATOR_CHEFE', 'admin' => 'ADMIN'] as $key => $role) {
            $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (:n, :e, :h, :r, 1)'
            )->execute([
                'n' => 'Teste ' . $key,
                'e' => "teste.{$key}.{$tag}@exemplo.invalid",
                'h' => password_hash('nao-usada', PASSWORD_DEFAULT),
                'r' => $role,
            ]);
            $this->users[$key] = (int) $pdo->lastInsertId();
        }
    }

    protected function tearDown(): void
    {
        $pdo = Connection::get();
        // user_site tem ON DELETE CASCADE dos dois lados.
        if ($this->siteId !== 0) {
            $pdo->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $this->siteId]);
        }
        foreach ($this->users as $id) {
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
        }
    }

    public function testSyncLinksOnlyRedatoresAndReturnsTheNewlyLinked(): void
    {
        $added = $this->sites->syncUsers($this->siteId, [$this->users['a'], $this->users['b']]);

        $this->assertEqualsCanonicalizing([$this->users['a'], $this->users['b']], $added);
        $this->assertEqualsCanonicalizing([$this->users['a'], $this->users['b']], $this->sites->userIdsFor($this->siteId));
    }

    public function testSyncOnlyReportsWhoWasNotLinkedBefore(): void
    {
        $this->sites->syncUsers($this->siteId, [$this->users['a']]);

        $added = $this->sites->syncUsers($this->siteId, [$this->users['a'], $this->users['b']]);

        $this->assertSame([$this->users['b']], $added, 'Quem já estava vinculado não deve ser notificado de novo.');
    }

    public function testSyncReplacesTheSetAndCanClearIt(): void
    {
        $this->sites->syncUsers($this->siteId, [$this->users['a'], $this->users['b']]);

        $this->sites->syncUsers($this->siteId, [$this->users['b']]);
        $this->assertSame([$this->users['b']], $this->sites->userIdsFor($this->siteId));

        $this->sites->syncUsers($this->siteId, []);
        $this->assertSame([], $this->sites->userIdsFor($this->siteId));
    }

    public function testAdminAndUnknownIdsNeverGetLinked(): void
    {
        // POST forjado: um ADMIN e um id que não existe no meio dos ids válidos.
        $added = $this->sites->syncUsers($this->siteId, [$this->users['a'], $this->users['admin'], 999999999]);

        $this->assertSame([$this->users['a']], $added);

        $pdo = Connection::get();
        $adminLinks = $pdo->prepare('SELECT COUNT(*) FROM user_site WHERE user_id = :u');
        $adminLinks->execute(['u' => $this->users['admin']]);
        $this->assertSame(0, (int) $adminLinks->fetchColumn(), 'ADMIN enxerga todos os sites — nunca ganha linha em user_site.');
    }

    public function testSyncDoesNotTouchLinksToOtherSites(): void
    {
        $pdo = Connection::get();
        $pdo->prepare("INSERT INTO sites (name, language, is_active) VALUES (:n, 'pt-BR', 1)")
            ->execute(['n' => 'Outro Site de Teste ' . bin2hex(random_bytes(4))]);
        $otherSiteId = (int) $pdo->lastInsertId();

        try {
            $this->sites->syncUsers($otherSiteId, [$this->users['a']]);
            $this->sites->syncUsers($this->siteId, [$this->users['a']]);
            $this->sites->syncUsers($this->siteId, []); // limpa só ESTE site

            $this->assertSame([$this->users['a']], $this->sites->userIdsFor($otherSiteId));
        } finally {
            $pdo->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $otherSiteId]);
        }
    }
}

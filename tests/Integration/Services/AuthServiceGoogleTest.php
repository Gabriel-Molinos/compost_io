<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\AuthService;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * `AuthService::attemptGoogle()` contra o banco de dev de verdade. Cria um
 * usuário descartável em `setUp()` e remove em `tearDown()` — nunca toca
 * contas reais. Segue o mesmo padrão de skip-se-banco-fora-do-ar de
 * `tests/Integration/Services/SiteSourceServiceTest.php` (o banco gerenciado
 * da DigitalOcean tem se mostrado intermitente).
 */
final class AuthServiceGoogleTest extends TestCase
{
    private ?int $userId = null;
    private string $email = '';

    protected function setUp(): void
    {
        try {
            Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }

        $this->email = 'teste.google.' . bin2hex(random_bytes(6)) . '@exemplo.invalid';

        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_active)
             VALUES (:name, :email, :hash, :role, 1)'
        )->execute([
            'name'  => 'Usuário de Teste (Google)',
            'email' => $this->email,
            'hash'  => password_hash('senha-qualquer-nao-usada', PASSWORD_DEFAULT),
            'role'  => 'REDATOR_CHEFE',
        ]);
        $this->userId = (int) $pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->userId !== null) {
            Connection::get()->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $this->userId]);
        }
    }

    public function testUnknownEmailReturnsNullWithoutCreatingAnyRow(): void
    {
        $pdo = Connection::get();
        $countBefore = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

        $result = AuthService::attemptGoogle('google-sub-qualquer', 'nao.existe.' . bin2hex(random_bytes(6)) . '@exemplo.invalid');

        $this->assertNull($result);

        $countAfter = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame($countBefore, $countAfter, 'attemptGoogle() nunca deve criar uma linha nova em users.');
    }

    public function testExistingActiveEmailLogsInAndLinksGoogleId(): void
    {
        $result = AuthService::attemptGoogle('sub-de-teste-123', $this->email);

        $this->assertNotNull($result);
        $this->assertSame($this->userId, (int) $result['id']);
        $this->assertArrayNotHasKey('google_id', $result); // não deve vazar pra fora do método
        $this->assertArrayNotHasKey('password_hash', $result);

        $stored = Connection::get()
            ->query('SELECT google_id FROM users WHERE id = ' . $this->userId)
            ->fetchColumn();
        $this->assertSame('sub-de-teste-123', $stored);
    }

    public function testDifferentGoogleAccountOnAlreadyLinkedEmailIsRejected(): void
    {
        // Primeiro login vincula sub-a; segunda tentativa com sub-b (mesmo
        // e-mail, conta Google diferente) precisa ser recusada.
        AuthService::attemptGoogle('sub-a', $this->email);

        $result = AuthService::attemptGoogle('sub-b', $this->email);

        $this->assertNull($result);
    }

    public function testInactiveUserIsRejected(): void
    {
        Connection::get()->prepare('UPDATE users SET is_active = 0 WHERE id = :id')->execute(['id' => $this->userId]);

        $result = AuthService::attemptGoogle('sub-qualquer', $this->email);

        $this->assertNull($result);
    }
}

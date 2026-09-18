<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\NotificationService;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Ler / desler uma notificação (botão de alternar da tela de notificações) contra o banco de dev:
 * cria uma notificação descartável pro admin (id 1), alterna e apaga — nunca toca as reais.
 */
final class NotificationServiceTest extends TestCase
{
    private NotificationService $service;
    private int $id = 0;

    protected function setUp(): void
    {
        try {
            $pdo = Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }

        $this->service = new NotificationService();
        $this->service->notify(1, 'TESTE_ALTERNAR', 'Teste', 'Notificação descartável de teste');
        $this->id = (int) $pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->id > 0) {
            Connection::get()->prepare('DELETE FROM notifications WHERE id = :id')->execute(['id' => $this->id]);
        }
    }

    public function testMarkReadThenUnreadRoundTrip(): void
    {
        $before = $this->service->unreadCount(1);

        $this->service->markRead($this->id, 1);
        $this->assertNotNull($this->service->find($this->id, 1)['read_at']);
        $this->assertSame($before - 1, $this->service->unreadCount(1));

        $this->service->markUnread($this->id, 1);
        $this->assertNull($this->service->find($this->id, 1)['read_at']);
        $this->assertSame($before, $this->service->unreadCount(1));
    }

    public function testOtherUsersCannotToggleSomeoneElsesNotification(): void
    {
        $this->service->markRead($this->id, 999999);

        $this->assertNull($this->service->find($this->id, 1)['read_at']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\NotificationService;
use App\Services\PlatformFeedbackService;
use App\Services\SidebarService;
use App\Services\SiteService;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * `SidebarService::counts()` junta 5 consultas da sidebar numa só — o resultado
 * precisa ser IGUAL ao dos serviços que ela substituiu (somente leitura, contra
 * o banco de dev; admin id 1). Skip se o banco estiver fora do ar.
 */
final class SidebarServiceTest extends TestCase
{
    protected function setUp(): void
    {
        try {
            Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }
    }

    public function testCountsMatchTheServicesTheyReplace(): void
    {
        $sites = new SiteService();
        $counts = (new SidebarService())->counts(1, true);

        $this->assertSame((new NotificationService())->unreadCount(1), $counts['unread']);
        $this->assertSame((new PlatformFeedbackService())->countPending(), $counts['pendingFeedback']);
        $this->assertSame($sites->countAll(), $counts['sitesTotal']);
        $this->assertSame($sites->firstIdForUser(1), $counts['firstSiteId']);
    }

    public function testNonAdminSeesNoPendingFeedbackAndOnlyOwnSites(): void
    {
        $sites = new SiteService();
        $counts = (new SidebarService())->counts(1, false);

        $this->assertSame(0, $counts['pendingFeedback']);
        $this->assertSame($sites->countForUser(1), $counts['sitesTotal']);
    }
}

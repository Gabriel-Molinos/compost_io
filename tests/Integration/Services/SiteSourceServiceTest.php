<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\SiteSourceService;
use PHPUnit\Framework\TestCase;
use PDOException;

/**
 * Bate no banco de dev de verdade (site "Gavsy", id 2 — docs/technical
 * mencionam esse site como o de teste padrão do projeto). O banco gerenciado
 * da DigitalOcean tem se mostrado intermitente (achado real 2026-09-15) —
 * `setUp()` pula o teste em vez de falhar quando a conexão não responde, pra
 * uma instabilidade de rede não ser lida como regressão de código.
 */
final class SiteSourceServiceTest extends TestCase
{
    private const TEST_SITE_ID = 2;

    private SiteSourceService $sources;
    private ?int $createdId = null;

    protected function setUp(): void
    {
        try {
            Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }

        $this->sources = new SiteSourceService();
    }

    protected function tearDown(): void
    {
        if ($this->createdId !== null) {
            $this->sources->delete($this->createdId);
        }
    }

    public function testCreateFindAndDigestForPrompt(): void
    {
        $this->createdId = $this->sources->create(
            self::TEST_SITE_ID,
            'https://teste.integracao.invalid/fonte',
            'Nota de teste automatizado',
        );

        $found = $this->sources->find(self::TEST_SITE_ID, $this->createdId);
        $this->assertNotNull($found);
        $this->assertSame('https://teste.integracao.invalid/fonte', $found['url']);
        $this->assertSame('Nota de teste automatizado', $found['note']);

        $digest = $this->sources->digestForPrompt(self::TEST_SITE_ID);
        $this->assertNotNull($digest);
        $this->assertStringContainsString('https://teste.integracao.invalid/fonte', $digest);
        $this->assertStringContainsString('Nota de teste automatizado', $digest);
    }

    public function testDeleteRemovesTheSource(): void
    {
        $id = $this->sources->create(self::TEST_SITE_ID, 'https://outra.teste.invalid/fonte', null);

        $this->sources->delete($id);

        $this->assertNull($this->sources->find(self::TEST_SITE_ID, $id));
        // Já removido — tearDown() não precisa (e não deveria) tentar de novo.
        $this->createdId = null;
    }

    public function testFindReturnsNullForWrongSiteScope(): void
    {
        $this->createdId = $this->sources->create(self::TEST_SITE_ID, 'https://terceira.teste.invalid/fonte', null);

        // Mesmo id, site errado — não pode "escapar" do escopo do site.
        $this->assertNull($this->sources->find(self::TEST_SITE_ID + 9999, $this->createdId));
    }
}

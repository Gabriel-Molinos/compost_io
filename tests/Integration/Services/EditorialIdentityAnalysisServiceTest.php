<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Integrations\AIException;
use App\Integrations\AIProvider;
use App\Integrations\AIResult;
use App\Integrations\WordPress\WordPressException;
use App\Services\EditorialIdentityAnalysisService;
use App\Services\SiteService;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * `EditorialIdentityAnalysisService::suggest()` (pedido do responsável
 * 2026-09-28): site vazio não sugere nada, site com identidade já preenchida
 * não sugere nada (nunca pisa em cima de um humano), IA fora do ar é engolida,
 * e o caminho feliz preenche os 4 campos + marca `editorial_identity_suggested_at`.
 * `$fetchPosts` (closure injetada) substitui a chamada real ao WordPress —
 * ver comentário no construtor do serviço.
 */
final class EditorialIdentityAnalysisServiceTest extends TestCase
{
    private ?int $siteId = null;
    private SiteService $sites;

    protected function setUp(): void
    {
        try {
            Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }
        $this->sites = new SiteService();
    }

    protected function tearDown(): void
    {
        if ($this->siteId !== null) {
            Connection::get()->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $this->siteId]);
            $this->siteId = null;
        }
    }

    private function createSite(array $overrides = []): int
    {
        $this->siteId = $this->sites->create($overrides + [
            'name'     => 'Site de teste IdentidadeIA ' . bin2hex(random_bytes(4)),
            'language' => 'pt-BR',
        ]);

        return $this->siteId;
    }

    private function posts(int $n): \Closure
    {
        return static function (int $siteId) use ($n): array {
            $out = [];
            for ($i = 0; $i < $n; $i++) {
                $out[] = ['title' => "Post {$i}", 'excerpt' => "Resumo do post {$i} sobre o nicho do site."];
            }

            return $out;
        };
    }

    public function testEmptySiteWithFewPostsDoesNotFillAnything(): void
    {
        $siteId = $this->createSite();
        $service = new EditorialIdentityAnalysisService(sites: $this->sites, ai: new NeverCalledAIProvider(), fetchPosts: $this->posts(2));

        $applied = $service->suggest($siteId);

        $this->assertFalse($applied);
        $site = $this->sites->find($siteId);
        $this->assertSame('', trim((string) $site['niche']));
        $this->assertNull($site['editorial_identity_suggested_at']);
    }

    public function testSiteWithNoWordPressConnectionDoesNotFillAnything(): void
    {
        $siteId = $this->createSite();
        $fetchPosts = static function (int $siteId): array {
            throw new WordPressException('sem conexão configurada pra este site');
        };
        $service = new EditorialIdentityAnalysisService(sites: $this->sites, ai: new NeverCalledAIProvider(), fetchPosts: $fetchPosts);

        $this->assertFalse($service->suggest($siteId));
    }

    public function testSiteWithIdentityAlreadyFilledIsNeverOverwritten(): void
    {
        $siteId = $this->createSite(['niche' => 'nicho já escolhido à mão']);
        $service = new EditorialIdentityAnalysisService(sites: $this->sites, ai: new NeverCalledAIProvider(), fetchPosts: $this->posts(20));

        $this->assertFalse($service->suggest($siteId));
        $this->assertSame('nicho já escolhido à mão', $this->sites->find($siteId)['niche']);
    }

    public function testAiFailureIsSwallowed(): void
    {
        $siteId = $this->createSite();
        $service = new EditorialIdentityAnalysisService(sites: $this->sites, ai: new FailingAIProvider(), fetchPosts: $this->posts(20));

        $this->assertFalse($service->suggest($siteId));
        $this->assertNull($this->sites->find($siteId)['editorial_identity_suggested_at']);
    }

    public function testHappyPathFillsAllFourFieldsAndMarksAsSuggested(): void
    {
        $siteId = $this->createSite();
        $ai = new FixedJsonAIProvider([
            'niche'              => 'viagens econômicas',
            'target_audience'    => 'pessoas planejando a primeira viagem internacional com pouco orçamento',
            'tone'               => 'amigável, didático, direto',
            'editorial_identity' => 'Cobre roteiros de baixo custo, passo a passo prático, sem jargão.',
        ]);
        $service = new EditorialIdentityAnalysisService(sites: $this->sites, ai: $ai, fetchPosts: $this->posts(20));

        $this->assertTrue($service->suggest($siteId));

        $site = $this->sites->find($siteId);
        $this->assertSame('viagens econômicas', $site['niche']);
        $this->assertSame('pessoas planejando a primeira viagem internacional com pouco orçamento', $site['target_audience']);
        $this->assertSame('amigável, didático, direto', $site['tone']);
        $this->assertSame('Cobre roteiros de baixo custo, passo a passo prático, sem jargão.', $site['editorial_identity']);
        $this->assertNotNull($site['editorial_identity_suggested_at']);
    }

    public function testOversizedFieldsAreTruncatedToColumnLimits(): void
    {
        $siteId = $this->createSite();
        $ai = new FixedJsonAIProvider([
            'niche'              => str_repeat('a', 300),
            'target_audience'    => str_repeat('b', 400),
            'tone'               => str_repeat('c', 200),
            'editorial_identity' => str_repeat('d', 6000),
        ]);
        $service = new EditorialIdentityAnalysisService(sites: $this->sites, ai: $ai, fetchPosts: $this->posts(20));

        $this->assertTrue($service->suggest($siteId));

        $site = $this->sites->find($siteId);
        $this->assertSame(191, mb_strlen((string) $site['niche']));
        $this->assertSame(255, mb_strlen((string) $site['target_audience']));
        $this->assertSame(100, mb_strlen((string) $site['tone']));
        $this->assertSame(5000, mb_strlen((string) $site['editorial_identity']));
    }

    public function testManualSaveClearsTheSuggestedMarker(): void
    {
        $siteId = $this->createSite();
        $ai = new FixedJsonAIProvider([
            'niche' => 'nicho', 'target_audience' => 'público', 'tone' => 'tom', 'editorial_identity' => 'identidade',
        ]);
        (new EditorialIdentityAnalysisService(sites: $this->sites, ai: $ai, fetchPosts: $this->posts(20)))->suggest($siteId);
        $this->assertNotNull($this->sites->find($siteId)['editorial_identity_suggested_at']);

        $this->sites->update($siteId, ['name' => 'Site de teste IdentidadeIA (revisado)', 'niche' => 'nicho', 'language' => 'pt-BR']);

        $this->assertNull($this->sites->find($siteId)['editorial_identity_suggested_at']);
    }
}

final class NeverCalledAIProvider implements AIProvider
{
    public function generateText(string $prompt, ?string $systemInstruction = null): AIResult
    {
        throw new RuntimeException('NeverCalledAIProvider: não devia ter sido chamado.');
    }

    /** @param array<string, mixed> $schema */
    public function generateJson(string $prompt, array $schema, ?string $systemInstruction = null): AIResult
    {
        throw new RuntimeException('NeverCalledAIProvider: guarda de "sem posts o bastante"/"já preenchido" falhou — chamou a IA mesmo assim.');
    }
}

final class FailingAIProvider implements AIProvider
{
    public function generateText(string $prompt, ?string $systemInstruction = null): AIResult
    {
        throw new AIException('falha simulada');
    }

    /** @param array<string, mixed> $schema */
    public function generateJson(string $prompt, array $schema, ?string $systemInstruction = null): AIResult
    {
        throw new AIException('falha simulada');
    }
}

final class FixedJsonAIProvider implements AIProvider
{
    /** @param array<string, mixed> $json */
    public function __construct(private array $json)
    {
    }

    public function generateText(string $prompt, ?string $systemInstruction = null): AIResult
    {
        throw new RuntimeException('FixedJsonAIProvider: generateText() não é usado por este serviço.');
    }

    /** @param array<string, mixed> $schema */
    public function generateJson(string $prompt, array $schema, ?string $systemInstruction = null): AIResult
    {
        return new AIResult(text: json_encode($this->json, JSON_UNESCAPED_UNICODE), json: $this->json, promptTokens: 100, outputTokens: 50, totalTokens: 150, model: 'fake-model');
    }
}

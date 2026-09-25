<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Pipeline;

use App\Database\Connection;
use App\Integrations\AIProvider;
use App\Integrations\AIResult;
use App\Services\ArticleService;
use App\Services\Pipeline\ArticlePipeline;
use App\Services\SiteService;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * "Rascunho específico" (2026-09-24): o pedido livre do redator é guardado em
 * `articles.writer_request` e injetado, como pauta de prioridade máxima, no
 * prompt de TODOS os passos da IA — inclusive nas regenerações da mesma
 * linhagem — e nunca vaza pra um rascunho comum gerado depois pelo mesmo
 * pipeline. IA falsa que guarda os prompts recebidos: zero custo, zero rede.
 */
final class WriterRequestPipelineTest extends TestCase
{
    // Cabeçalho exato da camada — só o PromptBuilder o gera. O nome "PEDIDO ESPECÍFICO DO REDATOR"
    // sozinho também aparece nas regras condicionais de docs/ai/*.md, então não serve de marcador.
    private const MARKER = '# PEDIDO ESPECÍFICO DO REDATOR (PRIORIDADE MÁXIMA)';
    private const REQUEST = 'Guia de rotina de skincare para pele oleosa, com tabela comparando 3 protetores solares e FAQ de 5 perguntas.';

    private ?int $siteId = null;

    /** @var list<int> */
    private array $articleIds = [];

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
        foreach ($this->articleIds as $id) {
            Connection::get()->prepare('DELETE FROM articles WHERE id = :id')->execute(['id' => $id]);
        }
        $this->articleIds = [];
        if ($this->siteId !== null) {
            Connection::get()->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $this->siteId]);
            $this->siteId = null;
        }
    }

    public function testRequestIsStoredAndReachesEveryStepPrompt(): void
    {
        $this->siteId = $this->createTestSite();
        $ai = new PromptCapturingAIProvider($this->stepResponses());
        $pipeline = new ArticlePipeline($ai);

        $id = $pipeline->prepareGenerate($this->siteId, null, 'MANUAL', self::REQUEST);
        $this->articleIds[] = $id;

        $this->assertSame(self::REQUEST, (new ArticleService())->find($this->siteId, $id)['writer_request']);

        $pipeline->runGenerate($id, $this->siteId);

        $this->assertCount(7, $ai->prompts, 'planning, research, writing, seo, compliance, review, image');
        foreach ($ai->prompts as $i => $prompt) {
            $this->assertStringContainsString(self::MARKER, $prompt, "passo #{$i} não recebeu o pedido do redator");
            $this->assertStringContainsString(self::REQUEST, $prompt, "passo #{$i} não recebeu o texto do pedido");
        }
    }

    public function testRegularDraftGetsNoRequestLayerAndNothingLeaksFromThePreviousRun(): void
    {
        $this->siteId = $this->createTestSite();
        $ai = new PromptCapturingAIProvider(array_merge($this->stepResponses(), $this->stepResponses()));
        $pipeline = new ArticlePipeline($ai); // MESMA instância nos dois rascunhos — é o caso que poderia vazar estado

        $withRequest = $pipeline->prepareGenerate($this->siteId, null, 'MANUAL', self::REQUEST);
        $this->articleIds[] = $withRequest;
        $pipeline->runGenerate($withRequest, $this->siteId);

        $ai->prompts = [];
        $plain = $pipeline->prepareGenerate($this->siteId);
        $this->articleIds[] = $plain;
        $pipeline->runGenerate($plain, $this->siteId);

        $this->assertNull((new ArticleService())->find($this->siteId, $plain)['writer_request']);
        $this->assertCount(7, $ai->prompts);
        foreach ($ai->prompts as $i => $prompt) {
            $this->assertStringNotContainsString(self::MARKER, $prompt, "passo #{$i}: o pedido do rascunho anterior vazou");
        }
    }

    public function testRegenerationKeepsFollowingTheSameRequest(): void
    {
        $this->siteId = $this->createTestSite();
        $ai = new PromptCapturingAIProvider($this->stepResponses());
        $pipeline = new ArticlePipeline($ai);
        $articles = new ArticleService();

        $first = $pipeline->prepareGenerate($this->siteId, null, 'MANUAL', self::REQUEST);
        $this->articleIds[] = $first;
        $articles->setStatus($first, 'REVISION_REQUESTED'); // como se o Redator-Chefe tivesse rejeitado

        $next = $pipeline->prepareRegenerate($first);
        $this->articleIds[] = $next['article_id'];

        $this->assertSame(
            self::REQUEST,
            $articles->find($this->siteId, $next['article_id'])['writer_request'],
            'a nova tentativa da linhagem tem que herdar o pedido',
        );

        $pipeline->runRegenerate($next['article_id'], $this->siteId, null, null, $next['lineage_id']);

        $this->assertCount(7, $ai->prompts);
        foreach ($ai->prompts as $i => $prompt) {
            $this->assertStringContainsString(self::REQUEST, $prompt, "passo #{$i} da regeneração perdeu o pedido");
        }
    }

    public function testOverlongRequestIsTrimmedToTheColumnLimit(): void
    {
        $this->siteId = $this->createTestSite();
        $id = (new ArticleService())->create($this->siteId, null, 'MANUAL', str_repeat('a', ArticleService::WRITER_REQUEST_MAX + 500));
        $this->articleIds[] = $id;

        $stored = (string) (new ArticleService())->find($this->siteId, $id)['writer_request'];
        $this->assertSame(ArticleService::WRITER_REQUEST_MAX, mb_strlen($stored));
    }

    private function createTestSite(): int
    {
        return (new SiteService())->create([
            'name'     => 'Site de teste WriterRequest ' . bin2hex(random_bytes(4)),
            'niche'    => 'testes automatizados',
            'language' => 'pt-BR',
            'tone'     => 'neutro',
        ]);
    }

    /** @return list<AIResult> uma resposta por passo, na ordem do pipeline. */
    private function stepResponses(): array
    {
        $html = '<p>Parágrafo de teste do WriterRequestPipelineTest.</p><p>Outro parágrafo curto.</p>';

        return [
            new AIResult('', [
                'title' => 'Guia de skincare', 'focus_keyword' => 'skincare pele oleosa', 'category' => '',
                'angle' => 'a', 'rationale' => 'r', 'cannibalization_risk' => 'none',
            ], 10, 10, 20, 'fake-model'),
            new AIResult('', ['findings' => [], 'gaps' => []], 10, 10, 20, 'fake-model'),
            new AIResult('', [
                'title' => 'Guia de skincare', 'slug' => 'guia-de-skincare', 'focus_keyword' => 'skincare pele oleosa',
                'meta_description' => 'Meta.', 'content_html' => $html, 'word_count' => 0,
            ], 10, 10, 20, 'fake-model'),
            new AIResult('', ['passes' => true, 'issues' => []], 10, 10, 20, 'fake-model'),
            new AIResult('', ['approved' => true, 'blocking' => [], 'warnings' => []], 10, 10, 20, 'fake-model'),
            new AIResult('', ['recommendation' => 'ready_for_human', 'summary' => 'ok'], 10, 10, 20, 'fake-model'),
            new AIResult('', ['style_notes' => '', 'images' => []], 10, 10, 20, 'fake-model'),
        ];
    }
}

/** Como o FakeStepAIProvider do ArticlePipelineTest, mas guarda cada prompt recebido pra o teste inspecionar. */
final class PromptCapturingAIProvider implements AIProvider
{
    /** @var list<string> */
    public array $prompts = [];

    /** @param list<AIResult> $queue */
    public function __construct(private array $queue)
    {
    }

    public function generateText(string $prompt, ?string $systemInstruction = null): AIResult
    {
        throw new RuntimeException('PromptCapturingAIProvider: generateText() não é usado pelo pipeline de geração.');
    }

    /** @param array<string, mixed> $schema */
    public function generateJson(string $prompt, array $schema, ?string $systemInstruction = null): AIResult
    {
        if ($this->queue === []) {
            throw new RuntimeException('PromptCapturingAIProvider: fila de respostas programadas esgotou.');
        }
        $this->prompts[] = $prompt;

        return array_shift($this->queue);
    }
}

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
 * `ArticlePipeline::runGenerate()` de ponta a ponta contra o banco de dev de
 * verdade, mas com um `AIProvider` falso (`FakeStepAIProvider`, abaixo) no
 * lugar do Gemini real — sem isso, testar o pipeline exigiria uma chamada
 * paga de verdade a cada rodada de teste. O site usado é criado e apagado
 * pelo próprio teste (nunca um dos sites reais do dev, que têm WordPress
 * de verdade conectado — `ArticlePipeline::run()` tenta buscar posts
 * publicados via `WordPressClient::listRecentPosts()`; um site sem conexão
 * cai no `catch (WordPressException)` e segue sem essa lista, exatamente
 * como aconteceria num site novo de verdade).
 *
 * Cobre o "ainda não implementado: ... do pipeline de IA" apontado em
 * docs/technical/testes-e-observabilidade.md §92 — não é cobertura
 * exaustiva de cada ramo do pipeline, é o ponto de partida: o caminho
 * feliz completo (7 passos, virar IN_REVIEW) mais um caso de aviso real
 * (texto abaixo do mínimo de palavras).
 */
final class ArticlePipelineTest extends TestCase
{
    private ?int $siteId = null;
    private ?int $articleId = null;

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
        if ($this->articleId !== null) {
            Connection::get()->prepare('DELETE FROM articles WHERE id = :id')->execute(['id' => $this->articleId]);
            $this->articleId = null;
        }
        if ($this->siteId !== null) {
            Connection::get()->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $this->siteId]);
            $this->siteId = null;
        }
    }

    public function testRunGenerateHappyPathTurnsArticleIntoInReview(): void
    {
        $this->siteId = $this->createTestSite();

        $pipeline = new ArticlePipeline($this->fakeProvider());
        $this->articleId = $pipeline->prepareGenerate($this->siteId);

        $result = $pipeline->runGenerate($this->articleId, $this->siteId);

        $this->assertSame('Guia de Teste Automatizado', $result['title']);
        $this->assertSame('ready_for_human', $result['recommendation']);
        $this->assertGreaterThan(0, $result['word_count']);

        $article = (new ArticleService())->find($this->siteId, $this->articleId);
        $this->assertNotNull($article);
        $this->assertSame('IN_REVIEW', $article['status']);
        $this->assertSame('teste automatizado', $article['focus_keyword']);

        $version = (new ArticleService())->latestVersion($this->articleId);
        $this->assertNotNull($version);
        $this->assertStringContainsString('Este é um parágrafo de teste', (string) $version['content']);

        // Passo `image` devolveu images:[] de propósito (evita precisar de
        // ImageProvider/NanoBanana no teste) — o pipeline trata isso como
        // aviso, nunca falha a geração por causa de imagem (generateImages()
        // é sempre best-effort, ver comentário na própria classe).
        $this->assertContains('Brief visual não retornou nenhuma imagem utilizável — artigo sem imagens.', $result['warnings']);
    }

    public function testRunGenerateWarnsWhenWritingIsBelowMinimumWordCount(): void
    {
        $this->siteId = $this->createTestSite();

        $pipeline = new ArticlePipeline($this->fakeProvider());
        $this->articleId = $pipeline->prepareGenerate($this->siteId);

        $result = $pipeline->runGenerate($this->articleId, $this->siteId);

        $tooShortWarning = array_filter(
            $result['warnings'],
            static fn (string $w): bool => str_contains($w, 'abaixo do mínimo de 1500'),
        );
        $this->assertNotEmpty($tooShortWarning, 'Texto de teste tem propositalmente poucas palavras — devia gerar o aviso de mínimo.');
    }

    private function createTestSite(): int
    {
        return (new SiteService())->create([
            'name'     => 'Site de teste ArticlePipelineTest ' . bin2hex(random_bytes(4)),
            'niche'    => 'testes automatizados',
            'language' => 'pt-BR',
            'tone'     => 'neutro',
        ]);
    }

    private function fakeProvider(): AIProvider
    {
        $contentHtml = '<p>Este é um parágrafo de teste gerado pelo FakeStepAIProvider, sem nenhum link, '
            . 'pra ArticlePipelineTest não depender de chamada de IA real nem de verificação HTTP de verdade.</p>'
            . '<p>Segundo parágrafo curto só pra ter mais de uma tag.</p>';

        return new FakeStepAIProvider([
            // planning
            new AIResult('', [
                'title'                => 'Guia de Teste Automatizado',
                'focus_keyword'        => 'teste automatizado',
                'category'             => '',
                'angle'                => 'ângulo de teste',
                'rationale'            => 'motivo de teste',
                'cannibalization_risk' => 'none',
            ], 10, 10, 20, 'fake-model'),
            // research
            new AIResult('', [
                'findings' => [],
                'gaps'     => [],
            ], 10, 10, 20, 'fake-model'),
            // writing
            new AIResult('', [
                'title'            => 'Guia de Teste Automatizado',
                'slug'             => 'guia-de-teste-automatizado',
                'focus_keyword'    => 'teste automatizado',
                'meta_description' => 'Meta descrição de teste.',
                'content_html'     => $contentHtml,
                'word_count'       => 0, // força o pipeline a recontar via strip_tags() — cai bem abaixo de 1500
            ], 10, 10, 20, 'fake-model'),
            // seo
            new AIResult('', ['passes' => true, 'issues' => []], 10, 10, 20, 'fake-model'),
            // compliance
            new AIResult('', ['approved' => true, 'blocking' => [], 'warnings' => []], 10, 10, 20, 'fake-model'),
            // review
            new AIResult('', [
                'recommendation' => 'ready_for_human',
                'summary'        => 'Parecer de teste — tudo ok.',
            ], 10, 10, 20, 'fake-model'),
            // image — vazio de propósito (ver docblock da classe)
            new AIResult('', ['style_notes' => '', 'images' => []], 10, 10, 20, 'fake-model'),
        ]);
    }
}

/**
 * Devolve, em ordem, um `AIResult` pré-programado por chamada a
 * `generateJson()` — um por passo do pipeline (planning, research, writing,
 * seo, compliance, review, image, nesta ordem — ver `ArticlePipeline::run()`).
 * Não inspeciona prompt/schema recebido: a ordem de chamada do pipeline é
 * determinística, então a fila basta.
 */
final class FakeStepAIProvider implements AIProvider
{
    /** @param list<AIResult> $queue */
    public function __construct(private array $queue)
    {
    }

    public function generateText(string $prompt, ?string $systemInstruction = null): AIResult
    {
        throw new RuntimeException('FakeStepAIProvider: generateText() não é usado pelo pipeline de geração.');
    }

    /** @param array<string, mixed> $schema */
    public function generateJson(string $prompt, array $schema, ?string $systemInstruction = null): AIResult
    {
        if ($this->queue === []) {
            throw new RuntimeException('FakeStepAIProvider: passo a mais chamado — fila de respostas programadas esgotou.');
        }

        return array_shift($this->queue);
    }
}

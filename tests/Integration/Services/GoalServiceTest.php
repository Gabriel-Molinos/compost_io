<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Database\Connection;
use App\Services\CategoryService;
use App\Services\GoalService;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * `GoalService::mostUnderTargetCategory()` contra o banco de dev de verdade
 * (site "Gavsy", id 2 — já tem categorias reais, usa duas delas). Mesmo
 * padrão de skip-se-banco-fora-do-ar de `SiteSourceServiceTest`; meta e
 * artigos de teste criados num período fictício (2099-01, nunca colide com
 * dado real) e removidos em `tearDown()`.
 *
 * Achado real (2026-09-21, pedido do responsável: "acho que bugou, a
 * geração só tá indo pra uma categoria") — a versão anterior só contava
 * como "já feito" artigo APPROVED/SCHEDULED/PUBLISHED; uma categoria com
 * vários rascunhos represados em IN_REVIEW (esperando revisão humana, que
 * demora) continuava parecendo "atrasada" pro algoritmo pra sempre.
 */
final class GoalServiceTest extends TestCase
{
    private const TEST_SITE_ID = 2;
    // "Futuro o bastante" pra nunca colidir com dado real, mas dentro do alcance de TIMESTAMP do
    // MySQL (até 2038-01-19) — achado real: 2099 estourava esse limite ("Incorrect datetime value").
    private const TEST_PERIOD = '2037-06';

    private GoalService $goals;
    private ?int $goalId = null;
    /** @var list<int> */
    private array $createdArticleIds = [];

    protected function setUp(): void
    {
        try {
            Connection::get();
        } catch (PDOException) {
            $this->markTestSkipped('Banco de dev indisponível agora — não é uma falha de código.');
        }

        $this->goals = new GoalService();
    }

    protected function tearDown(): void
    {
        if ($this->createdArticleIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdArticleIds), '?'));
            Connection::get()->prepare("DELETE FROM articles WHERE id IN ({$placeholders})")->execute($this->createdArticleIds);
        }
        if ($this->goalId !== null) {
            Connection::get()->prepare('DELETE FROM goals WHERE id = :id')->execute(['id' => $this->goalId]);
        }
    }

    private function createArticle(int $categoryId, string $status): int
    {
        // created_at PRECISA cair dentro de self::TEST_PERIOD — a contagem que
        // mostUnderTargetCategory() faz é por mês de created_at, não por "agora".
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO articles (site_id, category_id, status, created_at) VALUES (:s, :c, :st, :d)'
        )->execute(['s' => self::TEST_SITE_ID, 'c' => $categoryId, 'st' => $status, 'd' => self::TEST_PERIOD . '-15 10:00:00']);
        $id = (int) $pdo->lastInsertId();
        $this->createdArticleIds[] = $id;

        return $id;
    }

    public function testPicksCategoryWithReviewPendingArticlesOverUntouchedOne(): void
    {
        $categories = (new CategoryService())->allForSite(self::TEST_SITE_ID);
        if (count($categories) < 2) {
            $this->markTestSkipped('Site de teste precisa de pelo menos 2 categorias.');
        }
        [$categoryA, $categoryB] = [(int) $categories[0]['id'], (int) $categories[1]['id']];

        $this->goalId = $this->goals->create(self::TEST_SITE_ID, self::TEST_PERIOD, 4, null, [
            $categoryA => 3,
            $categoryB => 1,
        ]);

        // Categoria A já tem 3 rascunhos (bateu o alvo) — só que nenhum aprovado ainda, esperando
        // revisão. Categoria B nunca recebeu nenhum. Sem o fix, A "parece" atrasada pra sempre
        // (IN_REVIEW não contava) e o algoritmo escolheria ela de novo, empilhando tudo ali.
        $this->createArticle($categoryA, 'IN_REVIEW');
        $this->createArticle($categoryA, 'IN_REVIEW');
        $this->createArticle($categoryA, 'IN_REVIEW');

        $picked = $this->goals->mostUnderTargetCategory(self::TEST_SITE_ID, $this->goalId, self::TEST_PERIOD);

        $this->assertSame($categoryB, $picked, 'Categoria B (alvo ainda não tocado) deveria ganhar de A (alvo já represado em revisão).');
    }

    public function testDiscardedArticlesDoNotCountTowardTheTarget(): void
    {
        $categories = (new CategoryService())->allForSite(self::TEST_SITE_ID);
        if ($categories === []) {
            $this->markTestSkipped('Site de teste precisa de pelo menos 1 categoria.');
        }
        $categoryId = (int) $categories[0]['id'];

        $this->goalId = $this->goals->create(self::TEST_SITE_ID, self::TEST_PERIOD, 2, null, [
            $categoryId => 2,
        ]);
        $this->createArticle($categoryId, 'DISCARDED');

        $picked = $this->goals->mostUnderTargetCategory(self::TEST_SITE_ID, $this->goalId, self::TEST_PERIOD);

        // Descartado não conta: o alvo (2) continua todo em aberto pra essa categoria (a única do
        // teste), então ela ainda é a "mais atrasada" — descartar não deve travar a geração futura.
        $this->assertSame($categoryId, $picked);
    }
}

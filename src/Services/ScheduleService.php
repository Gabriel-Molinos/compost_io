<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use DateTimeImmutable;
use Exception;
use RuntimeException;

/**
 * Agendamento de publicação (RF-011, fluxo-editorial §30). Um artigo `APPROVED`
 * recebe autor + data/hora + imagem destacada e vai para `SCHEDULED`, com uma
 * linha `PENDING` em `schedules`. O envio ao WordPress é a fatia 7.5.
 *
 *   APPROVED --agendar--> SCHEDULED        (schedules: PENDING)
 *   SCHEDULED --reagendar--> SCHEDULED     (atualiza a linha PENDING)
 *   SCHEDULED --cancelar--> APPROVED       (schedules: CANCELED)
 */
final class ScheduleService
{
    public function __construct(private readonly ArticleService $articles = new ArticleService())
    {
    }

    /**
     * Agendamento vigente (PENDING) do artigo, com nome do autor e imagem.
     *
     * @return array<string, mixed>|null
     */
    public function activeForArticle(int $articleId): ?array
    {
        $stmt = Connection::get()->prepare(
            "SELECT s.*, a.name AS author_name, i.url AS image_url
             FROM schedules s
             LEFT JOIN site_authors a ON a.id = s.author_id
             LEFT JOIN images i ON i.id = s.image_id
             WHERE s.article_id = :a AND s.status = 'PENDING'
             ORDER BY s.id DESC LIMIT 1"
        );
        $stmt->execute(['a' => $articleId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Agendamento a exibir para um artigo já publicado: prioriza a linha que
     * tem um post no WordPress (pode haver linhas canceladas mais recentes de
     * tentativas anteriores).
     *
     * @return array<string, mixed>|null
     */
    public function latestForArticle(int $articleId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT s.*, a.name AS author_name
             FROM schedules s
             LEFT JOIN site_authors a ON a.id = s.author_id
             WHERE s.article_id = :a
             ORDER BY (s.wordpress_post_id IS NOT NULL) DESC, s.id DESC
             LIMIT 1'
        );
        $stmt->execute(['a' => $articleId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Agendamentos do site num intervalo de datas (calendário mensal — 7.6).
     * Ignora os cancelados.
     *
     * @return list<array<string, mixed>>
     */
    public function betweenForSite(int $siteId, string $startDate, string $endDate): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT s.id, s.scheduled_date, s.status, s.wordpress_post_id,
                    a.id AS article_id, a.title, a.status AS article_status,
                    au.name AS author_name
             FROM schedules s
             JOIN articles a ON a.id = s.article_id AND a.site_id = :s AND a.deleted_at IS NULL
             LEFT JOIN site_authors au ON au.id = s.author_id
             WHERE s.status <> 'CANCELED'
               AND s.scheduled_date >= :start AND s.scheduled_date < :end
             ORDER BY s.scheduled_date, a.title"
        );
        $stmt->execute(['s' => $siteId, 'start' => $startDate, 'end' => $endDate]);

        return $stmt->fetchAll();
    }

    /**
     * Agendamentos `PENDING` cuja data já chegou — o que o worker automático
     * (Fase 9, `bin/worker.php`) despacha como `schedule.publish`. Antes disso,
     * o envio ao WordPress dependia de alguém lembrar de clicar "Enviar".
     *
     * @return list<array{id: int, article_id: int, site_id: int}>
     */
    public function dueForPublish(): array
    {
        $stmt = Connection::get()->query(
            "SELECT s.id, s.article_id, a.site_id
             FROM schedules s
             JOIN articles a ON a.id = s.article_id AND a.deleted_at IS NULL
             WHERE s.status = 'PENDING' AND s.scheduled_date <= NOW()"
        );

        return array_map(
            static fn (array $r): array => ['id' => (int) $r['id'], 'article_id' => (int) $r['article_id'], 'site_id' => (int) $r['site_id']],
            $stmt->fetchAll()
        );
    }

    /** Marca um agendamento como `FAILED` — envio ao WordPress esgotou as tentativas (Fase 9). Nunca falha silenciosamente. */
    public function markFailed(int $scheduleId): void
    {
        Connection::get()->prepare("UPDATE schedules SET status = 'FAILED' WHERE id = :id AND status = 'PENDING'")
            ->execute(['id' => $scheduleId]);
    }

    /** @return list<array<string, mixed>> autores ativos do site */
    public function authorsForSite(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, name, wordpress_author_id FROM site_authors
             WHERE site_id = :s AND is_active = 1 ORDER BY name'
        );
        $stmt->execute(['s' => $siteId]);

        return $stmt->fetchAll();
    }

    /**
     * Cria o agendamento. Valida artigo/autor/imagem/data.
     *
     * @throws RuntimeException entrada inválida
     */
    public function schedule(int $articleId, int $siteId, int $authorId, string $dateTimeLocal, int $imageId): void
    {
        $article = $this->articles->find($siteId, $articleId);
        if ($article === null) {
            throw new RuntimeException('Artigo não encontrado.');
        }
        if ($article['status'] !== 'APPROVED') {
            throw new RuntimeException('Só é possível agendar um artigo aprovado (status atual: ' . $article['status'] . ').');
        }

        $when = $this->parseFutureDate($dateTimeLocal);
        $this->assertAuthor($authorId, $siteId);
        $this->assertFeaturedImage($imageId, $articleId);

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO schedules (article_id, author_id, image_id, scheduled_date, status)
                 VALUES (:a, :au, :img, :d, 'PENDING')"
            )->execute(['a' => $articleId, 'au' => $authorId, 'img' => $imageId, 'd' => $when]);

            $this->articles->setStatus($articleId, 'SCHEDULED');
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza o agendamento vigente (artigo continua SCHEDULED).
     *
     * @throws RuntimeException entrada inválida
     */
    public function reschedule(int $articleId, int $siteId, int $authorId, string $dateTimeLocal, int $imageId): void
    {
        $schedule = $this->activeForArticle($articleId);
        if ($schedule === null) {
            throw new RuntimeException('Não há agendamento pendente para este artigo.');
        }

        $when = $this->parseFutureDate($dateTimeLocal);
        $this->assertAuthor($authorId, $siteId);
        $this->assertFeaturedImage($imageId, $articleId);

        Connection::get()->prepare(
            'UPDATE schedules SET author_id = :au, image_id = :img, scheduled_date = :d
             WHERE id = :id'
        )->execute(['au' => $authorId, 'img' => $imageId, 'd' => $when, 'id' => (int) $schedule['id']]);
    }

    /**
     * Reagenda só a data (mantém o horário, autor e imagem) — usado pelo
     * arrastar-e-soltar do calendário (Fase 9, §71 pendência da Fase 7).
     * Mesma regra de "só no futuro" do reagendamento completo.
     *
     * @throws RuntimeException entrada inválida, sem agendamento pendente, ou nova data no passado
     */
    public function rescheduleDate(int $articleId, int $siteId, string $newDateYmd): void
    {
        if ($this->articles->find($siteId, $articleId) === null) {
            throw new RuntimeException('Artigo não encontrado.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDateYmd)) {
            throw new RuntimeException('Data inválida.');
        }

        $schedule = $this->activeForArticle($articleId);
        if ($schedule === null) {
            throw new RuntimeException('Não há agendamento pendente para este artigo.');
        }

        $time = substr((string) $schedule['scheduled_date'], 11); // "HH:MM:SS"

        try {
            $when = new DateTimeImmutable($newDateYmd . ' ' . $time);
        } catch (Exception) {
            throw new RuntimeException('Data inválida.');
        }

        if ($when < new DateTimeImmutable('now')) {
            throw new RuntimeException('A nova data precisa estar no futuro.');
        }

        Connection::get()->prepare('UPDATE schedules SET scheduled_date = :d WHERE id = :id')
            ->execute(['d' => $when->format('Y-m-d H:i:00'), 'id' => (int) $schedule['id']]);
    }

    /** Cancela o agendamento vigente e devolve o artigo para APPROVED. */
    public function cancel(int $articleId, int $siteId): void
    {
        $article = $this->articles->find($siteId, $articleId);
        if ($article === null || $article['status'] !== 'SCHEDULED') {
            throw new RuntimeException('O artigo não está agendado.');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE schedules SET status = 'CANCELED' WHERE article_id = :a AND status = 'PENDING'"
            )->execute(['a' => $articleId]);

            $this->articles->setStatus($articleId, 'APPROVED');
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function parseFutureDate(string $dateTimeLocal): string
    {
        $value = trim($dateTimeLocal);
        if ($value === '') {
            throw new RuntimeException('Informe a data e a hora da publicação.');
        }

        try {
            $when = new DateTimeImmutable(str_replace('T', ' ', $value));
        } catch (Exception) {
            throw new RuntimeException('Data/hora inválida.');
        }

        if ($when < new DateTimeImmutable('now')) {
            throw new RuntimeException('A data de publicação precisa estar no futuro.');
        }

        return $when->format('Y-m-d H:i:00');
    }

    private function assertAuthor(int $authorId, int $siteId): void
    {
        $stmt = Connection::get()->prepare(
            'SELECT 1 FROM site_authors WHERE id = :id AND site_id = :s AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $authorId, 's' => $siteId]);
        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('Selecione um autor válido do site.');
        }
    }

    private function assertFeaturedImage(int $imageId, int $articleId): void
    {
        $stmt = Connection::get()->prepare(
            "SELECT 1 FROM images
             WHERE id = :id AND article_id = :a AND role = 'FEATURED' AND selected = 1 LIMIT 1"
        );
        $stmt->execute(['id' => $imageId, 'a' => $articleId]);
        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('Escolha a imagem destacada antes de agendar.');
        }
    }
}

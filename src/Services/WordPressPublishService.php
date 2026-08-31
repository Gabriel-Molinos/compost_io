<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Integrations\WordPress\InternalLinkResolver;
use App\Integrations\WordPress\WordPressException;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * Envio de um artigo agendado ao WordPress (RF-012, fluxo-editorial §31, Fase 7.5).
 * Disparado por botão manual. Cria o post como `future` (o WordPress publica
 * sozinho na data) ou `publish` se a data já chegou. Sobe só a imagem destacada
 * (imagens de corpo = ajuste futuro).
 *
 *   SCHEDULED --publicar--> PUBLISHED   (schedules: PENDING -> PUBLISHED + wordpress_post_id)
 */
final class WordPressPublishService
{
    public function __construct(
        private readonly WordPressConnectionService $connections = new WordPressConnectionService(),
        private readonly ArticleService $articles = new ArticleService(),
    ) {
    }

    /**
     * @return array{post_id:int, link:string, status:string, links_rewritten:int, links_unwrapped:int}
     * @throws RuntimeException|WordPressException
     */
    public function publish(int $articleId, int $siteId): array
    {
        $article = $this->articles->find($siteId, $articleId);
        if ($article === null) {
            throw new RuntimeException('Artigo não encontrado.');
        }
        if ($article['status'] !== 'SCHEDULED') {
            throw new RuntimeException('Só é possível publicar um artigo agendado (status atual: ' . $article['status'] . ').');
        }

        $pdo = Connection::get();

        $schedule = $this->activeSchedule($articleId);
        $version = $this->articles->latestVersion($articleId);
        if ($version === null || trim((string) $version['content']) === '') {
            throw new RuntimeException('O artigo não tem corpo para publicar.');
        }

        $client = $this->connections->client($siteId);

        // Links internos "chutados" pela IA: resolve contra o WP, remove os sem par
        $linkResult = (new InternalLinkResolver($client, (string) ($this->connections->forSite($siteId)['url'] ?? '')))
            ->resolve((string) $version['content']);
        $content = $linkResult['html'];

        // Imagem destacada -> media library
        $image = $this->image((int) $schedule['image_id'], $articleId);
        $media = $client->uploadMedia($image['bytes'], $image['filename'], $image['mime']);
        $mediaId = (int) ($media['id'] ?? 0);

        // status + data (o WP recebe a data no fuso do site via date_gmt)
        $when = new DateTimeImmutable((string) $schedule['scheduled_date']);
        $isFuture = $when > new DateTimeImmutable('now');
        $gmt = $when->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');

        $payload = [
            'title'   => (string) ($article['title'] ?? ''),
            'content' => $content,
            'status'  => $isFuture ? 'future' : 'publish',
            'date_gmt' => $gmt,
            'excerpt' => (string) ($article['meta_description'] ?? ''),
        ];
        if (!empty($article['slug'])) {
            $payload['slug'] = (string) $article['slug'];
        }
        if ($mediaId > 0) {
            $payload['featured_media'] = $mediaId;
        }
        $wpCategoryId = $this->wordpressCategoryId($article);
        if ($wpCategoryId !== null) {
            $payload['categories'] = [$wpCategoryId];
        }
        $wpAuthorId = $this->wordpressAuthorId((int) $schedule['author_id']);
        if ($wpAuthorId !== null) {
            $payload['author'] = $wpAuthorId;
        }

        try {
            $post = $client->createPost($payload);
        } catch (\Throwable $e) {
            if ($mediaId > 0) {
                try {
                    $client->deleteMedia($mediaId);
                } catch (\Throwable) {
                    // best-effort: não mascarar o erro original
                }
            }
            throw $e;
        }

        $postId = (int) ($post['id'] ?? 0);
        if ($postId === 0) {
            throw new WordPressException('O WordPress não retornou o ID do post criado.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE schedules SET status = 'PUBLISHED', wordpress_post_id = :p WHERE id = :id"
            )->execute(['p' => $postId, 'id' => (int) $schedule['id']]);

            $this->articles->setStatus($articleId, 'PUBLISHED');
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return [
            'post_id'         => $postId,
            'link'            => (string) ($post['link'] ?? ''),
            'status'          => (string) ($post['status'] ?? $payload['status']),
            'links_rewritten' => $linkResult['rewritten'],
            'links_unwrapped' => $linkResult['unwrapped'],
        ];
    }

    /** @return array<string, mixed> */
    private function activeSchedule(int $articleId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT * FROM schedules WHERE article_id = :a AND status = 'PENDING' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['a' => $articleId]);
        $schedule = $stmt->fetch();

        if ($schedule === false) {
            throw new RuntimeException('Não há agendamento pendente para este artigo.');
        }
        if (empty($schedule['image_id'])) {
            throw new RuntimeException('O agendamento não tem imagem destacada.');
        }

        return $schedule;
    }

    /** @return array{bytes:string, filename:string, mime:string} */
    private function image(int $imageId, int $articleId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT url, format FROM images WHERE id = :id AND article_id = :a LIMIT 1'
        );
        $stmt->execute(['id' => $imageId, 'a' => $articleId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException('Imagem destacada não encontrada.');
        }

        $path = dirname(__DIR__, 2) . '/public' . $row['url'];
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new RuntimeException('Arquivo da imagem destacada não está no disco: ' . $row['url']);
        }

        $ext = strtolower((string) ($row['format'] ?: pathinfo((string) $row['url'], PATHINFO_EXTENSION) ?: 'jpg'));
        $mime = match ($ext) {
            'png'          => 'image/png',
            'webp'         => 'image/webp',
            'gif'          => 'image/gif',
            'jpg', 'jpeg'  => 'image/jpeg',
            default        => 'image/jpeg',
        };

        return [
            'bytes'    => $bytes,
            'filename' => basename((string) $row['url']),
            'mime'     => $mime,
        ];
    }

    /** @param array<string, mixed> $article */
    private function wordpressCategoryId(array $article): ?int
    {
        if (empty($article['category_id'])) {
            return null;
        }
        $stmt = Connection::get()->prepare(
            'SELECT wordpress_category_id FROM categories WHERE id = :c LIMIT 1'
        );
        $stmt->execute(['c' => (int) $article['category_id']]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    private function wordpressAuthorId(int $authorId): ?int
    {
        $stmt = Connection::get()->prepare(
            'SELECT wordpress_author_id FROM site_authors WHERE id = :a LIMIT 1'
        );
        $stmt->execute(['a' => $authorId]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }
}

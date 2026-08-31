<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\ImageConverter;
use App\Integrations\WordPress\BodyImageInjector;
use App\Integrations\WordPress\InternalLinkResolver;
use App\Integrations\WordPress\WordPressClient;
use App\Integrations\WordPress\WordPressException;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * Envio de um artigo agendado ao WordPress (RF-012, fluxo-editorial §31, Fase 7.5).
 * Botão manual. Post criado como `future` (o WordPress publica sozinho na data)
 * ou `publish` se a data já chegou. Sobe a imagem destacada e as de corpo
 * (distribuídas entre as seções). Guarda os IDs de mídia criados para poder
 * atualizar/retirar depois sem deixar órfão.
 *
 *   SCHEDULED --publicar--> PUBLISHED
 *   PUBLISHED --atualizar--> PUBLISHED   (reenvia conteúdo/imagens ao mesmo post)
 *   PUBLISHED --retirar--> APPROVED      (post vai para a lixeira do WP)
 */
final class WordPressPublishService
{
    public function __construct(
        private readonly WordPressConnectionService $connections = new WordPressConnectionService(),
        private readonly ArticleService $articles = new ArticleService(),
    ) {
    }

    /**
     * @return array{post_id:int, link:string, status:string, links_rewritten:int, links_unwrapped:int, body_images:int}
     */
    public function publish(int $articleId, int $siteId): array
    {
        $article = $this->article($siteId, $articleId, 'SCHEDULED', 'Só é possível publicar um artigo agendado');
        $schedule = $this->pendingSchedule($articleId);
        $version = $this->body($articleId);
        $client = $this->connections->client($siteId);

        $built = $this->build($client, $siteId, $article, $schedule, (string) $version['content']);

        $when = new DateTimeImmutable((string) $schedule['scheduled_date']);
        $isFuture = $when > new DateTimeImmutable('now');

        $payload = $built['payload'] + [
            'status'   => $isFuture ? 'future' : 'publish',
            'date_gmt' => $when->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
        ];

        try {
            $post = $client->createPost($payload);
        } catch (\Throwable $e) {
            $this->deleteMedia($client, $built['media_ids']);
            throw $e;
        }

        $postId = (int) ($post['id'] ?? 0);
        if ($postId === 0) {
            $this->deleteMedia($client, $built['media_ids']);
            throw new WordPressException('O WordPress não retornou o ID do post criado.');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE schedules SET status = 'PUBLISHED', wordpress_post_id = :p, wp_media_ids = :m WHERE id = :id"
            )->execute([
                'p'  => $postId,
                'm'  => json_encode($built['media_ids']),
                'id' => (int) $schedule['id'],
            ]);
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
            'links_rewritten' => $built['links_rewritten'],
            'links_unwrapped' => $built['links_unwrapped'],
            'body_images'     => count($built['media_ids']) - ($built['has_featured'] ? 1 : 0),
        ];
    }

    /**
     * Reenvia conteúdo e imagens ao post já criado (sobrescreve edições feitas
     * direto no WordPress). Mantém status e data que o post tiver lá.
     *
     * @return array{post_id:int, links_rewritten:int, links_unwrapped:int, body_images:int}
     */
    public function update(int $articleId, int $siteId): array
    {
        $article = $this->article($siteId, $articleId, 'PUBLISHED', 'Só dá para atualizar um artigo publicado');
        $schedule = $this->publishedSchedule($articleId);
        $version = $this->body($articleId);
        $client = $this->connections->client($siteId);
        $postId = (int) $schedule['wordpress_post_id'];

        // Remove a mídia do envio anterior antes de subir a nova.
        $this->deleteMedia($client, $this->mediaIds($schedule));

        $built = $this->build($client, $siteId, $article, $schedule, (string) $version['content']);

        try {
            $client->updatePost($postId, $built['payload']);
        } catch (\Throwable $e) {
            $this->deleteMedia($client, $built['media_ids']);
            throw $e;
        }

        Connection::get()->prepare('UPDATE schedules SET wp_media_ids = :m WHERE id = :id')
            ->execute(['m' => json_encode($built['media_ids']), 'id' => (int) $schedule['id']]);

        return [
            'post_id'         => $postId,
            'links_rewritten' => $built['links_rewritten'],
            'links_unwrapped' => $built['links_unwrapped'],
            'body_images'     => count($built['media_ids']) - ($built['has_featured'] ? 1 : 0),
        ];
    }

    /**
     * Manda o post para a lixeira do WordPress e devolve o artigo para APPROVED.
     */
    public function retract(int $articleId, int $siteId): void
    {
        $article = $this->article($siteId, $articleId, 'PUBLISHED', 'O artigo não está publicado');
        $schedule = $this->publishedSchedule($articleId);
        $client = $this->connections->client($siteId);

        try {
            $client->deletePost((int) $schedule['wordpress_post_id']); // lixeira (recuperável)
        } catch (WordPressException $e) {
            // 404 = post já sumiu; 410 = já estava na lixeira. Nos dois casos o alvo já não está no ar.
            if ($e->httpStatus !== 404 && $e->httpStatus !== 410) {
                throw $e;
            }
        }
        $this->deleteMedia($client, $this->mediaIds($schedule));

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE schedules SET status = 'CANCELED', wordpress_post_id = NULL, wp_media_ids = NULL WHERE id = :id"
            )->execute(['id' => (int) $schedule['id']]);
            $this->articles->setStatus($articleId, 'APPROVED');
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // -----------------------------------------------------------------

    /**
     * Resolve links, sobe imagens e monta o payload comum a publish/update
     * (sem status/data).
     *
     * @param array<string,mixed> $article
     * @param array<string,mixed> $schedule
     * @return array{payload:array<string,mixed>, media_ids:list<int>, has_featured:bool, links_rewritten:int, links_unwrapped:int}
     */
    private function build(WordPressClient $client, int $siteId, array $article, array $schedule, string $rawContent): array
    {
        $links = (new InternalLinkResolver($client, (string) ($this->connections->forSite($siteId)['url'] ?? '')))
            ->resolve($rawContent);
        $content = $links['html'];

        $mediaIds = [];

        // Imagem destacada
        $featured = $this->imageFile((int) $schedule['image_id'], (int) $article['id'], required: true);
        $featuredMedia = $client->uploadMedia($featured['bytes'], $featured['filename'], $featured['mime']);
        $featuredMediaId = (int) ($featuredMedia['id'] ?? 0);
        if ($featuredMediaId > 0) {
            $mediaIds[] = $featuredMediaId;
        }

        // Imagens de corpo
        $bodyForInjection = [];
        foreach ($this->bodyImages((int) $article['id']) as $row) {
            try {
                $file = $this->imageFile((int) $row['id'], (int) $article['id'], required: false);
            } catch (RuntimeException) {
                continue;
            }
            $media = $client->uploadMedia($file['bytes'], $file['filename'], $file['mime']);
            $id = (int) ($media['id'] ?? 0);
            $src = (string) ($media['source_url'] ?? '');
            if ($id > 0 && $src !== '') {
                $mediaIds[] = $id;
                $bodyForInjection[] = ['src' => $src, 'alt' => (string) ($row['alt_text'] ?? '')];
            }
        }
        if ($bodyForInjection !== []) {
            $content = BodyImageInjector::inject($content, $bodyForInjection);
        }

        $payload = [
            'title'   => (string) ($article['title'] ?? ''),
            'content' => $content,
            'excerpt' => (string) ($article['meta_description'] ?? ''),
        ];
        if (!empty($article['slug'])) {
            $payload['slug'] = (string) $article['slug'];
        }
        if ($featuredMediaId > 0) {
            $payload['featured_media'] = $featuredMediaId;
        }
        $wpCategoryId = $this->wordpressCategoryId($article);
        if ($wpCategoryId !== null) {
            $payload['categories'] = [$wpCategoryId];
        }
        $wpAuthorId = $this->wordpressAuthorId((int) $schedule['author_id']);
        if ($wpAuthorId !== null) {
            $payload['author'] = $wpAuthorId;
        }

        return [
            'payload'         => $payload,
            'media_ids'       => $mediaIds,
            'has_featured'    => $featuredMediaId > 0,
            'links_rewritten' => $links['rewritten'],
            'links_unwrapped' => $links['unwrapped'],
        ];
    }

    /** @param array<string,mixed> $schedule @return list<int> */
    private function mediaIds(array $schedule): array
    {
        $decoded = json_decode((string) ($schedule['wp_media_ids'] ?? ''), true);

        return is_array($decoded) ? array_values(array_filter(array_map('intval', $decoded))) : [];
    }

    /** @param list<int> $ids */
    private function deleteMedia(WordPressClient $client, array $ids): void
    {
        foreach ($ids as $id) {
            try {
                $client->deleteMedia($id);
            } catch (\Throwable) {
                // best-effort
            }
        }
    }

    /** @return array<string,mixed> */
    private function article(int $siteId, int $articleId, string $expectedStatus, string $errorPrefix): array
    {
        $article = $this->articles->find($siteId, $articleId);
        if ($article === null) {
            throw new RuntimeException('Artigo não encontrado.');
        }
        if ($article['status'] !== $expectedStatus) {
            throw new RuntimeException($errorPrefix . ' (status atual: ' . $article['status'] . ').');
        }

        return $article;
    }

    /** @return array<string,mixed> */
    private function body(int $articleId): array
    {
        $version = $this->articles->latestVersion($articleId);
        if ($version === null || trim((string) $version['content']) === '') {
            throw new RuntimeException('O artigo não tem corpo para publicar.');
        }

        return $version;
    }

    /** @return array<string, mixed> */
    private function pendingSchedule(int $articleId): array
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

    /** @return array<string, mixed> */
    private function publishedSchedule(int $articleId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT * FROM schedules
             WHERE article_id = :a AND status = 'PUBLISHED' AND wordpress_post_id IS NOT NULL
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['a' => $articleId]);
        $schedule = $stmt->fetch();

        if ($schedule === false) {
            throw new RuntimeException('Este artigo não tem um post publicado no WordPress.');
        }

        return $schedule;
    }

    /** @return list<array<string,mixed>> */
    private function bodyImages(int $articleId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT id, url, format, alt_text FROM images
             WHERE article_id = :a AND role = 'BODY' ORDER BY id"
        );
        $stmt->execute(['a' => $articleId]);

        return $stmt->fetchAll();
    }

    /** @return array{bytes:string, filename:string, mime:string} */
    private function imageFile(int $imageId, int $articleId, bool $required): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT url, format FROM images WHERE id = :id AND article_id = :a LIMIT 1'
        );
        $stmt->execute(['id' => $imageId, 'a' => $articleId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException('Imagem não encontrada.');
        }

        $path = dirname(__DIR__, 2) . '/public' . $row['url'];
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new RuntimeException('Arquivo da imagem não está no disco: ' . $row['url']);
        }

        // Fotos de 3–4 MB estouravam o tempo da requisição no upload. Reduz antes.
        $reduced = ImageConverter::downscaleJpeg($bytes);
        if ($reduced !== $bytes) {
            return [
                'bytes'    => $reduced,
                'filename' => pathinfo((string) $row['url'], PATHINFO_FILENAME) . '.jpg',
                'mime'     => 'image/jpeg',
            ];
        }

        $ext = strtolower((string) ($row['format'] ?: pathinfo((string) $row['url'], PATHINFO_EXTENSION) ?: 'jpg'));
        $mime = match ($ext) {
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            default       => 'image/jpeg',
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

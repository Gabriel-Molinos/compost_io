<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

/**
 * Imagens de um artigo (tabela `images`, schema §87): as opções geradas pelo
 * Nano Banana e a escolha do Redator-Chefe (`selected`). Fase 5.
 */
final class ImageService
{
    /** @param 'FEATURED'|'BODY' $role */
    public function add(
        int $articleId,
        string $role,
        string $url,
        ?string $prompt,
        ?string $altText,
        string $format,
        ?string $aspectRatio = null,
    ): int {
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO images (article_id, url, role, alt_text, selected, prompt, format, aspect_ratio)
             VALUES (:a, :u, :r, :alt, 0, :p, :f, :ar)'
        )->execute([
            'a'   => $articleId,
            'u'   => mb_substr($url, 0, 1024),
            'r'   => $role === 'BODY' ? 'BODY' : 'FEATURED',
            'alt' => $altText !== null ? mb_substr($altText, 0, 500) : null,
            'p'   => $prompt,
            'f'   => mb_substr($format, 0, 10),
            'ar'  => $aspectRatio !== null ? mb_substr($aspectRatio, 0, 8) : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * `COALESCE(sort_order, id)`: imagem nunca reordenada manualmente (`sort_order`
     * NULL) cai pra ordem de criação — mesmo comportamento de antes da migration 0030,
     * pra toda imagem existente e pra FEATURED (que não usa `sort_order`).
     *
     * @return list<array<string, mixed>>
     */
    public function forArticle(int $articleId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, url, role, alt_text, selected, prompt, format, aspect_ratio, sort_order, created_at
             FROM images WHERE article_id = :a ORDER BY role DESC, COALESCE(sort_order, id), id'
        );
        $stmt->execute(['a' => $articleId]);

        return $stmt->fetchAll();
    }

    /**
     * Só as imagens de corpo, na ordem em que vão ser injetadas no artigo — o que
     * `WordPressPublishService::bodyImages()` também usa na publicação de verdade
     * (mesma query, mesma ordem: nunca a prévia mostrar uma ordem e a publicação
     * usar outra).
     *
     * @return list<array<string, mixed>>
     */
    public function bodyImagesOrdered(int $articleId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT id, url, alt_text, sort_order FROM images
             WHERE article_id = :a AND role = 'BODY'
             ORDER BY COALESCE(sort_order, id), id"
        );
        $stmt->execute(['a' => $articleId]);

        return $stmt->fetchAll();
    }

    /**
     * Redator-Chefe escolhe a ordem das imagens de corpo (pedido do responsável
     * 2026-09-28) — `$imageIdsInOrder` precisa ser EXATAMENTE o conjunto de imagens
     * BODY do artigo (nenhuma de fora, nenhuma faltando), senão não aplica nada:
     * meio-aplicado deixaria `sort_order` inconsistente (algumas imagens numeradas,
     * outras não, sem dar pra saber a intenção de quem reordenou).
     *
     * @param list<int> $imageIdsInOrder
     */
    public function reorderBody(int $articleId, array $imageIdsInOrder): bool
    {
        $current = array_column($this->bodyImagesOrdered($articleId), 'id');
        sort($current);
        $given = array_map('intval', $imageIdsInOrder);
        $givenSorted = $given;
        sort($givenSorted);
        if ($current === [] || $givenSorted !== $current) {
            return false;
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE images SET sort_order = :o WHERE id = :id AND article_id = :a');
            foreach ($given as $position => $imageId) {
                $stmt->execute(['o' => $position + 1, 'id' => $imageId, 'a' => $articleId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Proporção da imagem destacada em uso — a escolhida (`selected=1`), ou a
     * primeira opção FEATURED se ainda não houve escolha. Usada pra gerar
     * imagens de corpo na MESMA proporção da destacada (pedido do
     * Redator-Chefe 2026-09-22). `null` quando o artigo não tem nenhuma
     * imagem destacada ainda, ou quando a linha é antiga e não guardou a
     * proporção (migration 0025) — quem chama cai pro padrão 16:9 nesse caso.
     */
    public function featuredAspectRatio(int $articleId): ?string
    {
        $stmt = Connection::get()->prepare(
            "SELECT aspect_ratio FROM images
             WHERE article_id = :a AND role = 'FEATURED'
             ORDER BY selected DESC, id LIMIT 1"
        );
        $stmt->execute(['a' => $articleId]);
        $ar = $stmt->fetchColumn();

        return $ar !== false && $ar !== null && trim((string) $ar) !== '' ? (string) $ar : null;
    }

    /** @return array<string, mixed>|null */
    public function find(int $articleId, int $imageId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM images WHERE id = :id AND article_id = :a LIMIT 1'
        );
        $stmt->execute(['id' => $imageId, 'a' => $articleId]);

        return $stmt->fetch() ?: null;
    }

    /** Remove uma imagem (opção descartada pelo Redator-Chefe). @return array<string,mixed>|null a linha removida */
    public function delete(int $articleId, int $imageId): ?array
    {
        $row = $this->find($articleId, $imageId);
        if ($row === null) {
            return null;
        }

        Connection::get()->prepare('DELETE FROM images WHERE id = :id AND article_id = :a')
            ->execute(['id' => $imageId, 'a' => $articleId]);

        return $row;
    }

    public function countForArticle(int $articleId): int
    {
        $stmt = Connection::get()->prepare('SELECT COUNT(*) FROM images WHERE article_id = :a');
        $stmt->execute(['a' => $articleId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Marca uma imagem como escolhida, tirando a marca das outras do mesmo papel
     * (Redator-Chefe escolhe — Fase 5.4). Idempotente.
     */
    public function select(int $articleId, int $imageId): bool
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare('SELECT role FROM images WHERE id = :id AND article_id = :a LIMIT 1');
        $stmt->execute(['id' => $imageId, 'a' => $articleId]);
        $role = $stmt->fetchColumn();
        if ($role === false) {
            return false;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE images SET selected = 0 WHERE article_id = :a AND role = :r')
                ->execute(['a' => $articleId, 'r' => $role]);
            $pdo->prepare('UPDATE images SET selected = 1 WHERE id = :id')
                ->execute(['id' => $imageId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return true;
    }
}

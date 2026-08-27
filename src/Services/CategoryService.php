<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

final class CategoryService
{
    /** @return list<array<string, mixed>> */
    public function allForSite(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, site_id, name, guidelines FROM categories WHERE site_id = :s ORDER BY name'
        );
        $stmt->execute(['s' => $siteId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $siteId, int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM categories WHERE id = :id AND site_id = :s LIMIT 1'
        );
        $stmt->execute(['id' => $id, 's' => $siteId]);

        return $stmt->fetch() ?: null;
    }

    public function nameExists(int $siteId, string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM categories WHERE site_id = :s AND name = :n';
        $params = ['s' => $siteId, 'n' => $name];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(int $siteId, string $name, ?string $guidelines): int
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO categories (site_id, name, guidelines) VALUES (:s, :n, :g)'
        );
        $stmt->execute(['s' => $siteId, 'n' => $name, 'g' => $guidelines]);

        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, string $name, ?string $guidelines): void
    {
        Connection::get()
            ->prepare('UPDATE categories SET name = :n, guidelines = :g WHERE id = :id')
            ->execute(['n' => $name, 'g' => $guidelines, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        Connection::get()->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $id]);
    }
}

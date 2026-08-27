<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

final class SiteService
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return Connection::get()
            ->query('SELECT id, name, niche, language, is_active FROM sites ORDER BY name')
            ->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM sites WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO sites (name, niche, language, target_audience, tone, wordpress_url, is_active)
             VALUES (:name, :niche, :language, :audience, :tone, :url, :active)'
        );
        $stmt->execute($this->params($data));

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE sites SET name = :name, niche = :niche, language = :language,
                target_audience = :audience, tone = :tone, wordpress_url = :url, is_active = :active
             WHERE id = :id'
        );
        $stmt->execute($this->params($data) + ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function params(array $data): array
    {
        return [
            'name'     => trim((string) ($data['name'] ?? '')),
            'niche'    => self::nullable($data['niche'] ?? null),
            'language' => trim((string) ($data['language'] ?? 'pt-BR')) ?: 'pt-BR',
            'audience' => self::nullable($data['target_audience'] ?? null),
            'tone'     => self::nullable($data['tone'] ?? null),
            'url'      => self::nullable($data['wordpress_url'] ?? null),
            'active'   => !empty($data['is_active']) ? 1 : 0,
        ];
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

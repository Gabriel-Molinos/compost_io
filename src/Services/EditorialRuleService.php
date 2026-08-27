<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

final class EditorialRuleService
{
    public const TYPES = ['INTEREST', 'NON_INTEREST'];

    /**
     * Regras do site agrupadas por tipo.
     *
     * @return array{INTEREST: list<array<string,mixed>>, NON_INTEREST: list<array<string,mixed>>}
     */
    public function groupedForSite(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, site_id, type, description, intensity
             FROM editorial_rules WHERE site_id = :s ORDER BY type, description'
        );
        $stmt->execute(['s' => $siteId]);

        $grouped = ['INTEREST' => [], 'NON_INTEREST' => []];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[$row['type']][] = $row;
        }

        return $grouped;
    }

    /** @return array{INTEREST: int, NON_INTEREST: int} */
    public function countsForSite(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT type, COUNT(*) c FROM editorial_rules WHERE site_id = :s GROUP BY type'
        );
        $stmt->execute(['s' => $siteId]);

        $counts = ['INTEREST' => 0, 'NON_INTEREST' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['type']] = (int) $row['c'];
        }

        return $counts;
    }

    /** @return array<string, mixed>|null */
    public function find(int $siteId, int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM editorial_rules WHERE id = :id AND site_id = :s LIMIT 1'
        );
        $stmt->execute(['id' => $id, 's' => $siteId]);

        return $stmt->fetch() ?: null;
    }

    public function create(int $siteId, string $type, string $description, int $intensity): int
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO editorial_rules (site_id, type, description, intensity)
             VALUES (:s, :t, :d, :i)'
        );
        $stmt->execute(['s' => $siteId, 't' => $type, 'd' => $description, 'i' => $intensity]);

        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, string $type, string $description, int $intensity): void
    {
        Connection::get()->prepare(
            'UPDATE editorial_rules SET type = :t, description = :d, intensity = :i WHERE id = :id'
        )->execute(['t' => $type, 'd' => $description, 'i' => $intensity, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        Connection::get()->prepare('DELETE FROM editorial_rules WHERE id = :id')->execute(['id' => $id]);
    }
}

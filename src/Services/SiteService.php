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
            ->query('SELECT id, name, logo_path, niche, language, target_audience, tone, wordpress_url, is_active
                      FROM sites ORDER BY name')
            ->fetchAll();
    }

    /** Sites vinculados a um usuário (Redator-Chefe). @return list<array<string, mixed>> */
    public function forUser(int $userId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT s.id, s.name, s.logo_path, s.niche, s.language, s.target_audience, s.tone, s.wordpress_url, s.is_active
             FROM sites s
             JOIN user_site us ON us.site_id = s.id
             WHERE us.user_id = :id
             ORDER BY s.name'
        );
        $stmt->execute(['id' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Sites em destaque pra sidebar fora do contexto de um site (atalho de
     * navegação) — com cap, pra não listar 60+ links de uma vez quando a
     * plataforma escalar (§97 performance). Combinar com count*() abaixo
     * pra saber se "Ver todos" precisa aparecer.
     *
     * @return list<array{id: int, name: string}>
     */
    public function recentForSidebar(int $userId, bool $isAdmin, int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        $pdo = Connection::get();

        if ($isAdmin) {
            return $pdo->query("SELECT id, name FROM sites ORDER BY name LIMIT {$limit}")->fetchAll();
        }

        $stmt = $pdo->prepare(
            "SELECT s.id, s.name
             FROM sites s
             JOIN user_site us ON us.site_id = s.id
             WHERE us.user_id = :id
             ORDER BY s.name
             LIMIT {$limit}"
        );
        $stmt->execute(['id' => $userId]);

        return $stmt->fetchAll();
    }

    public function countAll(): int
    {
        return (int) Connection::get()->query('SELECT COUNT(*) FROM sites')->fetchColumn();
    }

    public function countForUser(int $userId): int
    {
        $stmt = Connection::get()->prepare(
            'SELECT COUNT(*) FROM sites s JOIN user_site us ON us.site_id = s.id WHERE us.user_id = :id'
        );
        $stmt->execute(['id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /** Site do tutorial guiado (layout/base.php) — o primeiro site vinculado ao usuário, ou null se nenhum. */
    public function firstIdForUser(int $userId): ?int
    {
        $stmt = Connection::get()->prepare(
            'SELECT s.id FROM sites s JOIN user_site us ON us.site_id = s.id
             WHERE us.user_id = :id ORDER BY s.name LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM sites WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function hasUser(int $siteId, int $userId): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT 1 FROM user_site WHERE site_id = :s AND user_id = :u LIMIT 1'
        );
        $stmt->execute(['s' => $siteId, 'u' => $userId]);

        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO sites (name, niche, language, target_audience, tone, editorial_identity, wordpress_url, is_active)
             VALUES (:name, :niche, :language, :audience, :tone, :identity, :url, :active)'
        );
        $stmt->execute($this->params($data));

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE sites SET name = :name, niche = :niche, language = :language,
                target_audience = :audience, tone = :tone, editorial_identity = :identity,
                wordpress_url = :url, is_active = :active
             WHERE id = :id'
        );
        $stmt->execute($this->params($data) + ['id' => $id]);
    }

    /**
     * Grava (ou limpa, com null) o caminho do logo já enviado/salvo por
     * Uploads::image()/fromLocalFile(). `$libraryFilename` é o nome do
     * arquivo de origem na biblioteca (`SiteLogoLibraryService`), ou null
     * quando a logo veio de upload manual — precisa ser gravado sempre
     * junto (nunca só `logo_path`), senão o seletor não sabe marcar essa
     * logo como ocupada pra outros sites (achado real 2026-09-15: sem
     * isso, dava pra escolher a mesma logo pra dois sites diferentes).
     */
    public function setLogo(int $siteId, ?string $path, ?string $libraryFilename = null): void
    {
        Connection::get()->prepare('UPDATE sites SET logo_path = :p, logo_library_filename = :f WHERE id = :id')
            ->execute(['p' => $path, 'f' => $libraryFilename, 'id' => $siteId]);
    }

    /**
     * Arquivos da biblioteca já em uso por OUTROS sites — pro seletor
     * excluir do que mostra (`$excludeSiteId` é o próprio site sendo
     * editado, cuja logo atual não deve se auto-bloquear).
     *
     * @return list<string>
     */
    public function usedLogoLibraryFilenames(?int $excludeSiteId = null): array
    {
        $sql = 'SELECT logo_library_filename FROM sites WHERE logo_library_filename IS NOT NULL';
        $params = [];
        if ($excludeSiteId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeSiteId;
        }
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);

        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Exclusão de verdade (não é soft-delete — `sites` não tem `deleted_at`,
     * diferente de `articles`). TODAS as tabelas com `site_id` têm
     * `ON DELETE CASCADE` (migration 0001 + seguintes) — apaga junto
     * categorias, metas, artigos, execuções de IA, conexão WordPress etc.
     * Irreversível; `SiteController::destroy()` exige o admin digitar o
     * nome exato do site antes de chamar isto (achado real 2026-09-15,
     * pedido explícito: precisa dar pra excluir site).
     */
    public function delete(int $id): void
    {
        Connection::get()->prepare('DELETE FROM sites WHERE id = :id')->execute(['id' => $id]);
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
            'identity' => self::nullable($data['editorial_identity'] ?? null),
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

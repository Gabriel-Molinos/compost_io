<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;

final class UserService
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return Connection::get()->query(
            'SELECT u.id, u.name, u.email, u.role, u.is_active, u.avatar_path,
                    (SELECT COUNT(*) FROM user_site us WHERE us.user_id = u.id) AS site_count
             FROM users u ORDER BY u.name'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, name, email, role, is_active, avatar_path FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function emailExists(string $email, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = ['email' => $email];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, string $plainPassword): int
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_active)
             VALUES (:name, :email, :hash, :role, :active)'
        );
        $stmt->execute([
            'name'   => trim((string) $data['name']),
            'email'  => trim((string) $data['email']),
            'hash'   => password_hash($plainPassword, PASSWORD_DEFAULT),
            'role'   => $data['role'] === 'ADMIN' ? 'ADMIN' : 'REDATOR_CHEFE',
            'active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Não mexe em `name` (pedido do responsável, 2026-09-08: nome é o único
     * dado de perfil que o próprio usuário controla — admin edita e-mail,
     * senha, perfil/papel e sites vinculados; ver self::updateName()).
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data, ?string $plainPassword): void
    {
        $sql = 'UPDATE users SET email = :email, role = :role, is_active = :active';
        $params = [
            'id'     => $id,
            'email'  => trim((string) $data['email']),
            'role'   => $data['role'] === 'ADMIN' ? 'ADMIN' : 'REDATOR_CHEFE',
            'active' => !empty($data['is_active']) ? 1 : 0,
        ];

        if ($plainPassword !== null && $plainPassword !== '') {
            $sql .= ', password_hash = :hash';
            $params['hash'] = password_hash($plainPassword, PASSWORD_DEFAULT);
        }

        $sql .= ' WHERE id = :id';
        Connection::get()->prepare($sql)->execute($params);
    }

    /** O próprio usuário troca o próprio nome (ProfileController) — admin não mexe mais nisso. */
    public function updateName(int $id, string $name): void
    {
        Connection::get()->prepare('UPDATE users SET name = :name WHERE id = :id')
            ->execute(['name' => trim($name), 'id' => $id]);
    }

    /** Grava (ou limpa, com null) o caminho da foto de perfil já enviada/salva por Uploads::image(). */
    public function setAvatar(int $userId, ?string $path): void
    {
        Connection::get()->prepare('UPDATE users SET avatar_path = :p WHERE id = :id')
            ->execute(['p' => $path, 'id' => $userId]);
    }

    /** @return list<int> */
    public function siteIdsFor(int $userId): array
    {
        $stmt = Connection::get()->prepare('SELECT site_id FROM user_site WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<int> $siteIds */
    public function syncSites(int $userId, array $siteIds): void
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $pdo->prepare('DELETE FROM user_site WHERE user_id = :id')->execute(['id' => $userId]);

            if ($siteIds !== []) {
                $insert = $pdo->prepare('INSERT INTO user_site (user_id, site_id) VALUES (:u, :s)');
                foreach (array_unique($siteIds) as $siteId) {
                    $insert->execute(['u' => $userId, 's' => $siteId]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

/**
 * Fontes confiáveis cadastradas pelo redator, por site (fluxo-editorial §21,
 * etapa "Validar fontes"). O passo `research` do pipeline consulta essa lista
 * como pool prioritário antes de recorrer só à memória de treinamento da IA
 * (docs/ai/research.md) — cadastro é sempre humano, nunca escrito pela IA.
 */
final class SiteSourceService
{
    /** @return list<array<string, mixed>> */
    public function allForSite(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, site_id, url, note, created_at FROM site_sources
             WHERE site_id = :s ORDER BY created_at DESC'
        );
        $stmt->execute(['s' => $siteId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $siteId, int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM site_sources WHERE id = :id AND site_id = :s LIMIT 1'
        );
        $stmt->execute(['id' => $id, 's' => $siteId]);

        return $stmt->fetch() ?: null;
    }

    public function create(int $siteId, string $url, ?string $note): int
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO site_sources (site_id, url, note) VALUES (:s, :u, :n)'
        );
        $stmt->execute(['s' => $siteId, 'u' => $url, 'n' => $note]);

        return (int) $pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        Connection::get()->prepare('DELETE FROM site_sources WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Texto pronto pra injetar no prompt do passo `research`
     * (`PromptBuilder::setSiteSources()`) — null se o site não tem nenhuma
     * fonte cadastrada, pra `research.md` saber que não há pool nenhum.
     */
    public function digestForPrompt(int $siteId): ?string
    {
        $sources = $this->allForSite($siteId);
        if ($sources === []) {
            return null;
        }

        $lines = array_map(
            static function (array $s): string {
                $note = trim((string) ($s['note'] ?? ''));

                return '- ' . $s['url'] . ($note !== '' ? ' — ' . $note : '');
            },
            $sources,
        );

        return implode("\n", $lines);
    }
}

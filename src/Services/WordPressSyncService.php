<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Integrations\WordPress\WordPressClient;

/**
 * Sincroniza autores e categorias do WordPress de um site para o banco
 * (Fase 7.3). Autores: `site_authors` é espelho do WP. Categorias: casa por
 * nome com as categorias locais e grava `categories.wordpress_category_id`;
 * não cria nem apaga categorias.
 */
final class WordPressSyncService
{
    public function __construct(
        private readonly WordPressConnectionService $connections = new WordPressConnectionService(),
    ) {
    }

    /**
     * Espelha os autores do WP em `site_authors`. Quem sumiu do WP fica
     * `is_active = 0` (não apaga, para não quebrar `schedules` antigos).
     *
     * @return array{total:int, added:int, reactivated:int, updated:int, deactivated:int}
     */
    public function syncAuthors(int $siteId): array
    {
        $client = $this->connections->client($siteId);
        $wpAuthors = $this->fetchAll($client, 'listAuthors', ['who' => 'authors']);

        $pdo = Connection::get();

        /** @var array<int, array{name:string, is_active:int}> $existing */
        $existing = [];
        $stmt = $pdo->prepare(
            'SELECT wordpress_author_id, name, is_active FROM site_authors
             WHERE site_id = :s AND wordpress_author_id IS NOT NULL'
        );
        $stmt->execute(['s' => $siteId]);
        foreach ($stmt->fetchAll() as $row) {
            $existing[(int) $row['wordpress_author_id']] = [
                'name'      => (string) $row['name'],
                'is_active' => (int) $row['is_active'],
            ];
        }

        $added = $reactivated = $updated = 0;
        $seenIds = [];

        $upsert = $pdo->prepare(
            'INSERT INTO site_authors (site_id, wordpress_author_id, name, is_active)
             VALUES (:s, :wid, :n, 1)
             ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = 1'
        );

        foreach ($wpAuthors as $author) {
            $wid = (int) ($author['id'] ?? 0);
            if ($wid === 0) {
                continue;
            }
            $name = trim((string) ($author['name'] ?? ('Autor #' . $wid)));
            $seenIds[] = $wid;

            $upsert->execute(['s' => $siteId, 'wid' => $wid, 'n' => $name]);

            if (!isset($existing[$wid])) {
                $added++;
            } elseif ($existing[$wid]['is_active'] === 0) {
                $reactivated++;
            } elseif ($existing[$wid]['name'] !== $name) {
                $updated++;
            }
        }

        $deactivated = 0;
        $obsolete = array_diff(array_keys($existing), $seenIds);
        if ($obsolete !== []) {
            $in = implode(',', array_fill(0, count($obsolete), '?'));
            $params = array_merge([$siteId], array_values($obsolete));
            $pdo->prepare(
                "UPDATE site_authors SET is_active = 0
                 WHERE site_id = ? AND wordpress_author_id IN ({$in}) AND is_active = 1"
            )->execute($params);
            $deactivated = count(array_filter($obsolete, fn ($id) => $existing[$id]['is_active'] === 1));
        }

        return [
            'total'       => count($seenIds),
            'added'       => $added,
            'reactivated' => $reactivated,
            'updated'     => $updated,
            'deactivated' => $deactivated,
        ];
    }

    /**
     * Casa categorias do WP com as locais por nome normalizado (grava o
     * `wordpress_category_id`) e cria localmente as que só existem no WP.
     * Nunca apaga categoria local.
     *
     * A descrição da categoria no WordPress (campo `description`) vira as
     * diretrizes locais, mas SÓ quando a local ainda está vazia — texto que uma
     * pessoa escreveu nunca é sobrescrito.
     *
     * @return array{linked:int, already:int, imported:int, guidelines_filled:int, unmatched_local:list<string>}
     */
    public function syncCategories(int $siteId): array
    {
        $client = $this->connections->client($siteId);
        $wpCategories = $this->fetchAll($client, 'listCategories', []);

        /** @var array<string, array{id:int, name:string, description:string}> $wpByName */
        $wpByName = [];
        foreach ($wpCategories as $cat) {
            $id = (int) ($cat['id'] ?? 0);
            if ($id === 0) {
                continue;
            }
            $name = html_entity_decode((string) ($cat['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $description = trim(html_entity_decode(strip_tags((string) ($cat['description'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $wpByName[self::normalize($name)] = [
                'id'          => $id,
                'name'        => mb_substr(trim($name), 0, 191),
                'description' => $description,
            ];
        }

        $pdo = Connection::get();
        $stmt = $pdo->prepare('SELECT id, name, guidelines, wordpress_category_id FROM categories WHERE site_id = :s');
        $stmt->execute(['s' => $siteId]);
        $local = $stmt->fetchAll();

        $update = $pdo->prepare('UPDATE categories SET wordpress_category_id = :wid WHERE id = :id');

        $fillGuidelines = $pdo->prepare('UPDATE categories SET guidelines = :g WHERE id = :id');

        $linked = $already = $guidelinesFilled = 0;
        $unmatchedLocal = [];
        $matchedKeys = [];

        foreach ($local as $cat) {
            $key = self::normalize((string) $cat['name']);
            if (!isset($wpByName[$key])) {
                $unmatchedLocal[] = (string) $cat['name'];
                continue;
            }

            $matchedKeys[$key] = true;
            $wpId = $wpByName[$key]['id'];

            if ($wpByName[$key]['description'] !== '' && trim((string) ($cat['guidelines'] ?? '')) === '') {
                $fillGuidelines->execute(['g' => $wpByName[$key]['description'], 'id' => (int) $cat['id']]);
                $guidelinesFilled++;
            }

            if ((int) ($cat['wordpress_category_id'] ?? 0) === $wpId) {
                $already++;
                continue;
            }

            $update->execute(['wid' => $wpId, 'id' => (int) $cat['id']]);
            $linked++;
        }

        $insert = $pdo->prepare(
            'INSERT INTO categories (site_id, name, guidelines, wordpress_category_id) VALUES (:s, :n, :g, :wid)'
        );
        $imported = 0;
        foreach ($wpByName as $key => $wp) {
            if (isset($matchedKeys[$key])) {
                continue;
            }
            $insert->execute([
                's'   => $siteId,
                'n'   => $wp['name'],
                'g'   => $wp['description'] !== '' ? $wp['description'] : null,
                'wid' => $wp['id'],
            ]);
            $imported++;
            if ($wp['description'] !== '') {
                $guidelinesFilled++;
            }
        }

        return [
            'linked'          => $linked,
            'already'         => $already,
            'imported'        => $imported,
            'guidelines_filled' => $guidelinesFilled,
            'unmatched_local' => $unmatchedLocal,
        ];
    }

    /**
     * Contagens para exibir na tela (autores ativos, categorias vinculadas).
     *
     * @return array{authors_active:int, authors_inactive:int, categories_total:int, categories_linked:int}
     */
    public function counts(int $siteId): array
    {
        $pdo = Connection::get();

        $a = $pdo->prepare(
            'SELECT
                SUM(is_active = 1) AS active,
                SUM(is_active = 0) AS inactive
             FROM site_authors WHERE site_id = :s'
        );
        $a->execute(['s' => $siteId]);
        $authors = $a->fetch() ?: [];

        $c = $pdo->prepare(
            'SELECT COUNT(*) AS total, COUNT(wordpress_category_id) AS linked
             FROM categories WHERE site_id = :s'
        );
        $c->execute(['s' => $siteId]);
        $categories = $c->fetch() ?: [];

        return [
            'authors_active'    => (int) ($authors['active'] ?? 0),
            'authors_inactive'  => (int) ($authors['inactive'] ?? 0),
            'categories_total'  => (int) ($categories['total'] ?? 0),
            'categories_linked' => (int) ($categories['linked'] ?? 0),
        ];
    }

    /**
     * Percorre a paginação da REST API (100 por página) até esgotar.
     *
     * @param array<string, string|int> $extraQuery
     * @return list<array<string, mixed>>
     */
    private function fetchAll(WordPressClient $client, string $method, array $extraQuery): array
    {
        $all = [];
        for ($page = 1; $page <= 20; $page++) {
            $batch = $client->{$method}(['per_page' => 100, 'page' => $page] + $extraQuery);
            if ($batch === []) {
                break;
            }
            $all = array_merge($all, $batch);
            if (count($batch) < 100) {
                break;
            }
        }

        return $all;
    }

    /** Nome comparável: sem acento, sem entities, minúsculo, espaços colapsados. */
    private static function normalize(string $name): string
    {
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        if ($ascii !== false) {
            $name = $ascii;
        }
        $name = mb_strtolower($name, 'UTF-8');
        $name = preg_replace('/[^a-z0-9]+/i', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }
}

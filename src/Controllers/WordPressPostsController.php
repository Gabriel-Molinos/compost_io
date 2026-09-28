<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Integrations\WordPress\WordPressException;
use App\Services\WordPressConnectionService;
use App\Services\WordPressPostMirrorService;
use App\Services\WordPressSyncService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\ImageUploadValidator;
use App\Support\Session;
use App\View;
use InvalidArgumentException;

/**
 * "Todos os posts" (pedido do responsável 2026-09-28) — lista + edita QUALQUER
 * post do WordPress conectado, criado pelo COMPOST ou direto lá, sem o
 * Redator-Chefe precisar logar no wp-admin. Duas motivações reais: menos
 * gente logando no painel do WordPress (Application Password já é um
 * caminho de autenticação à parte — já houve invasão de site antes) e uma
 * cópia local de todo o conteúdo (`wordpress_posts_mirror`), pra não perder
 * nada numa invasão futura.
 *
 * A LISTA lê só o espelho local (rápido, funciona mesmo se o WordPress
 * estiver fora do ar no momento — apesar de "Sincronizar agora" e "Salvar"
 * precisarem do WordPress de verdade, como qualquer ação de escrita). A
 * EDIÇÃO busca o conteúdo mais recente ao vivo (`getPost()`) — o espelho é
 * pra listar/backup, não pra editar em cima de uma cópia que pode estar
 * desatualizada.
 */
final class WordPressPostsController extends Controller
{
    private WordPressConnectionService $connections;
    private WordPressPostMirrorService $mirror;
    private WordPressSyncService $sync;

    public function __construct()
    {
        $this->connections = new WordPressConnectionService();
        $this->mirror = new WordPressPostMirrorService();
        $this->sync = new WordPressSyncService($this->connections, $this->mirror);
    }

    public function index(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        $configured = $this->connections->hasCredential((int) $site['id']);

        $origin = (string) ($_GET['origin'] ?? '');
        $origin = in_array($origin, [WordPressPostMirrorService::ORIGIN_COMPOST, WordPressPostMirrorService::ORIGIN_EXTERNAL], true) ? $origin : null;

        $author = trim((string) ($_GET['author'] ?? ''));
        $author = $author !== '' ? $author : null;

        $imageParam = (string) ($_GET['image'] ?? '');
        $hasImage = match ($imageParam) {
            'none' => false,
            'with' => true,
            default => null,
        };

        $search = trim((string) ($_GET['q'] ?? ''));
        $search = $search !== '' ? mb_substr($search, 0, 191) : null;

        $filters = ['origin' => $origin, 'author' => $author, 'hasImage' => $hasImage, 'search' => $search];

        View::render('sites/wordpress-posts/index', [
            'title'        => 'Todos os posts · ' . $site['name'],
            'site'         => $site,
            'configured'   => $configured,
            'posts'        => $configured ? $this->mirror->listForSite((int) $site['id'], $filters) : [],
            'lastSyncedAt' => $configured ? $this->mirror->lastSyncedAt((int) $site['id']) : null,
            'originCounts' => $configured ? $this->mirror->originCounts((int) $site['id']) : ['all' => 0, 'compost' => 0, 'external' => 0],
            'authors'      => $configured ? $this->mirror->distinctAuthors((int) $site['id']) : [],
            'origin'       => $origin,
            'author'       => $author,
            'image'        => $imageParam,
            'search'       => $search,
            'filtersActive' => $origin !== null || $author !== null || $hasImage !== null || $search !== null,
        ]);
    }

    public function sync(string $siteId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();

        // Achado real 2026-09-28, testado ao vivo na Gavsy: site com ~170 posts paginando
        // 100 por vez (cada página é uma chamada HTTP de verdade ao WordPress, com _embed)
        // passa fácil do limite padrão de execução do PHP — sem isto, a sincronização morre
        // NO MEIO da paginação, silenciosamente (sem erro nenhum pro usuário), e a limpeza
        // dos posts removidos (pruneMissing) nunca chega a rodar.
        set_time_limit(300);
        @ini_set('max_execution_time', '300');

        try {
            $r = $this->sync->syncPostsMirror((int) $site['id']);
            Session::flash('success', sprintf(
                '%d post(s) sincronizado(s) — %d do COMPOST, %d feito(s) direto no WordPress.',
                $r['synced'], $r['compost'], $r['external'],
            ));
        } catch (WordPressException $e) {
            Session::flash('error', 'Falha ao sincronizar: ' . $e->getMessage());
        }

        Http::redirect('/sites/' . $site['id'] . '/wordpress-posts');
    }

    public function edit(string $siteId, string $postId): void
    {
        $site = $this->requireSite($siteId);
        $row = $this->mirror->find((int) $site['id'], (int) $postId);
        if ($row === null) {
            $this->notFound();
        }

        try {
            $client = $this->connections->client((int) $site['id']);
            $live = $client->getPost((int) $row['wordpress_post_id']);
            $categories = $client->listCategories();
            $authors = $client->listAuthors();
            $featuredUrl = null;
            $featuredMediaId = (int) ($live['featured_media'] ?? 0);
            if ($featuredMediaId > 0) {
                try {
                    $media = $client->getMedia($featuredMediaId);
                    $featuredUrl = (string) ($media['source_url'] ?? '') ?: null;
                } catch (WordPressException) {
                    // mídia removida direto no WordPress — segue sem prévia, não é motivo pra travar a edição
                }
            }
        } catch (WordPressException $e) {
            Session::flash('error', 'Não deu pra carregar o post mais recente do WordPress: ' . $e->getMessage());
            Http::redirect('/sites/' . $site['id'] . '/wordpress-posts');
            return;
        }

        View::render('sites/wordpress-posts/edit', [
            'title'       => 'Editar · ' . $row['title'] . ' · ' . $site['name'],
            'site'        => $site,
            'row'         => $row,
            'isCompost'   => $row['article_id'] !== null,
            'content'     => (string) ($live['content']['raw'] ?? $live['content']['rendered'] ?? $row['content']),
            'excerptRaw'  => (string) ($live['excerpt']['raw'] ?? $row['excerpt']),
            'titleRaw'    => (string) ($live['title']['raw'] ?? $row['title']),
            'authorId'    => (int) ($live['author'] ?? 0),
            'categoryIds' => array_map('intval', (array) ($live['categories'] ?? [])),
            'categories'  => $categories,
            'authors'     => $authors,
            'featuredUrl' => $featuredUrl,
            'errors'      => [],
        ]);
    }

    public function update(string $siteId, string $postId): void
    {
        $site = $this->requireSite($siteId);
        Csrf::verify();
        $row = $this->mirror->find((int) $site['id'], (int) $postId);
        if ($row === null) {
            $this->notFound();
        }
        $back = '/sites/' . $site['id'] . '/wordpress-posts/' . $postId . '/edit';

        $title = trim((string) ($_POST['title'] ?? ''));
        $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
        $content = (string) ($_POST['content'] ?? '');
        $authorId = (int) ($_POST['author_id'] ?? 0);
        $categoryId = (int) ($_POST['category_id'] ?? 0);

        if ($title === '') {
            Session::flash('error', 'O título não pode ficar vazio.');
            Http::redirect($back);
            return;
        }

        try {
            $client = $this->connections->client((int) $site['id']);

            $payload = [
                'title'      => $title,
                'excerpt'    => $excerpt,
                'content'    => $content,
                'author'     => $authorId > 0 ? $authorId : null,
                'categories' => $categoryId > 0 ? [$categoryId] : null,
            ];
            $payload = array_filter($payload, static fn ($v): bool => $v !== null);

            $featuredUrlForMirror = null;
            $file = $_FILES['featured_image'] ?? null;
            if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
                    throw new InvalidArgumentException('O envio da imagem destacada falhou. Tente de novo.');
                }
                $bytes = (string) file_get_contents((string) $file['tmp_name']);
                ImageUploadValidator::validate($bytes);
                $media = $client->uploadMedia($bytes, 'featured-' . uniqid() . '.webp', 'image/webp');
                $mediaId = (int) ($media['id'] ?? 0);
                if ($mediaId > 0) {
                    $payload['featured_media'] = $mediaId;
                    $featuredUrlForMirror = (string) ($media['source_url'] ?? '') ?: null;
                }
            }

            $updated = $client->updatePost((int) $row['wordpress_post_id'], $payload);

            // Autor/categoria em texto pro espelho local (pra lista mostrar sem outra chamada
            // ao abrir) — resolvidos por uma segunda leitura porque updatePost() não devolve
            // os nomes, só os ids.
            $authorName = $row['wordpress_author_name'] ?? null;
            if ($authorId > 0) {
                $match = array_filter($client->listAuthors(), static fn ($a) => (int) ($a['id'] ?? 0) === $authorId);
                $authorName = $match !== [] ? (string) (reset($match)['name'] ?? '') : $authorName;
            }
            $categoryNames = $categoryId > 0
                ? array_column(array_filter($client->listCategories(), static fn ($c) => (int) ($c['id'] ?? 0) === $categoryId), 'name')
                : (array) explode(', ', (string) ($row['wordpress_category_names'] ?? ''));

            $this->mirror->upsert((int) $site['id'], [
                'id'                 => (int) $row['wordpress_post_id'],
                'link'               => (string) ($updated['link'] ?? $row['link']),
                'slug'               => (string) ($updated['slug'] ?? $row['slug'] ?? ''),
                'status'             => (string) ($updated['status'] ?? $row['status']),
                'date_gmt'           => (string) ($updated['date_gmt'] ?? ''),
                'modified_gmt'       => (string) ($updated['modified_gmt'] ?? gmdate('Y-m-d\TH:i:s')),
                'title'              => $title,
                'excerpt'            => $excerpt !== '' ? $excerpt : trim(strip_tags($content)),
                'content'            => (string) ($updated['content']['rendered'] ?? $content),
                // Sem upload novo, mantém a imagem destacada que já estava no espelho — senão
                // "salvar sem trocar a imagem" apagava a featured_image_url sem querer.
                'featured_image_url' => $featuredUrlForMirror ?? ($row['featured_image_url'] !== null ? (string) $row['featured_image_url'] : null),
                'author_name'        => $authorName !== '' ? $authorName : null,
                'category_names'     => array_values(array_filter($categoryNames)),
            ], $row['article_id'] !== null ? (int) $row['article_id'] : null);

            Session::flash('success', 'Post atualizado no WordPress.');
            Http::redirect('/sites/' . $site['id'] . '/wordpress-posts');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            Http::redirect($back);
        } catch (WordPressException $e) {
            Session::flash('error', 'Falha ao salvar no WordPress: ' . $e->getMessage());
            Http::redirect($back);
        }
    }
}

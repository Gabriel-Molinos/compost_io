<?php

declare(strict_types=1);

/**
 * Mostra o prompt do passo `image` (brief visual) montado pelo PromptBuilder —
 * sem chamar nenhuma API (Fase 5.1). Se um article_id for passado, o corpo desse
 * artigo entra na camada "ARTIGO PRODUZIDO"; senão o prompt sai sem artigo.
 *
 *   php bin/image_brief_preview.php <site_id> [article_id]
 *
 * Exemplos:
 *   php bin/image_brief_preview.php 2
 *   php bin/image_brief_preview.php 2 7
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Env;
use App\Services\ArticleService;
use App\Services\PromptBuilder;

Env::load(dirname(__DIR__) . '/.env');

$siteId = isset($argv[1]) ? (int) $argv[1] : 0;
$articleId = isset($argv[2]) ? (int) $argv[2] : 0;

if ($siteId === 0) {
    fwrite(STDERR, "Uso: php bin/image_brief_preview.php <site_id> [article_id]\n");
    exit(1);
}

$draft = [];
if ($articleId !== 0) {
    $articles = new ArticleService();
    $article = $articles->find($siteId, $articleId);
    if ($article === null) {
        fwrite(STDERR, "Artigo {$articleId} não encontrado no site {$siteId}.\n");
        exit(1);
    }
    $version = $articles->latestVersion($articleId);
    if ($version === null) {
        fwrite(STDERR, "Artigo {$articleId} ainda não tem corpo (article_versions).\n");
        exit(1);
    }
    $draft = [
        'title'            => (string) ($article['title'] ?? ''),
        'slug'             => (string) ($article['slug'] ?? ''),
        'meta_description' => (string) ($article['meta_description'] ?? ''),
        'word_count'       => (int) ($version['word_count'] ?? 0),
        'content_html'     => (string) ($version['content'] ?? ''),
    ];
}

try {
    $prompt = (new PromptBuilder())->build('image', $siteId, null, null, [], $draft);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}

$chars = mb_strlen($prompt);
$words = str_word_count(strip_tags($prompt));

fwrite(STDERR, "--- prompt: image / site {$siteId}"
    . ($articleId !== 0 ? " / artigo {$articleId}" : ' / sem artigo')
    . " · {$chars} caracteres · ~{$words} palavras ---\n");
echo $prompt;

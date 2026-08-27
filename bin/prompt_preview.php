<?php

declare(strict_types=1);

/**
 * Mostra o prompt final montado pelo PromptBuilder para um site/passo — sem
 * chamar nenhuma API. Serve para revisar as camadas de contexto (Fase 4.1).
 *
 *   php bin/prompt_preview.php <step> <site_id> [goal_id] [category_id]
 *
 * Exemplos:
 *   php bin/prompt_preview.php planning 2
 *   php bin/prompt_preview.php writing 2 5 3
 *
 * Passos: planning | research | writing | seo | compliance | review
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Env;
use App\Services\PromptBuilder;

Env::load(dirname(__DIR__) . '/.env');

$step = $argv[1] ?? '';
$siteId = isset($argv[2]) ? (int) $argv[2] : 0;
$goalId = isset($argv[3]) ? (int) $argv[3] : null;
$categoryId = isset($argv[4]) ? (int) $argv[4] : null;

if ($step === '' || $siteId === 0) {
    fwrite(STDERR, "Uso: php bin/prompt_preview.php <step> <site_id> [goal_id] [category_id]\n");
    fwrite(STDERR, 'Passos: ' . implode(' | ', PromptBuilder::STEPS) . "\n");
    exit(1);
}

try {
    $prompt = (new PromptBuilder())->build($step, $siteId, $goalId, $categoryId, [
        'title'         => 'EXEMPLO — título de trabalho',
        'focus_keyword' => 'exemplo palavra-chave',
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}

$chars = mb_strlen($prompt);
$words = str_word_count(strip_tags($prompt));

fwrite(STDERR, "--- prompt: {$step} / site {$siteId} · {$chars} caracteres · ~{$words} palavras ---\n");
echo $prompt;

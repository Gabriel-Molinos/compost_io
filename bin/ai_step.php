<?php

declare(strict_types=1);

/**
 * Roda UM passo editorial de verdade: monta o prompt (PromptBuilder, 4.1) e
 * manda pro Gemini (4.2). Não grava nada no banco nem roda o pipeline — é só
 * para ver as duas peças funcionando juntas.
 *
 *   php bin/ai_step.php <step> <site_id> [goal_id] [category_id]
 *
 * Exemplos:
 *   php bin/ai_step.php planning 2 5
 *   php bin/ai_step.php research 2 5 3
 *
 * Passos: planning | research | writing | seo | compliance | review
 * Requer GEMINI_API_KEY no .env. Custo: uma chamada real ao modelo configurado.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Env;
use App\Integrations\AIException;
use App\Integrations\Gemini\GeminiConfig;
use App\Integrations\Gemini\GeminiProvider;
use App\Services\PromptBuilder;

Env::load(dirname(__DIR__) . '/.env');

$step = $argv[1] ?? '';
$siteId = isset($argv[2]) ? (int) $argv[2] : 0;
$goalId = isset($argv[3]) ? (int) $argv[3] : null;
$categoryId = isset($argv[4]) ? (int) $argv[4] : null;

if ($step === '' || $siteId === 0) {
    fwrite(STDERR, "Uso: php bin/ai_step.php <step> <site_id> [goal_id] [category_id]\n");
    fwrite(STDERR, 'Passos: ' . implode(' | ', PromptBuilder::STEPS) . "\n");
    exit(1);
}

try {
    $config = GeminiConfig::fromEnv();
    $prompt = (new PromptBuilder())->build($step, $siteId, $goalId, $categoryId, [
        'title'         => 'EXEMPLO — a IA vai propor o tema neste passo',
        'focus_keyword' => '',
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro ao montar: ' . $e->getMessage() . "\n");
    exit(1);
}

$promptChars = mb_strlen($prompt);
echo "Passo: {$step} · site {$siteId}" . ($goalId ? " · meta {$goalId}" : '') . ($categoryId ? " · categoria {$categoryId}" : '') . "\n";
echo "Modelo: {$config->model} · prompt: {$promptChars} caracteres\n\n";

try {
    $started = microtime(true);
    $result = (new GeminiProvider($config))->generateText($prompt);
    $elapsed = round(microtime(true) - $started, 1);
} catch (AIException $e) {
    $tag = $e->retryable ? ' (retryable)' : '';
    fwrite(STDERR, "Gemini falhou{$tag}: " . $e->getMessage() . "\n");
    exit(1);
}

echo "--- resposta ({$elapsed}s) ------------------------------------------------\n";
echo rtrim($result->text) . "\n";
echo "-------------------------------------------------------------------------\n";
echo "Tokens: prompt={$result->promptTokens} · raciocínio={$result->thoughtsTokens} · resposta={$result->outputTokens} · total={$result->totalTokens}\n";
echo "Modelo retornado: {$result->model}\n";

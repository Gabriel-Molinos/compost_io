<?php

declare(strict_types=1);

/**
 * Smoke test da integração Gemini (Fase 4.2): faz UMA chamada real à API,
 * pedindo saída estruturada, e imprime texto + contagem de tokens.
 *
 *   php bin/gemini_smoke.php
 *
 * Requer GEMINI_API_KEY no .env. Custo: ~alguns milhares de tokens no máximo.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Env;
use App\Integrations\AIException;
use App\Integrations\Gemini\GeminiConfig;
use App\Integrations\Gemini\GeminiProvider;

Env::load(dirname(__DIR__) . '/.env');

try {
    $config = GeminiConfig::fromEnv();
} catch (AIException $e) {
    fwrite(STDERR, 'Config: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Modelo: {$config->model}\n";
echo "Endpoint: {$config->generateContentUrl()}\n";
echo "Chave: " . substr($config->apiKey, 0, 4) . str_repeat('*', max(0, strlen($config->apiKey) - 4)) . "\n\n";

$schema = [
    'type'       => 'object',
    'properties' => [
        'ok'      => ['type' => 'boolean'],
        'message' => ['type' => 'string'],
    ],
    'required' => ['ok', 'message'],
];

$prompt = 'Responda em JSON com ok=true e uma saudação curta em português brasileiro no campo message.';

try {
    $started = microtime(true);
    $result = (new GeminiProvider($config))->generateJson($prompt, $schema, 'Você é um serviço de teste. Seja breve.');
    $elapsed = round((microtime(true) - $started) * 1000);
} catch (AIException $e) {
    $tag = $e->retryable ? ' (retryable)' : '';
    fwrite(STDERR, "FALHOU{$tag}: " . $e->getMessage() . "\n");
    fwrite(STDERR, "\nSe for 401/403: o valor de GEMINI_API_KEY pode não ser uma API key do Google AI Studio\n");
    fwrite(STDERR, "(formato esperado: começa com 'AIza'). Gere uma em https://aistudio.google.com/apikey\n");
    exit(1);
}

echo "OK em {$elapsed}ms\n";
echo "Texto bruto: {$result->text}\n";
echo 'JSON parseado: ' . var_export($result->json, true) . "\n";
echo "Tokens: prompt={$result->promptTokens} output={$result->outputTokens} thoughts={$result->thoughtsTokens} total={$result->totalTokens}\n";
echo "modelVersion: {$result->model}\n";

$assertions = [
    'retornou texto não-vazio'   => $result->text !== '',
    'JSON foi parseado'          => is_array($result->json),
    'campo ok presente'          => ($result->json['ok'] ?? null) === true,
    'contagem de tokens > 0'     => $result->totalTokens > 0,
];

$fail = 0;
echo "\n";
foreach ($assertions as $name => $ok) {
    echo ($ok ? '  ok   ' : '  FAIL ') . $name . "\n";
    $ok || $fail++;
}

echo "\n" . ($fail === 0 ? 'smoke test PASSOU' : "{$fail} falha(s)") . "\n";
exit($fail === 0 ? 0 : 1);

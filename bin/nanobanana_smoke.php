<?php

declare(strict_types=1);

/**
 * Faz UMA geração real de imagem pelo Nano Banana (Fase 5.2) e grava o arquivo,
 * para conferir a integração ponta a ponta. Não toca no banco.
 *
 *   php bin/nanobanana_smoke.php ["prompt em inglês"] [aspect_ratio] [size]
 *
 * Exemplos:
 *   php bin/nanobanana_smoke.php
 *   php bin/nanobanana_smoke.php "A calm minimalist desk with a laptop, soft light" 4:3 1K
 *
 * Requer IMAGE_API_KEY (ou GEMINI_API_KEY) no .env. Custo: uma imagem real
 * (~US$ 0,13 no gemini-3-pro-image, varia por resolução).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Env;
use App\Integrations\AIException;
use App\Integrations\Image\ImageRequest;
use App\Integrations\Image\NanoBanana\NanoBananaConfig;
use App\Integrations\Image\NanoBanana\NanoBananaProvider;

Env::load(dirname(__DIR__) . '/.env');

$prompt = $argv[1] ?? 'A serene mountain landscape at sunset, realistic photography, soft light';
$aspect = $argv[2] ?? '16:9';
$size = $argv[3] ?? (string) (NanoBananaConfig::fromEnv()->defaultSize);

try {
    $config = NanoBananaConfig::fromEnv();
    $request = new ImageRequest($prompt, $aspect, $size);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro de configuração: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Modelo: {$config->model} · {$aspect} · {$size}\n";
echo "Prompt: {$prompt}\n\n";

try {
    $started = microtime(true);
    $result = (new NanoBananaProvider($config))->generate($request);
    $elapsed = round(microtime(true) - $started, 1);
} catch (AIException $e) {
    $tag = $e->retryable ? ' (retryable)' : '';
    fwrite(STDERR, "Nano Banana falhou{$tag}: " . $e->getMessage() . "\n");
    exit(1);
}

$dir = sys_get_temp_dir();
$file = $dir . '/nanobanana_smoke_' . date('Ymd_His') . '.' . $result->extension();
file_put_contents($file, $result->bytes);

$kb = round(strlen($result->bytes) / 1024, 1);
echo "--- ok ({$elapsed}s) ---------------------------------------------------\n";
echo "Arquivo: {$file} ({$kb} KB, {$result->mimeType})\n";
echo "Modelo retornado: {$result->model}\n";
echo "Tokens: entrada={$result->inputTokens} · saída={$result->outputTokens} · total={$result->totalTokens}\n";

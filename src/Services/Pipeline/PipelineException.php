<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use RuntimeException;
use Throwable;

/** Falha em um passo do `ArticlePipeline`. Carrega o artigo já criado e o passo. */
final class PipelineException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $articleId,
        public readonly string $step,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

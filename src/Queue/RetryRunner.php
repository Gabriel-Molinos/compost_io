<?php

declare(strict_types=1);

namespace App\Queue;

use App\Integrations\AIException;

/**
 * Executa uma operação que pode falhar de forma transitória (timeout, 429, 5xx),
 * repetindo conforme a `RetryPolicy`. Só repete quando a exceção diz
 * `retryable = true`; erros definitivos (400/401/403, conteúdo bloqueado)
 * sobem na primeira ocorrência.
 */
final class RetryRunner
{
    public function __construct(private readonly RetryPolicy $policy = new RetryPolicy())
    {
    }

    /**
     * @template T
     * @param callable(int $attempt): T                       $operation
     * @param callable(int $attempt, AIException $e, int $delay): void|null $onRetry
     * @return T
     * @throws AIException se todas as tentativas falharem ou o erro não for retryable
     */
    public function run(callable $operation, ?callable $onRetry = null): mixed
    {
        $attempt = 0;

        while (true) {
            $attempt++;
            try {
                return $operation($attempt);
            } catch (AIException $e) {
                $isLast = $attempt >= $this->policy->maxAttempts;
                if (!$e->retryable || $isLast) {
                    throw $e;
                }

                $delay = $this->policy->delayAfter($attempt);
                if ($onRetry !== null) {
                    $onRetry($attempt, $e, $delay);
                }
                if ($delay > 0) {
                    sleep($delay);
                }
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Queue;

/**
 * Unidade de trabalho da fila. `type` identifica o handler; `payload` são os
 * dados serializáveis (ex.: `['article_id' => 12, 'step' => 'writing']`).
 */
final class Job
{
    /** @param array<string, scalar|null> $payload */
    public function __construct(
        public readonly string $type,
        public readonly array $payload = [],
        public readonly string $id = '',
    ) {
    }

    public function withId(string $id): self
    {
        return new self($this->type, $this->payload, $id);
    }
}

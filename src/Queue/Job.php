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

    /** Serializa para gravar na fila (Redis). */
    public function toJson(): string
    {
        return json_encode(
            ['id' => $this->id, 'type' => $this->type, 'payload' => $this->payload],
            JSON_THROW_ON_ERROR
        );
    }

    /** Reconstrói a partir do que foi lido da fila (Redis). */
    public static function fromJson(string $json): self
    {
        /** @var array{id: string, type: string, payload: array<string, scalar|null>} $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self($data['type'], $data['payload'] ?? [], $data['id'] ?? '');
    }
}

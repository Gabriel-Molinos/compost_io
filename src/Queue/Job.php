<?php

declare(strict_types=1);

namespace App\Queue;

/**
 * Unidade de trabalho da fila. `type` identifica o handler; `payload` são os
 * dados serializáveis (ex.: `['article_id' => 12, 'step' => 'writing']`).
 * `attempts` conta quantas vezes já foi reenfileirado após falha (Fase 9,
 * retry/dead-letter — ver `RedisQueueDriver::fail()`).
 */
final class Job
{
    /** @param array<string, scalar|null> $payload */
    public function __construct(
        public readonly string $type,
        public readonly array $payload = [],
        public readonly string $id = '',
        public readonly int $attempts = 0,
    ) {
    }

    public function withId(string $id): self
    {
        return new self($this->type, $this->payload, $id, $this->attempts);
    }

    public function withIncrementedAttempts(): self
    {
        return new self($this->type, $this->payload, $this->id, $this->attempts + 1);
    }

    /** Serializa para gravar na fila (Redis). */
    public function toJson(): string
    {
        return json_encode(
            ['id' => $this->id, 'type' => $this->type, 'payload' => $this->payload, 'attempts' => $this->attempts],
            JSON_THROW_ON_ERROR
        );
    }

    /** Reconstrói a partir do que foi lido da fila (Redis). */
    public static function fromJson(string $json): self
    {
        /** @var array{id: string, type: string, payload: array<string, scalar|null>, attempts?: int} $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self($data['type'], $data['payload'] ?? [], $data['id'] ?? '', $data['attempts'] ?? 0);
    }
}

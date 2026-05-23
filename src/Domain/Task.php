<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Domain;

/**
 * Plain domain entity — no framework coupling needed for demo scope.
 */
final class Task
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly ?string $notes,
        public readonly bool $done,
        public readonly int $createdAt,
    ) {}

    public static function new(string $title, ?string $notes = null): self
    {
        return new self(
            id: null,
            title: $title,
            notes: $notes,
            done: false,
            createdAt: time(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'notes' => $this->notes,
            'done' => $this->done,
            'created_at' => $this->createdAt,
        ];
    }
}

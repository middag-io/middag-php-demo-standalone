<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Domain\Doctrine;

use Middag\Framework\Persistence\Contract\EntityInterface;

/**
 * Data-Mapper task entity — the Symfony-Doctrine-style experience.
 *
 * Persistence-ignorant: it knows nothing about SQL, the table, or the
 * connection. {@see TaskMapper} translates rows to/from it and
 * {@see TaskRepository} persists it. This is the MIRROR of
 * {@see \Middag\Demo\Standalone\Domain\Eloquent\Task} over the SAME
 * `demo_tasks` table — the proof of paradigm parity.
 *
 * Mutable (like a Doctrine entity); identity is the only read-only piece.
 */
final class Task implements EntityInterface
{
    public function __construct(
        private ?int $id,
        public string $title,
        public ?string $notes = null,
        public string $status = 'open',
        public string $priority = 'normal',
        public ?string $dueOn = null,
        public int $createdAt = 0,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function markDone(): void
    {
        $this->status = 'done';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'notes' => $this->notes,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_on' => $this->dueOn,
            'created_at' => $this->createdAt,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}

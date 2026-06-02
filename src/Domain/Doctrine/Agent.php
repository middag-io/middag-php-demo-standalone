<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Domain\Doctrine;

use Middag\Framework\Persistence\Contract\EntityInterface;

/**
 * Data-Mapper agent entity — the Symfony-Doctrine-style experience.
 *
 * Persistence-ignorant reference data: {@see AgentMapper} translates rows and
 * {@see AgentRepository} persists it. Reached the data-mapper way, alongside the
 * active-record {@see \Middag\Demo\Standalone\Domain\Eloquent\Ticket} — the demo's
 * dual-ORM parity story. `role` drives capability gating.
 */
final class Agent implements EntityInterface
{
    public const ROLES = ['agent', 'supervisor', 'admin'];

    public function __construct(
        private ?int $id,
        public string $name,
        public string $email,
        public string $role = 'agent',
        public bool $active = true,
        public int $createdAt = 0,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor' || $this->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'active' => $this->active,
            'created_at' => $this->createdAt,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}

<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Domain\Doctrine;

use Middag\Framework\Persistence\Contract\EntityInterface;

/**
 * Data-Mapper SLA policy entity — read-mostly config joined into dashboards.
 *
 * Reached the data-mapper way ({@see SlaPolicyMapper} + {@see SlaPolicyRepository}).
 */
final class SlaPolicy implements EntityInterface
{
    public function __construct(
        private ?int $id,
        public string $name,
        public string $priority = 'normal',
        public int $responseMinutes = 60,
        public int $resolutionMinutes = 1440,
        public int $createdAt = 0,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'priority' => $this->priority,
            'response_minutes' => $this->responseMinutes,
            'resolution_minutes' => $this->resolutionMinutes,
            'created_at' => $this->createdAt,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}

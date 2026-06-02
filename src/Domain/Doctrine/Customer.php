<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Domain\Doctrine;

use Middag\Framework\Persistence\Contract\EntityInterface;

/**
 * Data-Mapper customer entity — the reporter on a ticket.
 *
 * Reached the data-mapper way ({@see CustomerMapper} + {@see CustomerRepository}).
 * Feeds the entity_picker live-search source on the ticket form.
 */
final class Customer implements EntityInterface
{
    public function __construct(
        private ?int $id,
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?string $company = null,
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
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'created_at' => $this->createdAt,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}

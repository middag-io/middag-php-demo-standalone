<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Form;

use Middag\Demo\Standalone\Domain\Doctrine\Customer;
use Middag\Demo\Standalone\Domain\Doctrine\CustomerRepository;
use Middag\Framework\Form\Contract\EntitySourceInterface;

/**
 * Entity source feeding a ticket form's customer entity-picker. Backed by the
 * data-mapper {@see CustomerRepository} (proving the picker works over either ORM
 * paradigm). Registered under `demo_customers` in DemoBootstrap::wireRuntime().
 */
final readonly class CustomerEntitySource implements EntitySourceInterface
{
    public function __construct(private CustomerRepository $customers) {}

    /** @return array<int, array{value: mixed, label: string}> */
    public function resolve(string $search = '', int $limit = 20): array
    {
        return array_map(
            static fn (Customer $c): array => [
                'value' => $c->getId(),
                'label' => $c->company !== null ? sprintf('%s (%s)', $c->name, $c->company) : $c->name,
            ],
            $this->customers->search($search, $limit),
        );
    }
}

<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Form;

use Middag\Demo\Standalone\Domain\Doctrine\Agent;
use Middag\Demo\Standalone\Domain\Doctrine\AgentRepository;
use Middag\Framework\Form\Contract\EntitySourceInterface;

/**
 * Entity source feeding a ticket form's assignee entity-picker. Backed by the
 * data-mapper {@see AgentRepository}. Registered under `demo_agents` in
 * DemoBootstrap::wireRuntime().
 */
final readonly class AgentEntitySource implements EntitySourceInterface
{
    public function __construct(private AgentRepository $agents) {}

    /** @return array<int, array{value: mixed, label: string}> */
    public function resolve(string $search = '', int $limit = 20): array
    {
        $term = mb_strtolower(trim($search));
        $agents = array_filter(
            $this->agents->latest(),
            static fn (Agent $a): bool => $term === '' || str_contains(mb_strtolower($a->name), $term),
        );

        return array_map(
            static fn (Agent $a): array => [
                'value' => $a->getId(),
                'label' => sprintf('%s (%s)', $a->name, $a->role),
            ],
            array_slice(array_values($agents), 0, $limit),
        );
    }
}

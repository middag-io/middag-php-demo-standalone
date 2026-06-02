<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http\Concern;

/**
 * Builds an `id => display-label` map from a list of data-mapper entities.
 *
 * Each entity exposes `toArray()` with `id` + `name` keys (the demo's
 * Agent/Customer/SlaPolicy entities all do). Controllers use this to resolve the
 * foreign-key ids on active-record tickets into human names in one request — the
 * dual-ORM read idiom the help-desk demo proves.
 */
trait MapsEntityLabels
{
    /**
     * @param list<object> $entities
     * @return array<int, string>
     */
    private function idLabelMap(array $entities): array
    {
        $map = [];
        foreach ($entities as $entity) {
            /** @var array<string, mixed> $data */
            $data = $entity->toArray();
            $map[(int) ($data['id'] ?? 0)] = (string) ($data['name'] ?? '');
        }

        return $map;
    }
}

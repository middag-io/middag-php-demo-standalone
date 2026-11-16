<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Navigation;

use Closure;
use Middag\Ui\Navigation\Contract\NavigationRegistryInterface;
use Middag\Ui\Navigation\NavigationNode;

/**
 * The demo's navigation registry. middag-io/ui ships the contract and the node
 * value object; no OSS library ships an implementation (a host adapter brings
 * its own), so the demo implements it: groups and sections hold items, items
 * point at named routes resolved by the given closure, and build() returns the
 * NavigationTreePayload the shell reads.
 */
final class DemoNavigationRegistry implements NavigationRegistryInterface
{
    /** @var array<string, array{key: string, label: string, icon: ?string, route: ?string, params: array<string, mixed>, weight: int, parent: ?string}> */
    private array $nodes = [];

    /** @var array<string, string> */
    private array $capabilities = [];

    /** @var array<string, bool> */
    private array $collapsible = [];

    /** @var array<string, true> */
    private array $drilldown = [];

    /** @var array<string, Closure> */
    private array $badges = [];

    /**
     * @param Closure(string, array<string, mixed>): string $url              route name + params => URL
     * @param list<string>                                  $heldCapabilities
     */
    public function __construct(
        private readonly Closure $url,
        private readonly array $heldCapabilities = [],
    ) {}

    public function group(string $key, string $label, ?string $icon = null, int $weight = 50): static
    {
        return $this->node($key, $label, $icon, null, [], $weight);
    }

    public function section(string $key, string $label, ?string $icon = null, int $weight = 50): static
    {
        return $this->node($key, $label, $icon, null, [], $weight);
    }

    public function item(string $key, string $label, string $route, array $routeParams = [], ?string $icon = null, int $weight = 50): static
    {
        return $this->node($key, $label, $icon, $route, $routeParams, $weight);
    }

    public function capability(string $nodeKey, string $capability): static
    {
        $this->capabilities[$nodeKey] = $capability;

        return $this;
    }

    public function collapsible(string $groupKey, bool $defaultOpen = false): static
    {
        $this->collapsible[$groupKey] = $defaultOpen;

        return $this;
    }

    public function drilldown(string $nodeKey): static
    {
        $this->drilldown[$nodeKey] = true;

        return $this;
    }

    public function badge(string $nodeKey, Closure $resolver): static
    {
        $this->badges[$nodeKey] = $resolver;

        return $this;
    }

    public function build(string $activeRoute): array
    {
        $activeKey = '';
        foreach ($this->nodes as $key => $node) {
            if ($node['route'] === $activeRoute) {
                $activeKey = $key;
            }
        }

        return [
            'tree' => array_map(static fn (NavigationNode $node): array => $node->jsonSerialize(), $this->children(null, $activeKey)),
            'activeKey' => $activeKey,
            'footer' => [],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function node(string $key, string $label, ?string $icon, ?string $route, array $params, int $weight): static
    {
        $dot = strrpos($key, '.');
        $this->nodes[$key] = [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'route' => $route,
            'params' => $params,
            'weight' => $weight,
            'parent' => $dot === false ? null : substr($key, 0, $dot),
        ];

        return $this;
    }

    /**
     * @return list<NavigationNode>
     */
    private function children(?string $parent, string $activeKey): array
    {
        $nodes = array_filter($this->nodes, fn (array $node): bool => $node['parent'] === $parent && $this->allowed($node['key']));
        uasort($nodes, static fn (array $a, array $b): int => $a['weight'] <=> $b['weight']);

        $built = [];
        foreach ($nodes as $key => $node) {
            $badge = isset($this->badges[$key]) ? (string) ($this->badges[$key])() : null;
            $built[] = new NavigationNode(
                key: $key,
                label: $node['label'],
                icon: $node['icon'],
                href: $node['route'] !== null ? ($this->url)($node['route'], $node['params']) : null,
                badge: $badge !== '' ? $badge : null,
                active: $key === $activeKey,
                drilldown: isset($this->drilldown[$key]),
                collapsible: isset($this->collapsible[$key]),
                defaultOpen: $this->collapsible[$key] ?? false,
                children: $this->children($key, $activeKey),
                weight: $node['weight'],
                capability: $this->capabilities[$key] ?? null,
            );
        }

        return $built;
    }

    private function allowed(string $key): bool
    {
        return !isset($this->capabilities[$key]) || \in_array($this->capabilities[$key], $this->heldCapabilities, true);
    }
}

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
use InvalidArgumentException;
use Middag\Ui\Navigation\Contract\NavigationRegistryInterface;
use Middag\Ui\Navigation\NavigationNode;
use Middag\Ui\Navigation\NavigationTree;
use Middag\Ui\Shared\ValueObject\Translatable;

/**
 * The demo's navigation registry. middag-io/ui ships the contract and the node
 * value object; no OSS library ships an implementation (a host adapter brings
 * its own), so the demo implements it: groups and sections hold items, items
 * point at named routes resolved by the given closure or at an external
 * http(s) URL used as given, and build() returns the NavigationTree the shell
 * reads. Labels are stored as given: a Translatable reaches the client as
 * {key, domain, params?} and is never resolved here.
 */
final class DemoNavigationRegistry implements NavigationRegistryInterface
{
    /** Lowercase http(s) prefix, a non-empty host, and no whitespace or control characters anywhere. */
    private const string EXTERNAL_HREF = '#^https?://[^/\s\x00-\x1F\x7F]+[^\s\x00-\x1F\x7F]*$#D';

    /** @var array<string, array{key: string, label: string|Translatable, icon: ?string, route: ?string, params: array<string, mixed>, href: ?string, weight: int, parent: ?string, entityType: ?string, badgeVariant: ?string, statusColor: ?string, target: ?string}> */
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

    public function group(
        string $key,
        string|Translatable $label,
        ?string $icon = null,
        int $weight = 50,
        ?string $entityType = null,
        ?string $badgeVariant = null,
        ?string $statusColor = null,
    ): static {
        return $this->node($key, $label, $icon, $weight, entityType: $entityType, badgeVariant: $badgeVariant, statusColor: $statusColor);
    }

    public function section(
        string $key,
        string|Translatable $label,
        ?string $icon = null,
        int $weight = 50,
        ?string $entityType = null,
        ?string $badgeVariant = null,
        ?string $statusColor = null,
    ): static {
        return $this->node($key, $label, $icon, $weight, entityType: $entityType, badgeVariant: $badgeVariant, statusColor: $statusColor);
    }

    /**
     * @param array<string, mixed> $routeParams
     *
     * @throws InvalidArgumentException when both or neither of $route and $href are given, when $href is given
     *                                  with a non-empty $routeParams, or when $href is not an absolute lowercase
     *                                  http:// or https:// URL
     */
    public function item(
        string $key,
        string|Translatable $label,
        ?string $route,
        array $routeParams = [],
        ?string $icon = null,
        int $weight = 50,
        ?string $entityType = null,
        ?string $badgeVariant = null,
        ?string $statusColor = null,
        ?string $target = null,
        ?string $href = null,
    ): static {
        $this->assertDestination($key, $route, $routeParams, $href);

        return $this->node($key, $label, $icon, $weight, $route, $routeParams, $href, $entityType, $badgeVariant, $statusColor, $target);
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

    public function build(string $activeRoute): NavigationTree
    {
        $activeKey = '';
        foreach ($this->nodes as $key => $node) {
            if ($node['route'] === $activeRoute) {
                $activeKey = $key;
            }
        }

        return new NavigationTree(
            tree: $this->children(null, $activeKey),
            activeKey: $activeKey,
        );
    }

    /**
     * @param array<string, mixed> $routeParams
     */
    private function assertDestination(string $key, ?string $route, array $routeParams, ?string $href): void
    {
        if (($route === null) === ($href === null)) {
            throw new InvalidArgumentException(sprintf("Navigation item '%s' needs exactly one of a route or an href.", $key));
        }
        if ($href === null) {
            return;
        }
        if ($routeParams !== []) {
            throw new InvalidArgumentException(sprintf("Navigation item '%s' takes no route parameters with an href.", $key));
        }
        if (preg_match(self::EXTERNAL_HREF, $href) !== 1) {
            throw new InvalidArgumentException(sprintf("Navigation item '%s' href must be an absolute URL starting with http:// or https://.", $key));
        }
    }

    /**
     * @param array<string, mixed> $routeParams
     */
    private function node(
        string $key,
        string|Translatable $label,
        ?string $icon,
        int $weight,
        ?string $route = null,
        array $routeParams = [],
        ?string $href = null,
        ?string $entityType = null,
        ?string $badgeVariant = null,
        ?string $statusColor = null,
        ?string $target = null,
    ): static {
        $dot = strrpos($key, '.');
        $this->nodes[$key] = [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'route' => $route,
            'params' => $routeParams,
            'href' => $href,
            'weight' => $weight,
            'parent' => $dot === false ? null : substr($key, 0, $dot),
            'entityType' => $entityType,
            'badgeVariant' => $badgeVariant,
            'statusColor' => $statusColor,
            'target' => $target,
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
                href: $node['href'] ?? ($node['route'] !== null ? ($this->url)($node['route'], $node['params']) : null),
                badge: $badge !== '' ? $badge : null,
                active: $key === $activeKey,
                drilldown: isset($this->drilldown[$key]),
                collapsible: isset($this->collapsible[$key]),
                defaultOpen: $this->collapsible[$key] ?? false,
                children: $this->children($key, $activeKey),
                weight: $node['weight'],
                capability: $this->capabilities[$key] ?? null,
                entityType: $node['entityType'],
                badgeVariant: $node['badgeVariant'],
                statusColor: $node['statusColor'],
                target: $node['target'],
            );
        }

        return $built;
    }

    private function allowed(string $key): bool
    {
        return !isset($this->capabilities[$key]) || \in_array($this->capabilities[$key], $this->heldCapabilities, true);
    }
}

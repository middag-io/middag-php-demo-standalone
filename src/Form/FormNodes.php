<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Form;

use InvalidArgumentException;
use Middag\Ui\Form\Contract\FormSchemaNodeInterface;
use Middag\Ui\Form\FormFieldNode;
use Middag\Ui\Form\FormGroupNode;
use Middag\Ui\Form\FormHeaderNode;
use Middag\Ui\Form\FormSectionNode;
use Middag\Ui\Shared\Enum\FormComponent;

/**
 * Turns the schema the framework form renderer emits (loose arrays) into
 * middag-io/ui form schema nodes, so every form_panel is fed value objects.
 */
final class FormNodes
{
    private function __construct() {}

    /**
     * @param array<int, array<string, mixed>> $schema rendered schema nodes
     *
     * @return list<FormSchemaNodeInterface>
     */
    public static function fromRendered(array $schema): array
    {
        return array_values(array_map(self::node(...), $schema));
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function node(array $node): FormSchemaNodeInterface
    {
        return match ($node['kind'] ?? null) {
            'field' => new FormFieldNode(
                (string) $node['key'],
                FormComponent::from((string) $node['component']),
                (array) ($node['props'] ?? []),
            ),
            'section' => new FormSectionNode(
                (string) $node['id'],
                (string) $node['label'],
                self::fromRendered((array) ($node['children'] ?? [])),
                (bool) ($node['defaultCollapsed'] ?? false),
            ),
            'group' => new FormGroupNode(
                (string) $node['id'],
                self::fromRendered((array) ($node['children'] ?? [])),
                isset($node['columns']) ? (int) $node['columns'] : null,
            ),
            'header' => new FormHeaderNode((string) $node['label']),
            default => throw new InvalidArgumentException('Unknown form schema node kind: ' . json_encode($node['kind'] ?? null)),
        };
    }
}

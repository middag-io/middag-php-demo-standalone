<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Schema\HelpdeskSeeder;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Ui\Block\BlockDescriptor;
use Middag\Ui\EditablePanel\EditablePanelDescriptor;
use Middag\Ui\Region\PollConfig;
use Middag\Ui\Shared\Enum\HttpMethod;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Validates what the demo emits against the JSON Schema that middag-io/ui publishes
 * with the package (schema/page-contract.json and schema/fragment.json).
 *
 * The page contract of every route must match the schema root. The form nodes a
 * form_panel carries as free `data` are checked against their own definitions:
 * each schema node against FormSchemaNode (which covers FormFieldNode, the field
 * props, FormFieldValidation, FormCondition and the FormComponent enum). Several
 * of those ui types are schema-only, so this is the proof that the emitted forms
 * keep their shape.
 *
 * @internal
 */
#[CoversNothing]
final class WireSchemaTest extends DemoTestCase
{
    private const string SCHEMA_ID = 'https://ui-docs.middag.io/schemas/page-contract.json';

    private const string FRAGMENT_SCHEMA_ID = 'https://ui-docs.middag.io/schemas/fragment.json';

    protected function setUp(): void
    {
        parent::setUp();

        HelpdeskSeeder::seed($this->container->get(ConnectionAdapterInterface::class));
    }

    #[Test]
    #[DataProvider('routes')]
    public function pageContractMatchesThePublishedSchema(string $route): void
    {
        $contract = $this->contractOf($route);

        $this->assertValid($contract, self::SCHEMA_ID, $route);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function routes(): iterable
    {
        foreach (['/', '/tickets', '/tickets/1', '/tickets/1/edit', '/tickets/new', '/tickets/new?step=1', '/agents', '/agents/1', '/customers', '/parity', '/help', '/coverage', '/ui/page'] as $route) {
            yield $route => [$route];
        }
    }

    #[Test]
    public function loginPageContractMatchesThePublishedSchema(): void
    {
        $this->logout();

        $this->assertValid($this->contractOf('/login'), self::SCHEMA_ID, '/login');
    }

    #[Test]
    public function fragmentMatchesThePublishedFragmentSchema(): void
    {
        $fragment = $this->json($this->handle('GET', '/ui/fragment'));

        $this->assertValid($fragment, self::FRAGMENT_SCHEMA_ID, '/ui/fragment');
    }

    #[Test]
    public function formPanelNodesMatchFormSchemaNode(): void
    {
        $nodes = [];
        foreach (['/tickets/1/edit', '/tickets/new?step=1'] as $route) {
            foreach ($this->formPanels($this->contractOf($route)) as $panel) {
                foreach ((array) ($panel['data']['schema'] ?? []) as $node) {
                    $nodes[] = $node;
                }
            }
        }

        self::assertNotEmpty($nodes, 'the ticket forms emit schema nodes');
        foreach ($nodes as $node) {
            $this->assertValid($node, self::SCHEMA_ID . '#/$defs/FormSchemaNode', 'form node ' . ($node['key'] ?? '?'));
        }

        $components = array_column($nodes, 'component');
        self::assertContains('select', $components, 'option fields (OptionFieldProps) are covered');
        self::assertContains('entity_picker', $components);
        self::assertNotEmpty(array_filter($nodes, static fn (array $node): bool => isset($node['props']['validation'])), 'FormFieldValidation is covered');
        self::assertNotEmpty(array_filter($nodes, static fn (array $node): bool => isset($node['props']['required_when']) || isset($node['props']['visible_when'])), 'FormCondition is covered');
    }

    #[Test]
    public function closeActionResultMatchesActionResult(): void
    {
        $result = $this->json($this->handle('POST', '/tickets/1/close', [], ['HTTP_ACCEPT' => 'application/json']));

        $this->assertValid($result, self::SCHEMA_ID . '#/$defs/ActionResult', 'close action result');
    }

    #[Test]
    public function pollAndEditablePanelDescriptorsMatchTheSchema(): void
    {
        // Emission only: RegionBuilder cannot attach a poll config to a block,
        // and no free shell mounts the editable panel.
        $block = new BlockDescriptor('dense_table', 'queue', ['columns' => [], 'rows' => []], poll: new PollConfig('/tickets', 15000));
        $this->assertValid($block->jsonSerialize(), self::SCHEMA_ID . '#/$defs/BlockDescriptor', 'polled block');
        self::assertSame(15000, $block->jsonSerialize()['poll']['intervalMs'] ?? null);

        $panel = new EditablePanelDescriptor('/tickets/1/panel', '/tickets/1', HttpMethod::Put);
        $this->assertValid($panel->jsonSerialize(), self::SCHEMA_ID . '#/$defs/EditablePanelDescriptor', 'editable panel');
    }

    /**
     * @return array<string, mixed>
     */
    private function contractOf(string $route): array
    {
        $payload = $this->json($this->handle('GET', $route, [], ['HTTP_X_INERTIA' => 'true']));
        $contract = $payload['props']['contract'] ?? $payload;

        self::assertIsArray($contract, $route . ' emits a contract');

        return $contract;
    }

    /**
     * @param array<string, mixed> $contract
     *
     * @return list<array<string, mixed>>
     */
    private function formPanels(array $contract): array
    {
        $panels = [];
        $walk = static function (array $blocks) use (&$walk, &$panels): void {
            foreach ($blocks as $block) {
                if (($block['type'] ?? null) === 'form_panel') {
                    $panels[] = $block;
                }
                foreach ((array) ($block['data']['tabs'] ?? []) as $tab) {
                    $walk((array) ($tab['blocks'] ?? []));
                }
            }
        };
        foreach ((array) ($contract['layout']['regions'] ?? []) as $blocks) {
            $walk((array) $blocks);
        }

        return $panels;
    }

    private function assertValid(mixed $data, string $schemaRef, string $label): void
    {
        $result = $this->validator()->validate(json_decode((string) json_encode($data)), $schemaRef);

        self::assertTrue(
            $result->isValid(),
            $label . ' does not match ' . $schemaRef . ': ' . json_encode($result->error() instanceof ValidationError ? (new ErrorFormatter())->format($result->error()) : [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    private function validator(): Validator
    {
        $validator = new Validator();
        $validator->setMaxErrors(5);

        $resolver = $validator->resolver();
        \assert($resolver instanceof SchemaResolver);
        $schemaDir = $this->projectRoot() . '/vendor/middag-io/ui/schema';
        $resolver->registerFile(self::SCHEMA_ID, $schemaDir . '/page-contract.json');
        $resolver->registerFile(self::FRAGMENT_SCHEMA_ID, $schemaDir . '/fragment.json');

        return $validator;
    }

    private function logout(): void
    {
        $this->handle('POST', '/logout');
    }
}

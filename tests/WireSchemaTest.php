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
use Middag\Framework\Filesystem\Contract\FilesystemInterface;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use Middag\Ui\Block\BlockDescriptor;
use Middag\Ui\Region\PollConfig;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
        foreach (['/', '/tickets', '/tickets/1', '/tickets/1/edit', '/tickets/1/attachments', '/tickets/new', '/tickets/new?step=1', '/agents', '/agents/1', '/customers', '/parity', '/help', '/coverage', '/ui/page'] as $route) {
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

        $kinds = array_column($nodes, 'kind');
        self::assertContains('section', $kinds, 'the ticket form is laid out in sections');
        $fields = self::fieldNodes($nodes);
        $components = array_column($fields, 'component');
        self::assertContains('select', $components, 'option fields (OptionFieldProps) are covered');
        self::assertContains('entity_picker', $components);
        self::assertNotEmpty(array_filter($fields, static fn (array $node): bool => isset($node['props']['validation'])), 'FormFieldValidation is covered');
        self::assertNotEmpty(array_filter($fields, static fn (array $node): bool => isset($node['props']['required_when']) || isset($node['props']['visible_when'])), 'FormCondition is covered');
    }

    #[Test]
    public function closeActionResultMatchesActionResult(): void
    {
        $result = $this->json($this->handle('POST', '/tickets/1/close', [], ['HTTP_ACCEPT' => 'application/json']));

        $this->assertValid($result, self::SCHEMA_ID . '#/$defs/ActionResult', 'close action result');
    }

    #[Test]
    public function pollConfigMatchesTheSchema(): void
    {
        // Emission only: RegionBuilder cannot attach a poll config to a block.
        $block = new BlockDescriptor('dense_table', 'queue', ['columns' => [], 'rows' => []], poll: new PollConfig('/tickets', 15000));
        $this->assertValid($block->jsonSerialize(), self::SCHEMA_ID . '#/$defs/BlockDescriptor', 'polled block');
        self::assertSame(15000, $block->jsonSerialize()['poll']['intervalMs'] ?? null);
    }

    #[Test]
    public function agentEditablePanelMatchesTheSchema(): void
    {
        $this->container->get(AuthenticatorInterface::class)->login(1, ['name' => 'Supervisor', 'capabilities' => ['helpdesk:supervise']]);

        // The page config the agents page ships next to its contract.
        $props = $this->json($this->handle('GET', '/agents', [], ['HTTP_X_INERTIA' => 'true']))['props'];
        $this->assertValid($props['editablePanel'] ?? null, self::SCHEMA_ID . '#/$defs/EditablePanelDescriptor', 'agents editable panel');

        // The form the panel endpoint serves: FormSectionNode, FormGroupNode and
        // FormFieldNode, each valid as a FormSchemaNode.
        $descriptor = $this->json($this->handle('GET', '/agents/1/panel', [], ['HTTP_ACCEPT' => 'application/json']));
        self::assertNotEmpty($descriptor['schema'] ?? []);
        foreach ($descriptor['schema'] as $node) {
            $this->assertValid($node, self::SCHEMA_ID . '#/$defs/FormSchemaNode', 'panel node ' . ($node['id'] ?? '?'));
        }
    }

    #[Test]
    public function attachmentFileValueMatchesTheSchema(): void
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($tmp, 'wire');
        $upload = $this->handle('POST', '/tickets/1/attachments/upload', [], [], ['file' => new UploadedFile($tmp, 'wire.txt', null, null, true)]);
        $location = (string) $upload->headers->get('Location');
        self::assertStringContainsString('?token=', $location);

        // The pending upload comes back as the file field's FileValue, with its token.
        $contract = $this->contractOf($location);
        $this->assertValid($contract, self::SCHEMA_ID, 'attachments with a pending upload');
        $value = $this->formPanels($contract)[0]['data']['values']['file'] ?? null;
        self::assertIsArray($value);
        self::assertArrayHasKey('uploadToken', $value);
        $this->assertValid($value, self::SCHEMA_ID . '#/$defs/FileValue', 'pending upload file value');

        $path = 'attachments/' . $value['uploadToken'];
        $files = $this->container->get(FilesystemInterface::class);
        if ($files->exists($path)) {
            $files->delete($path);
        }
    }

    #[Test]
    public function flashedFieldErrorsMatchTheSharedPropsSchema(): void
    {
        $this->handle('POST', '/tickets', ['priority' => 'high'], ['HTTP_REFERER' => '/tickets/new']);
        $errors = $this->sharedErrorsOf('/tickets/new');

        $this->assertValid($errors, self::SCHEMA_ID . '#/$defs/SharedProps/properties/errors', 'flashed errors');
        $this->assertValid($errors, self::SCHEMA_ID . '#/$defs/FormErrors', 'flashed errors');
        self::assertIsArray($errors['subject'] ?? null, 'the missing subject is flashed as a structured message');
        foreach (array_is_list($errors['subject']) ? $errors['subject'] : [$errors['subject']] as $message) {
            $this->assertValid($message, self::SCHEMA_ID . '#/$defs/FieldMessage', 'flashed subject error');
        }
    }

    #[Test]
    public function flashedErrorsInANamedBagMatchTheFormErrorBagsSchema(): void
    {
        $this->handle('POST', '/tickets', ['priority' => 'high'], ['HTTP_REFERER' => '/tickets/new', 'HTTP_X_INERTIA_ERROR_BAG' => 'ticket']);
        $errors = $this->sharedErrorsOf('/tickets/new');

        self::assertSame(['ticket'], array_keys($errors), 'the field map is nested under the bag the request named');
        $this->assertValid($errors, self::SCHEMA_ID . '#/$defs/FormErrorBags', 'bagged errors');
        $this->assertValid($errors, self::SCHEMA_ID . '#/$defs/SharedProps/properties/errors', 'bagged errors');
        self::assertArrayHasKey('subject', $errors['ticket']);
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

    /**
     * @return array<string, mixed>
     */
    private function sharedErrorsOf(string $route): array
    {
        $errors = $this->json($this->handle('GET', $route, [], ['HTTP_X_INERTIA' => 'true']))['props']['errors'] ?? null;
        self::assertIsArray($errors);

        return $errors;
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

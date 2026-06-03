<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Command\CreateTicketCommand;
use Middag\Demo\Standalone\Domain\Eloquent\Ticket;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Help-desk ticket pages render as contract envelopes through the real kernel:
 * the list emits hand-built `variant`-keyed dense_table columns + metric cards;
 * the detail emits the field table + activity feed. Authenticated via DemoTestCase.
 *
 * @internal
 */
final class TicketContractTest extends DemoTestCase
{
    private function createTicket(string $subject, string $priority = 'normal'): int
    {
        $envelope = $this->container->get(MessageBusInterface::class)
            ->dispatch(new CreateTicketCommand(subject: $subject, priority: $priority, customerId: 1));

        return (int) $envelope->last(HandledStamp::class)?->getResult();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contentBlocks(string $path): array
    {
        $payload = $this->json($this->handle('GET', $path, [], ['HTTP_X_INERTIA' => 'true']));
        self::assertSame('Page', $payload['component']);

        // The list page uses dashboard (metric cards in `metrics`, table in
        // `content`); create/edit use stack (`content`); the sidebar detail page
        // uses `main`. Merge all three so callers find their blocks by key.
        $regions = $payload['props']['contract']['layout']['regions'] ?? [];

        return array_merge($regions['metrics'] ?? [], $regions['content'] ?? [], $regions['main'] ?? []);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return array<string, mixed>|null
     */
    private function blockByKey(array $blocks, string $key): ?array
    {
        foreach ($blocks as $block) {
            if (($block['key'] ?? '') === $key) {
                return $block;
            }
        }

        return null;
    }

    /**
     * Flatten the child blocks nested inside a `tabs` block's tabs[].blocks[].
     *
     * @param array<string, mixed>|null $tabsBlock
     * @return list<array<string, mixed>>
     */
    private function tabsNestedBlocks(?array $tabsBlock): array
    {
        $out = [];
        foreach ($tabsBlock['data']['tabs'] ?? [] as $tab) {
            foreach ($tab['blocks'] ?? [] as $block) {
                $out[] = $block;
            }
        }

        return $out;
    }

    #[Test]
    public function indexRendersDenseTableWithVariantColumnsAndMetricCards(): void
    {
        $this->createTicket('Login broken', 'high');

        $blocks = $this->contentBlocks('/tickets');

        $table = $this->blockByKey($blocks, 'tickets');
        self::assertNotNull($table, 'tickets list must contain the dense_table');
        self::assertSame('dense_table', $table['type']);

        // Cells are selected by the column `variant` (not `format`) — the whole point
        // of the hand-built table; the list is the rich-cell showcase.
        $variants = array_column($table['data']['columns'], 'variant', 'key');
        self::assertSame('status', $variants['status'] ?? null);
        self::assertSame('rich_status', $variants['priority'] ?? null);
        self::assertSame('annotated', $variants['assignee'] ?? null);
        self::assertSame('tag_chips', $variants['tags'] ?? null);
        self::assertSame('timestamp', $variants['created'] ?? null);
        self::assertSame('/tickets/{id}', $table['data']['rowHref'] ?? null);

        self::assertNotNull($this->blockByKey($blocks, 'total'), 'metric_card present');
        self::assertNotEmpty($table['data']['rows'], 'seeded ticket appears as a row');
    }

    #[Test]
    public function showRendersWorkflowProgressAndTabbedDetailActivitySla(): void
    {
        $id = $this->createTicket('Cannot reset password');

        $blocks = $this->contentBlocks('/tickets/' . $id);

        // workflow_progress (generic block escape hatch) reflects the ticket state.
        $state = $this->blockByKey($blocks, 'state');
        self::assertNotNull($state, 'ticket detail must contain the workflow_progress block');
        self::assertSame('workflow_progress', $state['type']);
        self::assertSame('new', $state['data']['currentState'] ?? null);
        self::assertNotEmpty($state['data']['states'] ?? [], 'workflow states populated');

        // tabbed_panel (wire type `tabs`) groups the detail/activity/sla blocks.
        $tabs = $this->blockByKey($blocks, 'tabs');
        self::assertNotNull($tabs, 'ticket detail must contain the tabbed_panel (tabs) block');
        self::assertSame('tabs', $tabs['type']);

        $nested = $this->tabsNestedBlocks($tabs);

        $detail = $this->blockByKey($nested, 'detail');
        self::assertNotNull($detail, 'Details tab carries the detail_panel');
        self::assertSame('detail_panel', $detail['type']);
        $fields = [];
        foreach ($detail['data']['sections'][0]['fields'] ?? [] as $field) {
            $fields[(string) $field['label']] = $field['value'];
        }
        self::assertSame('Cannot reset password', $fields['Subject'] ?? null);
        self::assertSame('new', $fields['Status'] ?? null);

        $activity = $this->blockByKey($nested, 'activity');
        self::assertNotNull($activity, 'Activity tab carries the activity_timeline');
        self::assertSame('activity_timeline', $activity['type']);

        $sla = $this->blockByKey($nested, 'sla');
        self::assertNotNull($sla, 'SLA tab carries the markdown_panel');
        self::assertSame('markdown_panel', $sla['type']);

        // The aside region (sidebar layout) carries the metric cards.
        $payload = $this->json($this->handle('GET', '/tickets/' . $id, [], ['HTTP_X_INERTIA' => 'true']));
        $aside = $payload['props']['contract']['layout']['regions']['aside'] ?? [];
        self::assertNotNull($this->blockByKey($aside, 'comments'), 'aside metric_card present');
        self::assertSame('sidebar', $payload['props']['contract']['layout']['template'] ?? null);
    }

    #[Test]
    public function newTicketRendersFormPanelWithEntityPickersAndConditionalAssignee(): void
    {
        $blocks = $this->contentBlocks('/tickets/new');

        $form = $this->blockByKey($blocks, 'ticket_form');
        self::assertNotNull($form, 'create page must contain the ticket form_panel');
        self::assertSame('form_panel', $form['type']);

        $components = [];
        $conditions = [];
        foreach ($form['data']['schema'] ?? [] as $field) {
            $components[$field['key'] ?? ''] = $field['component'] ?? null;
            if (isset($field['props']['required_when'])) {
                $conditions[(string) $field['key']] = $field['props']['required_when'];
            }
        }

        self::assertSame('entity_picker', $components['customer_id'] ?? null);
        self::assertSame('entity_picker', $components['agent_id'] ?? null);
        self::assertSame('select', $components['priority'] ?? null);
        // Assignee required_when priority is high/urgent (IN operator condition).
        self::assertSame('priority', $conditions['agent_id']['field'] ?? null);
    }

    #[Test]
    public function newTicketRendersTheWizardLayoutWithSteps(): void
    {
        $payload = $this->json($this->handle('GET', '/tickets/new', [], ['HTTP_X_INERTIA' => 'true']));
        $contract = $payload['props']['contract'];

        self::assertSame('wizard', $contract['layout']['template'] ?? null, 'create uses the wizard layout');
        $steps = $contract['layout']['meta']['steps'] ?? [];
        self::assertCount(2, $steps, 'the wizard has two steps');
        self::assertSame('active', $steps[0]['status'] ?? null, 'step 1 is active');
        self::assertSame('pending', $steps[1]['status'] ?? null, 'step 2 is pending');

        // Step 1 carries the required core and posts to the validating wizardStore.
        $form = $this->blockByKey($this->contentBlocks('/tickets/new'), 'ticket_form');
        self::assertNotNull($form);
        self::assertSame('/tickets/new', $form['data']['action'] ?? null);
        $keys = array_column($form['data']['schema'], 'key');
        self::assertContains('subject', $keys);
        self::assertContains('customer_id', $keys);
        self::assertNotContains('sla_policy_id', $keys, 'the schedule fields belong to step 2');
    }

    #[Test]
    public function wizardAdvancesThroughStepsAndCreatesWithMergedData(): void
    {
        // Step 1: post the required core. CreateTicketRequest validates it and the
        // controller stashes the validated core in the session, then 303s to step 2.
        $step1 = $this->handle('POST', '/tickets/new', [
            'subject' => 'Wizard ticket',
            'body' => 'opened via the wizard',
            'channel' => 'web',
            'priority' => 'normal',
            'customer_id' => 1,
        ]);
        self::assertSame(303, $step1->getStatusCode());

        // Step 2 GET: step 1 now completed, step 2 active; the form posts to confirm
        // and shows only the schedule fields.
        $contract = $this->json($this->handle('GET', '/tickets/new?step=2', [], ['HTTP_X_INERTIA' => 'true']))['props']['contract'];
        $steps = $contract['layout']['meta']['steps'] ?? [];
        self::assertSame('completed', $steps[0]['status'] ?? null);
        self::assertSame('active', $steps[1]['status'] ?? null);

        $form = $this->blockByKey($contract['layout']['regions']['content'] ?? [], 'ticket_form');
        self::assertNotNull($form);
        self::assertSame('/tickets/new/confirm', $form['data']['action'] ?? null);
        $keys = array_column($form['data']['schema'], 'key');
        self::assertContains('tags', $keys);
        self::assertNotContains('subject', $keys, 'step 2 shows only the schedule fields');

        // Step 2 submit: the optional fields merge onto the session core and create.
        $confirm = $this->handle('POST', '/tickets/new/confirm', [
            'tags' => 'wizard,demo',
            'sla_policy_id' => '',
            'due_at' => '',
        ]);
        self::assertSame(303, $confirm->getStatusCode());

        $created = Ticket::query()->where('subject', 'Wizard ticket')->get();
        self::assertCount(1, $created, 'the wizard created exactly one ticket');
        self::assertSame('wizard,demo', (string) $created[0]->tags, 'step-2 tags merged onto the step-1 core');
    }

    #[Test]
    public function storeCreatesTicketAndRedirects(): void
    {
        $response = $this->handle('POST', '/tickets', [
            'subject' => 'Printer offline',
            'body' => 'The 3rd floor printer is unreachable.',
            'priority' => 'normal',
            'channel' => 'web',
            'customer_id' => 1,
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertCount(1, Ticket::query()->where('subject', 'Printer offline')->get());
    }

    #[Test]
    public function editPrefillsFormPanelFromStoredTicket(): void
    {
        $id = $this->createTicket('Disk full');

        $form = $this->blockByKey($this->contentBlocks('/tickets/' . $id . '/edit'), 'ticket_form');
        self::assertNotNull($form);
        self::assertSame('Disk full', $form['data']['values']['subject'] ?? null);
    }

    #[Test]
    public function entityEndpointsServeCustomerAndAgentSources(): void
    {
        // Tables are empty in the test DB (only the demo user is seeded); the
        // endpoints must still answer with a `data` array the picker can map.
        foreach (['/api/entities/customers', '/api/entities/agents'] as $url) {
            $payload = $this->json($this->handle('GET', $url));
            self::assertArrayHasKey('data', $payload);
            self::assertIsArray($payload['data']);
        }
    }
}

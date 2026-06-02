<?php

declare(strict_types=1);

/**
 * Coverage manifest — the single source of truth for what free middag-io surface
 * this demo exercises, and where.
 *
 * `covered` maps a symbol (`kind:name`) to the route that proves it; CoverageManifestTest
 * boots each route and asserts the symbol is actually emitted (block types present,
 * cell variants present), that no PRO symbol leaks, and that no `gaps` symbol is
 * silently emitted. The /coverage page renders this same array — page and test never
 * drift because they read one file.
 *
 * `gaps` catalogs the honestly-unmet surface (emittable-but-unrenderable,
 * renderable-but-unemittable, PRO-only, or simply not yet exercised) with a reason,
 * so the demo never claims more than it proves.
 *
 * @return array{covered: array<string, array{kind: string, route: ?string, note: string}>, gaps: array<string, array{reason: string, filed: string}>}
 */

return [
    'covered' => [
        // Shells (2/2 free).
        'shell:basic' => ['kind' => 'shell', 'route' => '/', 'note' => 'login + every help-desk page'],
        'shell:immersive' => ['kind' => 'shell', 'route' => '/help', 'note' => 'help + coverage pages'],

        // Layouts (3/4 free — wizard is a catalogued gap).
        'layout:dashboard' => ['kind' => 'layout', 'route' => '/', 'note' => 'metrics + chart grid'],
        'layout:sidebar' => ['kind' => 'layout', 'route' => '/agents', 'note' => 'list + aside'],
        'layout:stack' => ['kind' => 'layout', 'route' => '/customers', 'note' => 'default single column'],

        // Blocks (13 free + 1 custom).
        'block:metric_card' => ['kind' => 'block', 'route' => '/', 'note' => 'queue metrics'],
        'block:status_strip' => ['kind' => 'block', 'route' => '/', 'note' => 'SLA health + score ring'],
        'block:dense_table' => ['kind' => 'block', 'route' => '/tickets', 'note' => 'the cell showcase'],
        'block:chart' => ['kind' => 'block', 'route' => '/', 'note' => 'custom registerBlock (inline SVG)'],
        'block:detail_panel' => ['kind' => 'block', 'route' => '/agents/{id}', 'note' => 'agent fields'],
        'block:link_list' => ['kind' => 'block', 'route' => '/agents/{id}', 'note' => 'assigned tickets'],
        'block:card_grid' => ['kind' => 'block', 'route' => '/customers', 'note' => 'customer cards'],
        'block:markdown_panel' => ['kind' => 'block', 'route' => '/help', 'note' => 'walkthrough'],
        'block:empty_state' => ['kind' => 'block', 'route' => '/help', 'note' => 'first-use nudge'],
        'block:action_grid' => ['kind' => 'block', 'route' => '/help', 'note' => 'quick links'],
        'block:form_panel' => ['kind' => 'block', 'route' => '/tickets/new', 'note' => 'framework form pipeline'],
        'block:workflow_progress' => ['kind' => 'block', 'route' => '/tickets/{id}', 'note' => 'status state machine'],
        'block:tabbed_panel' => ['kind' => 'block', 'route' => '/tickets/{id}', 'note' => 'emitted as wire type "tabs"; host aliases tabs->tabbed_panel + id->key'],
        'block:activity_timeline' => ['kind' => 'block', 'route' => '/tickets/{id}', 'note' => 'comment feed, nested in the tabbed_panel'],

        // dense_table cell renderers (9 of 9 free exercised + 1 custom sparkline).
        'cell:status' => ['kind' => 'cell', 'route' => '/tickets', 'note' => 'ticket status'],
        'cell:rich_status' => ['kind' => 'cell', 'route' => '/tickets', 'note' => 'priority {label,appearance,icon}'],
        'cell:annotated' => ['kind' => 'cell', 'route' => '/tickets', 'note' => 'assignee {text,sublabel}'],
        'cell:link_group' => ['kind' => 'cell', 'route' => '/tickets', 'note' => 'tags as chips'],
        'cell:timestamp' => ['kind' => 'cell', 'route' => '/tickets', 'note' => 'created date'],
        'cell:boolean' => ['kind' => 'cell', 'route' => '/parity', 'note' => 'parity match flag'],
        'cell:progress' => ['kind' => 'cell', 'route' => '/agents', 'note' => 'agent workload (supervisor view)'],
        'cell:link' => ['kind' => 'cell', 'route' => '/agents', 'note' => 'agent email as a mailto link, href interpolates {email} (supervisor view)'],
        'cell:html' => ['kind' => 'cell', 'route' => '/agents', 'note' => 'availability badge — server-controlled HTML, role escaped (supervisor view)'],
        'cell:sparkline' => ['kind' => 'cell', 'route' => '/agents', 'note' => 'custom registerCellRenderer (the chart-block seam, cell edition) — 7-day intake (supervisor view)'],

        // Form fields (the reachable PHP-factory ∩ React-free set used by the ticket form).
        'field:text' => ['kind' => 'field', 'route' => '/tickets/new', 'note' => 'subject, tags'],
        'field:textarea' => ['kind' => 'field', 'route' => '/tickets/new', 'note' => 'description'],
        'field:select' => ['kind' => 'field', 'route' => '/tickets/new', 'note' => 'channel, priority, SLA'],
        'field:entity_picker' => ['kind' => 'field', 'route' => '/tickets/new', 'note' => 'customer + assignee (live source)'],
        'field:date' => ['kind' => 'field', 'route' => '/tickets/new', 'note' => 'due date'],

        // Engine / providers.
        'engine:message_bus' => ['kind' => 'engine', 'route' => null, 'note' => 'sync command dispatch (create/update/transition/comment)'],
        'engine:async_signal' => ['kind' => 'engine', 'route' => null, 'note' => 'demo.ticket.created -> EscalateSlaCommand on the async transport'],
        'engine:dual_orm_parity' => ['kind' => 'engine', 'route' => '/parity', 'note' => 'data-mapper QueryBuilder vs active-record ModelQuery, identical counts'],
        'engine:entity_source' => ['kind' => 'engine', 'route' => '/tickets/new', 'note' => 'EntitySourceRegistry: demo_customers + demo_agents'],
        'engine:capability_gate' => ['kind' => 'provider', 'route' => '/agents', 'note' => 'helpdesk:supervise gates the supervisor columns'],
        'engine:register_block' => ['kind' => 'engine', 'route' => '/', 'note' => 'host registerBlock("chart") + tabs alias'],
        'condition:required_when_in' => ['kind' => 'engine', 'route' => '/tickets/new', 'note' => 'assignee required_when priority IN [high,urgent]'],
    ],

    'gaps' => [
        'layout:wizard' => ['reason' => 'ticket create uses a single-step form_panel; the FormStep wizard layout is not built', 'filed' => 'demo: wizard ticket create'],
        'block:pro' => ['reason' => 'PRO blocks (chart_panel, kanban_board, flow_editor, form_builder, condition_tree, sentence_builder) + the product shell ship in @middag-io/react-pro, out of the free OSS surface', 'filed' => 'n/a (by design)'],
        'field:duration' => ['reason' => 'emittable via FieldDefinition::duration() but no free React field renderer (would hit "Unknown field component")', 'filed' => 'react: duration renderer'],
        'field:otp_slider_native_select_tags' => ['reason' => 'React-renderable but no FieldDefinition factory method', 'filed' => 'framework: field factories'],
        'field:currency_rating_slug_color_phone_document' => ['reason' => 'React-renderable but absent from the PHP FieldType enum entirely', 'filed' => 'framework: field types'],
        'transport:persistent_bus' => ['reason' => 'only InMemoryTransport ships; bus-routed async cannot cross processes (the demo uses the in-memory worker, not a cross-process outbox)', 'filed' => 'framework: persistent transport'],
    ],
];

<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Http\Concern\RendersPages;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Ui\Page\PageBuilder;
use Middag\Ui\Region\RegionBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Help / about — the `immersive` shell showcase.
 *
 * Three blocks: a markdown_panel (the demo walkthrough), an empty_state (a
 * first-use nudge with a CTA — emitted via the generic block() escape hatch so
 * the CTA is the object shape `{label, href}` the React block reads, which the
 * typed emptyState() string `cta` arg cannot express), and an action_grid of
 * quick links into the rest of the demo.
 */
#[Auth(login: true)]
final class HelpController extends AbstractController
{
    use RendersPages;

    public function index(): Response
    {
        $contract = PageBuilder::page('demo.help')
            ->shell('immersive')
            ->layout('stack')
            ->title('Help & walkthrough')
            ->subtitle('What this demo proves, and where to look')
            ->region('content', function (RegionBuilder $region): void {
                $region->markdownPanel('readme', self::walkthroughMarkdown());

                // empty_state via generic block() → object-shaped cta {label, href}.
                $region->block('empty_state', 'getting_started', [
                    'variant' => 'first-use',
                    'icon' => 'sparkles',
                    'description' => 'New here? Open your first ticket to watch the form pipeline, dual-ORM writes and async SLA escalation in action.',
                    'cta' => ['label' => 'Open a ticket', 'href' => '/tickets/new'],
                ]);

                $region->actionGrid('links', [
                    self::link('go-dashboard', 'Dashboard', 'SLA health, metrics and the ticket trend chart.', 'Open dashboard', 'layout-dashboard', '/', 'primary'),
                    self::link('go-tickets', 'Tickets', 'The queue: hand-built cell renderers + the form pipeline.', 'Open tickets', 'inbox', '/tickets', 'secondary'),
                    self::link('go-parity', 'Dual-ORM parity', 'The same dataset read the data-mapper and active-record ways.', 'Open parity', 'columns', '/parity', 'secondary'),
                    self::link('go-coverage', 'Coverage', 'The CI-enforced surface manifest + the catalogued gaps.', 'Open coverage', 'shield-check', '/coverage', 'secondary'),
                ]);
            })
            ->build();

        return $this->page($contract);
    }

    /**
     * One canonical action_grid item (Action shape + card title/description).
     *
     * @return array<string, mixed>
     */
    private static function link(string $id, string $title, string $description, string $label, string $icon, string $href, string $intent): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'label' => $label,
            'icon' => $icon,
            'intent' => $intent,
            'target' => ['kind' => 'link', 'href' => $href],
        ];
    }

    private static function walkthroughMarkdown(): string
    {
        return "## Help-desk reference demo\n\n"
            . "A support desk built to prove the **free** middag-io surface end-to-end, "
            . "so the Moodle and WordPress adapters inherit a de-risked contract.\n\n"
            . "### What each page proves\n\n"
            . "- **Dashboard** — `dashboard` layout, metric cards, a status_strip and the custom `chart` block.\n"
            . "- **Tickets** — hand-built `dense_table` cell renderers; the form pipeline (entity pickers, conditional fields); the rich detail page (workflow_progress + tabbed detail/activity/SLA).\n"
            . "- **Agents** — `sidebar` layout with a capability-gated supervisor view.\n"
            . "- **Customers** — the `card_grid` block.\n"
            . "- **Dual-ORM parity** — the same dataset read two ways, asserted identical.\n"
            . "- **Coverage** — the CI-enforced manifest mapping every covered symbol to its proof route, plus the catalogued gaps.\n\n"
            . "### Run it\n\n"
            . "```\nphp -S localhost:8090 -t public public/index.php\ncd ui && npm run build:host\n```\n\n"
            . "Login: `demo@middag.io` / `middag`.";
    }
}

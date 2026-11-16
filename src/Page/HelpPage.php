<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Page;

use Middag\Ui\Page\AbstractPage;
use Middag\Ui\Page\Contract\PageContractInterface;
use Middag\Ui\Page\PageBuilder;
use Middag\Ui\Region\RegionBuilder;

/**
 * The help page as a page class (AbstractPage): its slug and its contract live
 * together, and HelpController only renders build().
 */
final class HelpPage extends AbstractPage
{
    public const SLUG = 'help';

    public function build(): PageContractInterface
    {
        return PageBuilder::page('demo.help')
            ->shell('immersive')
            ->layout('stack')
            ->title('Help & walkthrough')
            ->subtitle('What this demo proves, and where to look')
            ->region('content', function (RegionBuilder $region): void {
                $region->markdownPanel('readme', $this->walkthroughMarkdown());

                // empty_state via generic block() → object-shaped cta {label, href}.
                $region->block('empty_state', 'getting_started', [
                    'variant' => 'first-use',
                    'icon' => 'sparkles',
                    'description' => 'New here? Open your first ticket to watch the form pipeline, dual-ORM writes and async SLA escalation in action.',
                    'cta' => ['label' => 'Open a ticket', 'href' => '/tickets/new'],
                ]);

                $region->actionGrid('links', [
                    $this->link('go-dashboard', 'Dashboard', 'SLA health, metrics and the ticket trend chart.', 'Open dashboard', 'layout-dashboard', '/', 'primary'),
                    $this->link('go-tickets', 'Tickets', 'The queue: hand-built cell renderers + the form pipeline.', 'Open tickets', 'inbox', '/tickets', 'secondary'),
                    $this->link('go-parity', 'Dual-ORM parity', 'The same dataset read the data-mapper and active-record ways.', 'Open parity', 'columns', '/parity', 'secondary'),
                    $this->link('go-coverage', 'Coverage', 'The CI-enforced surface manifest + the catalogued gaps.', 'Open coverage', 'shield-check', '/coverage', 'secondary'),
                ]);
            })
            ->build();
    }

    /**
     * One canonical action_grid item (Action shape + card title/description).
     *
     * @return array<string, mixed>
     */
    private function link(string $id, string $title, string $description, string $label, string $icon, string $href, string $intent): array
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

    private function walkthroughMarkdown(): string
    {
        return "## Help-desk reference demo\n\n"
            . 'A support desk built to prove the **free** middag-io surface end-to-end, '
            . "so the Moodle and WordPress adapters inherit a de-risked contract.\n\n"
            . "### What each page proves\n\n"
            . "- **Dashboard** — `dashboard` layout, metric cards, a status_strip and a `chart` block.\n"
            . "- **Tickets** — hand-built `dense_table` cell renderers; the form pipeline (entity pickers, conditional fields); the rich detail page (status lifecycle + tabbed detail/activity/SLA).\n"
            . "- **Agents** — `sidebar` layout with a capability-gated supervisor view.\n"
            . "- **Customers** — the `card_grid` block.\n"
            . "- **Dual-ORM parity** — the same dataset read two ways, asserted identical.\n"
            . "- **Coverage** — the CI-enforced manifest mapping every covered symbol to its proof route, plus the catalogued gaps.\n\n"
            . "### Run it\n\n"
            . "```\nphp -S localhost:8090 -t public public/index.php\ncd ui && npm run build:host\n```\n\n"
            . 'Login: `demo@middag.io` / `middag`.';
    }
}

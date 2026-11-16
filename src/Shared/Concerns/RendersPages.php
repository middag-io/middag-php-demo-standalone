<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Shared\Concerns;

use Middag\Demo\Standalone\Observability\DebugCollector;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use Middag\Framework\Http\Inertia\InertiaFactory;
use Middag\Ui\Envelope\Contract\ContractEnvelopeInterface;
use Middag\Ui\Page\PageContract;
use Middag\Ui\Page\PageResources;
use Middag\Ui\Shared\ValueObject\Identity;
use Symfony\Component\HttpFoundation\Response;

/**
 * The contract-driven DX for a MIDDAG controller: build a `middag-io/ui`
 * contract, then `return $this->page($contract)`. The server describes the UI;
 * the client renders it. No hand-built prop arrays, no per-controller Inertia
 * wiring.
 *
 * Backed directly by the framework's `InertiaFactory::page()` bridge (G1 shipped
 * upstream): it folds the envelope into the reserved `contract` prop, derives the
 * component (PageContract → `Page`, Fragment → `Fragment`), and inherits the
 * partial-reload + entity normalization of InertiaResponse. The only demo-side
 * addition is the dev profiler hook.
 */
trait RendersPages
{
    /**
     * @param array<string, mixed> $extraProps
     */
    protected function page(ContractEnvelopeInterface $contract, array $extraProps = []): Response
    {
        DebugCollector::recordContract($contract); // dev profiler bar (no-op unless it renders)

        // Page resources: the signed-in user as an Identity and the capabilities
        // the session holds. The free renderer only types these; the payload is
        // what a host-aware client reads.
        if ($contract instanceof PageContract && !$contract->resources instanceof PageResources) {
            $contract = new PageContract($contract->shell, $contract->page, $contract->layout, $this->pageResources(), $contract->notifications, $contract->entities);
        }

        return InertiaFactory::page($contract, $extraProps, $this->request ?? null)->toResponse();
    }

    private function pageResources(): PageResources
    {
        $record = $this->getService(AuthenticatorInterface::class)->user();
        if (!is_array($record)) {
            return new PageResources();
        }

        /** @var array<string, mixed> $attributes */
        $attributes = is_array($record['attributes'] ?? null) ? $record['attributes'] : [];
        $held = is_array($attributes['capabilities'] ?? null) ? array_map(strval(...), $attributes['capabilities']) : [];

        return new PageResources(
            capabilities: array_fill_keys($held, true),
            user: new Identity(
                id: (string) ($record['id'] ?? ''),
                name: (string) ($attributes['name'] ?? ''),
                email: isset($attributes['email']) ? (string) $attributes['email'] : null,
            ),
        );
    }
}

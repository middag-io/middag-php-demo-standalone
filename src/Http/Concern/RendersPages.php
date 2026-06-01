<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http\Concern;

use Middag\Demo\Standalone\Framework\DebugCollector;
use Middag\Framework\Http\Inertia\InertiaFactory;
use Middag\Ui\Envelope\ContractEnvelopeInterface;
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

        return InertiaFactory::page($contract, $extraProps, $this->request ?? null)->toResponse();
    }
}

<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Framework;

use Middag\Ui\Envelope\ContractEnvelopeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dev profiler collector + debug bar (Fase 0). Records the emitted ui contract
 * and renders a fixed debug bar into HTML responses when APP_DEBUG is on.
 *
 * M10 (observability) is partially shipped upstream: the framework now has a
 * ProfileCollector + a HookManager profiling seam + a Bus ProfilingMiddleware.
 * This bar still shows only the contract + request context because wiring the bus
 * profiler is blocked by a residual gap — MessageBusFactory::create() has no
 * middleware extension point (FRAMEWORK-GAP G3), so ProfilingMiddleware can't be
 * injected without hand-building the bus. Left documented rather than worked
 * around; the hook/query profiler wiring is deferred follow-up.
 */
final class DebugCollector
{
    private static ?ContractEnvelopeInterface $contract = null;

    public static function recordContract(ContractEnvelopeInterface $contract): void
    {
        self::$contract = $contract;
    }

    public static function reset(): void
    {
        self::$contract = null;
    }

    /** Inject the dev debug bar before </body> of an HTML response. */
    public static function injectBar(string $html, Request $request, Response $response, float $elapsedSeconds): string
    {
        if (!str_contains($html, '</body>')) {
            return $html;
        }

        $rows = [
            'request' => $request->getMethod() . ' ' . $request->getPathInfo(),
            'status' => (string) $response->getStatusCode(),
            'auth' => (string) ($_SESSION['_middag_auth']['attributes']['email'] ?? 'anonymous'),
            'contract' => self::contractSummary(),
            'time' => sprintf('%.1f ms', $elapsedSeconds * 1000),
        ];

        $items = '';
        foreach ($rows as $key => $value) {
            $items .= '<span style="margin-right:18px"><b>' . htmlspecialchars($key) . ':</b> '
                . htmlspecialchars($value) . '</span>';
        }

        $bar = '<div style="position:fixed;bottom:0;left:0;right:0;z-index:99999;'
            . 'background:#0f172a;color:#5eead4;font:12px/1.7 ui-monospace,SFMono-Regular,monospace;'
            . 'padding:6px 14px;border-top:2px solid #2dd4bf">'
            . '<b style="color:#2dd4bf">middag debug</b> &nbsp; ' . $items
            . '<span style="float:right;opacity:.6">bus/hooks/queries: framework observability gap (M10)</span>'
            . '</div>';

        return str_replace('</body>', $bar . '</body>', $html);
    }

    private static function contractSummary(): string
    {
        if (self::$contract === null) {
            return '(none)';
        }

        $contract = self::$contract->jsonSerialize();
        if (!is_array($contract)) {
            return '(opaque)';
        }

        $blocks = [];
        foreach ((array) ($contract['layout']['regions'] ?? []) as $region) {
            foreach ((array) $region as $block) {
                if (is_array($block) && isset($block['type'])) {
                    $blocks[] = (string) $block['type'];
                }
            }
        }

        return sprintf(
            'shell=%s page=%s blocks=[%s]',
            (string) ($contract['shell'] ?? '?'),
            (string) ($contract['page']['key'] ?? '?'),
            implode(', ', $blocks),
        );
    }
}

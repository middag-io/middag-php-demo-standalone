<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Framework;

use Middag\Framework\Observability\Contract\ProfileCollectorInterface;
use Middag\Ui\Envelope\ContractEnvelopeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dev profiler collector + debug bar. Renders a fixed debug bar into HTML
 * responses when APP_DEBUG is on, showing what happened in the request.
 *
 * Observability (M10) is now framework-native: the bar reads the shared
 * {@see ProfileCollectorInterface} — fed by the Bus `ProfilingMiddleware` (every
 * dispatch, via the MessageBusFactory middleware seam, G3) and by
 * `HookManager::setProfileCollector()` (every fired hook) — plus the emitted ui
 * contract (`recordContract`) and request context. SQL query-log stays an
 * adapter-side concern (no OSS target).
 */
final class DebugCollector
{
    private static ?ContractEnvelopeInterface $contract = null;

    private static ?ProfileCollectorInterface $profile = null;

    public static function recordContract(ContractEnvelopeInterface $contract): void
    {
        self::$contract = $contract;
    }

    /** Wire the shared profile sink (bus + hooks) so the bar can read its events. */
    public static function useProfileCollector(ProfileCollectorInterface $profile): void
    {
        self::$profile = $profile;
    }

    public static function reset(): void
    {
        self::$contract = null;
        self::$profile = null;
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
            'bus' => self::categorySummary('bus'),
            'hooks' => self::categorySummary('hook'),
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
            . '<span style="float:right;opacity:.6">queries: adapter-side (OSS demo n/a)</span>'
            . '</div>';

        return str_replace('</body>', $bar . '</body>', $html);
    }

    /**
     * Count + total time of profiled events in a category (M10), e.g.
     * `2 (1.4ms)`. `(off)` when no collector is wired.
     */
    private static function categorySummary(string $category): string
    {
        if (self::$profile === null) {
            return '(off)';
        }

        $events = array_filter(
            self::$profile->events(),
            static fn (array $e): bool => ($e['category'] ?? null) === $category,
        );

        if ($events === []) {
            return '0';
        }

        $ms = 0.0;
        foreach ($events as $e) {
            $ms += (float) ($e['duration_ms'] ?? 0.0);
        }

        return sprintf('%d (%.1fms)', count($events), $ms);
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

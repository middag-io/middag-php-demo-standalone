<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Bootstrap;

use Middag\Framework\Kernel\ServiceProvider;

/**
 * Suffix auto-discovery for the demo's own services — the framework's
 * `Kernel\ServiceProvider`.
 *
 * Scans the listed dirs and registers every class whose short name ends in a
 * REGISTER_SUFFIX (Repository/Controller/Handler/Mapper/...) as an autowired,
 * public service keyed by FQCN — so the command handlers, HTTP controllers and
 * the Doctrine repository + mapper land in the container with no hand-wiring.
 * The contracts they autowire against are bound explicitly in {@see DemoBootstrap}.
 *
 * NOTE: ServiceProvider::register() hardcodes a `/src/` path segment, so
 * SCAN_DIRS are relative to the project root and must sit under src/. We scan
 * only the dirs that hold real services; composition-root / form / console
 * classes are wired explicitly to avoid registering them as accidental services.
 */
final class DemoServiceProvider extends ServiceProvider
{
    protected const ROOT_NAMESPACE = 'Middag\\Demo\\Standalone';

    protected const SCAN_DIRS = ['src/Command', 'src/Domain', 'src/Http'];
}

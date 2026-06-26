#!/usr/bin/env php
<?php

/**
 * Recurrence guard (DEMO-02): fail if vendor/middag-io/ holds a stale "ghost"
 * directory — a manual copy that is not a real Composer package.
 *
 * A ghost is any directory under vendor/middag-io/ whose name does not match the
 * short-name of the package its composer.json declares (e.g. a `ui.published`
 * copy of `middag-io/ui`), or that has no composer.json at all. These appear from
 * ad-hoc copy/publish operations; Composer never creates them. They are dead
 * weight (not autoloaded) and were the subject of DEMO-02.
 *
 * Wired into `composer check` so CI/local gates catch any recurrence. Exit 0 when
 * vendor/middag-io is absent (nothing installed yet) — that is not a failure.
 */

declare(strict_types=1);

$base = __DIR__ . '/../vendor/middag-io';

if (!is_dir($base)) {
    echo "vendor/middag-io not present (run composer install) — nothing to check\n";
    exit(0);
}

$ghosts = [];

foreach (new DirectoryIterator($base) as $entry) {
    if ($entry->isDot() || !$entry->isDir()) {
        continue;
    }

    $name = $entry->getFilename();
    $manifest = $entry->getPathname() . '/composer.json';

    if (!is_file($manifest)) {
        $ghosts[] = sprintf('%s (no composer.json)', $name);
        continue;
    }

    $decoded = json_decode((string) file_get_contents($manifest), true);
    $package = is_array($decoded) && isset($decoded['name']) && is_string($decoded['name'])
        ? $decoded['name']
        : null;

    if ($package === null) {
        $ghosts[] = sprintf('%s (composer.json without a package name)', $name);
        continue;
    }

    $expected = substr($package, (int) strpos($package, '/') + 1);

    if ($name !== $expected) {
        $ghosts[] = sprintf("%s (expected directory '%s' for package %s)", $name, $expected, $package);
    }
}

if ($ghosts !== []) {
    fwrite(STDERR, "Ghost copies detected under vendor/middag-io/:\n");
    foreach ($ghosts as $ghost) {
        fwrite(STDERR, '  - ' . $ghost . "\n");
    }
    fwrite(STDERR, "These are stale manual copies, not Composer packages — remove them.\n");
    exit(1);
}

echo "vendor/middag-io: clean (no ghost copies)\n";
exit(0);

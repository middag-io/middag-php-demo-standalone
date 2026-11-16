<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * Writes COVERAGE.md from coverage/manifest.php and the `@api` inventory of the
 * installed middag-io/framework and middag-io/ui (composer coverage:md).
 */

use Composer\InstalledVersions;
use Middag\Demo\Standalone\Tests\Coverage\ApiInventory;
use Middag\Demo\Standalone\Tests\Coverage\CoverageReport;

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

$versions = [];
foreach (array_keys(ApiInventory::LIBRARIES) as $lib) {
    $versions[$lib] = (string) InstalledVersions::getPrettyVersion('middag-io/' . $lib);
}

$markdown = CoverageReport::render(
    require $projectRoot . '/coverage/manifest.php',
    ApiInventory::scan($projectRoot),
    $versions,
);

file_put_contents($projectRoot . '/COVERAGE.md', $markdown);
fwrite(STDOUT, "COVERAGE.md written.\n");

<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Schema;

use Middag\Framework\Database\Schema\MigrationRunner;

/**
 * Concrete MigrationRunner for the demo — the framework ships only a MySQL one,
 * and the abstract base must be subclassed.
 *
 * The base wires a SchemaBuilder + SchemaBuilderAdapterInterface +
 * VersionTrackerInterface and provides install() (create missing tables),
 * upgrade(int $old) (add missing columns) and integer version tracking.
 * onUpgrade() is the seam for data migrations beyond additive diffs.
 */
final class DemoMigrationRunner extends MigrationRunner
{
    /** Bump when db/schema/*.php changes in a way upgrade() should apply. */
    public const VERSION = 2026060200;
}

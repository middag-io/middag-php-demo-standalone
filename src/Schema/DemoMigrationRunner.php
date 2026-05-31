<?php

declare(strict_types=1);

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
    public const VERSION = 2026053000;
}

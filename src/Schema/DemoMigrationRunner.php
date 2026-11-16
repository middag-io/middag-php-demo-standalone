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
    public const int VERSION = 2026111602;

    /**
     * 2026111600: tickets carry a UUID v7 `ref`. New tables (tags, the ticket-tag
     * pivot) come from install(); an existing demo_tickets table gains the column.
     *
     * 2026111601: users carry their capabilities. An existing demo_users table
     * gains the column; the user seed then writes each seeded user's set.
     *
     * 2026111602: attachments carry their uploader. An existing demo_attachments
     * table gains the column; older rows keep 0, so their pending uploads match
     * no user and can no longer be attached.
     */
    protected function onUpgrade(int $oldVersion): void
    {
        if ($oldVersion < 2026111600 && $this->adapter->tableExists('demo_tickets') && !$this->adapter->columnExists('demo_tickets', 'ref')) {
            $this->adapter->addColumn('demo_tickets', ['name' => 'ref', 'type' => 'varchar', 'length' => 36, 'notnull' => false]);
        }

        if ($oldVersion < 2026111601 && $this->adapter->tableExists('demo_users') && !$this->adapter->columnExists('demo_users', 'capabilities')) {
            $this->adapter->addColumn('demo_users', ['name' => 'capabilities', 'type' => 'varchar', 'length' => 255, 'notnull' => true, 'default' => '']);
        }

        if ($oldVersion < 2026111602 && $this->adapter->tableExists('demo_attachments') && !$this->adapter->columnExists('demo_attachments', 'user_id')) {
            $this->adapter->addColumn('demo_attachments', ['name' => 'user_id', 'type' => 'bigint', 'notnull' => true, 'default' => 0]);
        }
    }
}

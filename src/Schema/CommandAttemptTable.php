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

use Middag\Framework\Database\Attribute\Column;
use Middag\Framework\Database\Attribute\Index;
use Middag\Framework\Database\Attribute\Key;
use Middag\Framework\Database\Attribute\Table;

/**
 * The retry bookkeeping table of the async worker, declared by attribute instead
 * of a db/schema descriptor file. SchemaBuilder::registerClass() reads it through
 * the SchemaAttributeReader; install:db creates it with the other tables.
 */
#[Table('demo_command_attempts', comment: 'Attempts of async commands (SqliteAttemptStore)')]
#[Column('id', 'bigint', notnull: true, sequence: true)]
#[Column('command', 'varchar', length: 255, notnull: true)]
#[Column('attempts', 'bigint', notnull: true, default: 0)]
#[Column('max_attempts', 'bigint', notnull: true, default: 3)]
#[Column('status', 'varchar', length: 20, notnull: true, default: 'pending')]
#[Column('available_at', 'bigint', notnull: false)]
#[Column('last_error', 'text', notnull: false)]
#[Key('primary', ['id'], 'pk_demo_command_attempts')]
#[Index('idx_demo_command_attempts_status', ['status'])]
final class CommandAttemptTable
{
    public const string NAME = 'demo_command_attempts';
}

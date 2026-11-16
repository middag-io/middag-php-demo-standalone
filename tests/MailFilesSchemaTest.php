<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Tests;

use Middag\Demo\Standalone\Domain\Ticket;
use Middag\Demo\Standalone\Mail\CaptureMailer;
use Middag\Demo\Standalone\Mail\TicketCreatedNotifier;
use Middag\Demo\Standalone\Schema\CommandAttemptTable;
use Middag\Demo\Standalone\Schema\HelpdeskSeeder;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\SchemaBuilderAdapterInterface;
use Middag\Framework\Database\PrefixingSqlDialect;
use Middag\Framework\Database\Schema\SchemaAttributeReader;
use Middag\Framework\Database\StandardSqlDialect;
use Middag\Framework\Filesystem\Contract\FilesystemInterface;
use Middag\Framework\Filesystem\LocalFilesystem;
use Middag\Framework\Mail\Contract\MailerInterface;
use Middag\Framework\Mail\NullMailer;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The new-ticket mail with its attachment (captured, not sent), the files area,
 * a table declared by attribute, a prefixed SQL dialect and the seed summary.
 *
 * @internal
 */
#[CoversNothing]
final class MailFilesSchemaTest extends DemoTestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/demo_mail_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
        parent::tearDown();
    }

    #[Test]
    public function newTicketMailCarriesTheSummaryAsAnAttachment(): void
    {
        self::assertInstanceOf(NullMailer::class, $this->container->get(MailerInterface::class), 'nothing is captured unless MAIL_CAPTURE=1');
        self::assertInstanceOf(LocalFilesystem::class, $this->container->get(FilesystemInterface::class));

        $outbox = new LocalFilesystem($this->dir . '/mail');
        $files = new LocalFilesystem($this->dir . '/files');
        $ticket = new Ticket(['subject' => 'VPN drops', 'priority' => 'high', 'channel' => 'email', 'customer_id' => 1, 'created_at' => time()]);
        $ticket->save();

        $id = (int) $ticket->getKey();

        (new TicketCreatedNotifier(new CaptureMailer($outbox), $files, $this->dir . '/files'))->notify($id);

        self::assertTrue($files->exists('tickets/' . $id . '/summary.txt'));
        self::assertStringContainsString('VPN drops', $files->read('tickets/' . $id . '/summary.txt'));

        $captured = glob($this->dir . '/mail/*.json') ?: [];
        self::assertCount(1, $captured);
        $mail = (array) json_decode((string) file_get_contents($captured[0]), true);
        self::assertSame(['Help desk <helpdesk@middag.io>'], $mail['to']);
        self::assertSame('Demo <noreply@middag.io>', $mail['from']);
        self::assertSame('New ticket #' . $id . ': VPN drops', $mail['subject']);
        self::assertSame(['ticket-' . $id . '.txt'], $mail['attachments']);

        $files->delete('tickets/' . $id . '/summary.txt');
        self::assertFalse($files->exists('tickets/' . $id . '/summary.txt'));
    }

    #[Test]
    public function attemptTableIsDeclaredByAttributeAndInstalled(): void
    {
        $descriptor = (new SchemaAttributeReader())->read(CommandAttemptTable::class);

        self::assertSame(CommandAttemptTable::NAME, $descriptor['name']);
        self::assertContains('status', array_column($descriptor['columns'], 'name'));
        self::assertTrue($this->container->get(SchemaBuilderAdapterInterface::class)->tableExists(CommandAttemptTable::NAME));
    }

    #[Test]
    public function prefixedDialectQualifiesEveryTable(): void
    {
        $dialect = new PrefixingSqlDialect(new StandardSqlDialect(), 'mdl_');

        self::assertStringContainsString('mdl_demo_tickets', $dialect->table('demo_tickets'));
    }

    #[Test]
    public function seedReportsWhatItWroteAsASyncResult(): void
    {
        $first = HelpdeskSeeder::seed($this->container->get(ConnectionAdapterInterface::class));
        self::assertTrue($first->isFullSuccess());
        self::assertSame(12, $first->successCount);

        $again = HelpdeskSeeder::seed($this->container->get(ConnectionAdapterInterface::class));
        self::assertSame(0, $again->successCount, 'seeding is idempotent');
    }
}

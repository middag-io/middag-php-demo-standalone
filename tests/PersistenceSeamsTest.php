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

use Middag\Demo\Standalone\Domain\Comment;
use Middag\Demo\Standalone\Domain\Doctrine\CustomerRepository;
use Middag\Demo\Standalone\Domain\Tag;
use Middag\Demo\Standalone\Domain\Ticket;
use Middag\Demo\Standalone\Http\Dto\TicketDto;
use Middag\Demo\Standalone\Schema\HelpdeskSeeder;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Form\Schema\FieldSchemaReader;
use Middag\Framework\Persistence\Contract\EntityTypeRegistryInterface;
use Middag\Framework\Persistence\Entity\DefaultEntityType;
use Middag\Framework\Persistence\Query\RelationRef;
use Middag\Ui\Form\Contract\FieldInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * Active Record relations over the seeded help desk, an attribute cast, a join by
 * RelationRef on the data-mapper side, the entity types declared by attribute
 * and the #[Field] schema reader.
 *
 * @internal
 */
#[CoversNothing]
final class PersistenceSeamsTest extends DemoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HelpdeskSeeder::seed($this->container->get(ConnectionAdapterInterface::class));
    }

    #[Test]
    public function ticketRelationsReadCommentsOpeningCommentAndTags(): void
    {
        $ticket = Ticket::findOrFail(1);

        $comments = $ticket->comments()->getResults();
        self::assertNotEmpty($comments);
        self::assertContainsOnlyInstancesOf(Comment::class, $comments);

        $opening = $ticket->openingComment()->getResults();
        self::assertInstanceOf(Comment::class, $opening);
        self::assertStringStartsWith('Opening this ticket.', (string) $opening->body);

        $tags = array_map(static fn (Tag $tag): string => (string) $tag->name, $ticket->tagModels()->getResults());
        sort($tags);
        self::assertSame(['auth', 'login'], $tags);

        $owner = $opening->ticket()->getResults();
        self::assertInstanceOf(Ticket::class, $owner);
        self::assertSame(1, (int) $owner->id);
    }

    #[Test]
    public function eagerLoadingFillsTheRelation(): void
    {
        $tickets = Ticket::query()->with('comments')->limit(3)->get();

        foreach ($tickets as $ticket) {
            self::assertTrue($ticket->relationLoaded('comments'));
            self::assertNotEmpty($ticket->getRelation('comments'));
        }
    }

    #[Test]
    public function editingTheTagsRewritesThePivot(): void
    {
        $this->handle('PUT', '/tickets/1', ['subject' => 'Cannot log in after password reset', 'channel' => 'phone', 'priority' => 'urgent', 'customer_id' => 1, 'agent_id' => 1, 'tags' => 'auth, sso ']);

        $tags = array_map(static fn (Tag $tag): string => (string) $tag->name, Ticket::findOrFail(1)->tagModels()->getResults());
        sort($tags);
        self::assertSame(['auth', 'sso'], $tags);
    }

    #[Test]
    public function authorCastTrimsOnWriteAndRead(): void
    {
        $comment = new Comment(['ticket_id' => 1, 'author' => '  Ana Souza  ', 'body' => 'Checking.', 'is_internal' => false, 'created_at' => time()]);
        $comment->save();

        self::assertSame('Ana Souza', (string) Comment::findOrFail((int) $comment->id)->author);
    }

    #[Test]
    public function customersWithOpenTicketsComeFromARelationRefJoin(): void
    {
        $ref = new RelationRef('demo_tickets', 'id', 'customer_id', 't', 'one-to-many');
        self::assertSame('t.customer_id = c.id', $ref->onExpression(null, 'c'));

        $names = $this->container->get(CustomerRepository::class)->withOpenTickets();
        self::assertNotEmpty($names);
        self::assertSame($names, array_values(array_unique($names)), 'one row per customer');

        $payload = $this->json($this->handle('GET', '/customers', [], ['HTTP_X_INERTIA' => 'true']));
        self::assertStringContainsString('with_open_tickets', (string) json_encode($payload['props']['contract']));
    }

    #[Test]
    public function modelsDeclareTheirEntityTypes(): void
    {
        $registry = $this->container->get(EntityTypeRegistryInterface::class);

        self::assertTrue($registry->has('ticket'));
        $type = $registry->get('ticket');
        self::assertInstanceOf(DefaultEntityType::class, $type);
        self::assertSame(Ticket::class, $type->getEntityClass());
        self::assertSame('Ticket', $type->getLabel());
        self::assertTrue($registry->has('comment'));
        self::assertTrue($registry->has('tag'));
    }

    #[Test]
    public function fieldSchemaReaderReadsTheDtoFields(): void
    {
        $fields = (new FieldSchemaReader())->read(TicketDto::class);

        self::assertContainsOnlyInstancesOf(FieldInterface::class, $fields);
        $names = array_map(static fn (FieldInterface $field): string => (string) $field->toDefinition()->name, $fields);
        self::assertContains('subject', $names);
        self::assertContains('priority', $names);
    }
}

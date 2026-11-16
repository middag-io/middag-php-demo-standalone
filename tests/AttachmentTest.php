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

use Middag\Demo\Standalone\Domain\Attachment;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Framework\Filesystem\Contract\FilesystemInterface;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ticket attachments: the upload stored as pending under an upload token, the
 * form that carries the token back in its FileValue, the attach by token, and
 * the download of the stored bytes.
 *
 * @internal
 */
#[CoversNothing]
final class AttachmentTest extends DemoTestCase
{
    private const string CONTENTS = "line one\nline two\n";

    /** The session user DemoTestCase logs in. */
    private const int UPLOADER = 1;

    /** Another user of the demo. */
    private const int OTHER_USER = 2;

    private int $ticketId = 0;

    /** @var list<string> */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ticketId = $this->createTicket();
    }

    protected function tearDown(): void
    {
        $files = $this->container->get(FilesystemInterface::class);
        foreach ($this->written as $path) {
            if ($files->exists($path)) {
                $files->delete($path);
            }
        }
        parent::tearDown();
    }

    #[Test]
    public function uploadStoresAPendingFileAndTheFormCarriesItsToken(): void
    {
        $pending = $this->upload('notes.txt', self::CONTENTS);

        self::assertSame(Attachment::PENDING, $pending->status);
        self::assertSame(['notes.txt', 'text/plain', strlen(self::CONTENTS)], [$pending->name, $pending->type, $pending->size]);
        self::assertMatchesRegularExpression(Attachment::TOKEN_PATTERN, $pending->token);
        self::assertSame(self::CONTENTS, $this->container->get(FilesystemInterface::class)->read($pending->path()));

        $form = $this->formPanel('?token=' . $pending->token);
        self::assertSame('/tickets/' . $this->ticketId . '/attachments', $form['action']);
        self::assertSame('file', $form['schema'][0]['component'] ?? null);
        self::assertSame([
            'url' => $pending->downloadUrl(),
            'name' => 'notes.txt',
            'size' => strlen(self::CONTENTS),
            'type' => 'text/plain',
            'id' => (string) $pending->id,
            'uploadToken' => $pending->token,
        ], $form['values']['file']);
    }

    #[Test]
    public function theTokenSentBackAttachesTheFileAndItDownloads(): void
    {
        $pending = $this->upload('notes.txt', self::CONTENTS);
        $value = $this->formPanel('?token=' . $pending->token)['values']['file'];

        $response = $this->handle('POST', '/tickets/' . $this->ticketId . '/attachments', ['file' => $value]);

        self::assertSame(303, $response->getStatusCode());
        $attached = Attachment::attachedTo($this->ticketId);
        self::assertCount(1, $attached);
        self::assertSame($pending->token, $attached[0]->token);

        $download = $this->handle('GET', $attached[0]->downloadUrl());
        self::assertSame(200, $download->getStatusCode());
        self::assertSame(self::CONTENTS, $download->getContent());
        self::assertStringStartsWith('text/plain', (string) $download->headers->get('Content-Type'));
        self::assertSame('nosniff', $download->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('attachment; filename=notes.txt', (string) $download->headers->get('Content-Disposition'));
    }

    #[Test]
    public function aForgedOrForeignTokenAttachesNothing(): void
    {
        $pending = $this->upload('notes.txt', self::CONTENTS);
        $other = $this->createTicket();

        $this->handle('POST', '/tickets/' . $this->ticketId . '/attachments', ['file' => ['url' => '/x', 'uploadToken' => '../../etc/passwd']]);
        $this->handle('POST', '/tickets/' . $other . '/attachments', ['file' => ['url' => '/x', 'uploadToken' => $pending->token]]);

        self::assertSame([], Attachment::attachedTo($this->ticketId));
        self::assertSame([], Attachment::attachedTo($other));
        self::assertSame(404, $this->handle('GET', '/tickets/' . $other . '/attachments/' . $pending->id, [], ['HTTP_ACCEPT' => 'application/json'])->getStatusCode());
    }

    #[Test]
    public function anotherUsersTokenNeitherShowsNorAttachesThePendingUpload(): void
    {
        $pending = $this->upload('notes.txt', self::CONTENTS);
        self::assertSame(self::UPLOADER, $pending->user_id);
        $value = $this->formPanel('?token=' . $pending->token)['values']['file'];

        $this->container->get(AuthenticatorInterface::class)->login(self::OTHER_USER, ['name' => 'Other', 'capabilities' => []]);

        $form = $this->formPanel('?token=' . $pending->token);
        self::assertArrayNotHasKey('file', $form['values'] ?? []);
        self::assertSame('/tickets/' . $this->ticketId . '/attachments/upload', $form['action']);

        $response = $this->handle('POST', '/tickets/' . $this->ticketId . '/attachments', ['file' => $value]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame([], Attachment::attachedTo($this->ticketId));
        self::assertSame(Attachment::PENDING, Attachment::query()->where('id', $pending->id)->first()?->status);
    }

    #[Test]
    public function uploadRefusesAnUnlistedTypeAndAnOversizedFile(): void
    {
        $this->post('script.php', '<?php echo 1;');
        $this->post('large.txt', str_repeat('a', 1024 * 1024 + 1));

        self::assertSame(0, count(Attachment::query()->where('ticket_id', $this->ticketId)->get()));
    }

    private function createTicket(): int
    {
        $response = $this->handle('POST', '/api/tickets', ['subject' => 'Attachment ticket', 'customer_id' => '1', 'priority' => 'normal', 'channel' => 'web'], ['HTTP_ACCEPT' => 'application/json']);

        return (int) $this->json($response)['id'];
    }

    private function upload(string $name, string $contents): Attachment
    {
        $response = $this->post($name, $contents);
        self::assertSame(303, $response->getStatusCode());
        $location = (string) $response->headers->get('Location');
        self::assertStringStartsWith('/tickets/' . $this->ticketId . '/attachments?token=', $location);

        $pending = Attachment::findPending($this->ticketId, self::UPLOADER, substr($location, (int) strpos($location, '=') + 1));
        self::assertInstanceOf(Attachment::class, $pending);
        $this->written[] = $pending->path();

        return $pending;
    }

    private function post(string $name, string $contents): Response
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($tmp, $contents);

        return $this->handle('POST', '/tickets/' . $this->ticketId . '/attachments/upload', [], [], ['file' => new UploadedFile($tmp, $name, null, null, true)]);
    }

    /** @return array<string, mixed> */
    private function formPanel(string $query): array
    {
        $props = $this->json($this->handle('GET', '/tickets/' . $this->ticketId . '/attachments' . $query, [], ['HTTP_X_INERTIA' => 'true']))['props'];
        foreach ($props['contract']['layout']['regions']['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'form_panel') {
                return $block['data'];
            }
        }
        self::fail('No form_panel on the attachments page.');
    }
}

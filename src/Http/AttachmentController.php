<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Domain\Attachment;
use Middag\Demo\Standalone\Domain\Ticket;
use Middag\Demo\Standalone\Form\AttachmentForm;
use Middag\Demo\Standalone\Form\FormNodes;
use Middag\Demo\Standalone\Shared\Concerns\RendersPages;
use Middag\Framework\Exception\MiddagNotFoundException;
use Middag\Framework\Filesystem\Contract\FilesystemInterface;
use Middag\Framework\Form\Renderer\RendererRegistry;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Ui\Action\ActionTarget;
use Middag\Ui\Form\FileValue;
use Middag\Ui\Page\PageBuilder;
use Middag\Ui\Region\RegionBuilder;
use Middag\Ui\Shared\Enum\ActionIntent;
use Middag\Ui\Shared\Enum\RenderTarget;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ticket attachments: the host side of the free file field's upload flow.
 *
 * The free FileUploadField posts the picked file with the form (multipart). The
 * demo, as the host, stores it in the files area as a pending upload under a
 * random upload token and renders the form again with the stored file as a
 * FileValue that carries the token. Submitting that form sends the FileValue
 * back untouched; the token finds the pending upload, which becomes attached.
 * A pending upload belongs to its uploader: another user's token finds nothing.
 * Attached files are listed with a download link.
 */
#[Auth(login: true)]
final class AttachmentController extends AbstractController
{
    use RendersPages;

    public function __construct(
        private readonly RendererRegistry $renderers,
        private readonly AttachmentForm $form,
        private readonly FilesystemInterface $files,
        private readonly AuthenticatorInterface $auth,
    ) {}

    /**
     * GET /tickets/{id}/attachments — the form and the attached files. With a
     * pending `token` the form holds the uploaded file and attaches it.
     */
    public function index(int $id): Response
    {
        $ticket = Ticket::findOrFail($id);
        $pending = Attachment::findPending($id, $this->userId(), (string) $this->request->query->get('token', ''));

        $schema = FormNodes::fromRendered($this->renderers->get(RenderTarget::Props)->render($this->form)->props['schema'] ?? []);
        $values = $pending instanceof Attachment ? ['file' => $this->fileValue($pending)] : [];
        $base = '/tickets/' . $id . '/attachments';
        $links = array_map(
            static fn (Attachment $a): array => [
                'label' => $a->name,
                'href' => $a->downloadUrl(),
                'description' => $a->type . ' · ' . $a->size . ' bytes',
                // A plain anchor: Inertia would try to render the file as a page.
                'external' => true,
            ],
            Attachment::attachedTo($id),
        );

        $contract = PageBuilder::page('demo.tickets.attachments')
            ->shell('basic')
            ->layout('stack')
            ->title('Attachments')
            ->subtitle('#' . $id . ' · ' . $ticket->subject)
            ->actions([
                PageBuilder::action('back', 'Back to ticket', ActionTarget::link('/tickets/' . $id), ActionIntent::Secondary, 'arrow-left'),
            ])
            ->region('content', function (RegionBuilder $region) use ($pending, $base, $schema, $values, $links): void {
                $region->formPanel(
                    'attachment',
                    $pending instanceof Attachment ? $base : $base . '/upload',
                    'POST',
                    $schema,
                    $values,
                    null,
                    ['meta' => ['submitLabel' => $pending instanceof Attachment ? 'Attach file' : 'Upload']],
                );
                if ($links !== []) {
                    $region->linkList('files', $links);
                }
            })
            ->build();

        return $this->page($contract);
    }

    /**
     * POST /tickets/{id}/attachments/upload — stores the posted file as a pending
     * upload and sends the browser back to the form, which now holds it. Stale
     * pending uploads are purged first; a user holding the maximum of pending
     * uploads is refused until one is attached or expires, and a ticket holding
     * the maximum of attached files takes no more uploads.
     */
    public function upload(int $id): RedirectResponse
    {
        Ticket::findOrFail($id);
        Attachment::purgeStale($this->files, time());
        $file = $this->request->files->get('file');
        $error = $this->uploadError($file) ?? $this->quotaError($id);
        if ($error !== null || !$file instanceof UploadedFile) {
            $this->flash('error', $error ?? 'Choose a file to upload.');

            return $this->redirect('/tickets/' . $id . '/attachments', Response::HTTP_SEE_OTHER);
        }

        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            $this->flash('error', 'The upload could not be read.');

            return $this->redirect('/tickets/' . $id . '/attachments', Response::HTTP_SEE_OTHER);
        }

        $attachment = new Attachment([
            'ticket_id' => $id,
            'user_id' => $this->userId(),
            'token' => bin2hex(random_bytes(16)),
            'name' => $this->displayName($file->getClientOriginalName()),
            'type' => AttachmentForm::TYPES[strtolower($file->getClientOriginalExtension())],
            'size' => strlen($contents),
            'status' => Attachment::PENDING,
            'created_at' => time(),
        ]);
        $this->files->write($attachment->path(), $contents);
        $attachment->save();
        $this->flash('info', 'Uploaded. Attach the file to keep it on the ticket.');

        return $this->redirect('/tickets/' . $id . '/attachments?token=' . $attachment->token, Response::HTTP_SEE_OTHER);
    }

    /**
     * POST /tickets/{id}/attachments — the form sends the FileValue back; its
     * upload token turns the ticket's pending upload into an attachment.
     */
    public function attach(int $id): RedirectResponse
    {
        Ticket::findOrFail($id);
        $value = $this->request->getPayload()->all()['file'] ?? null;
        $token = is_array($value) && is_string($value['uploadToken'] ?? null) ? $value['uploadToken'] : '';
        $attachment = Attachment::findPending($id, $this->userId(), $token);

        if (!$attachment instanceof Attachment) {
            $this->flash('error', 'Upload a file first.');

            return $this->redirect('/tickets/' . $id . '/attachments', Response::HTTP_SEE_OTHER);
        }

        // Pending uploads made before the ticket filled up must not pass the cap.
        if (Attachment::attachedCount($id) >= Attachment::MAX_ATTACHED_PER_TICKET) {
            $this->flash('error', $this->ticketFullMessage());

            return $this->redirect('/tickets/' . $id . '/attachments', Response::HTTP_SEE_OTHER);
        }

        $attachment->status = Attachment::ATTACHED;
        $attachment->save();
        $this->flash('success', 'File attached.');

        return $this->redirect('/tickets/' . $id . '/attachments', Response::HTTP_SEE_OTHER);
    }

    /**
     * GET /tickets/{id}/attachments/{attachment} — the stored bytes as a download.
     * An attached file downloads for anyone signed in; a pending upload only for
     * its uploader, so the form can preview it.
     */
    public function download(int $id, int $attachment): Response
    {
        $file = Attachment::query()->where('id', $attachment)->first();
        if (!$file instanceof Attachment
            || $file->ticket_id !== $id
            || !$file->isDownloadableBy($this->userId())
            || !$this->files->exists($file->path())) {
            throw new MiddagNotFoundException('Attachment not found.');
        }

        $response = new Response($this->files->read($file->path()), Response::HTTP_OK, [
            'Content-Type' => $file->type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $file->name,
            (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $file->name),
        ));

        return $response;
    }

    /** The session user's id; the route is login-gated, so it is set. */
    private function userId(): int
    {
        return (int) $this->auth->id();
    }

    /** The stored upload as the file field's value, upload token included. */
    private function fileValue(Attachment $attachment): FileValue
    {
        return new FileValue(
            url: $attachment->downloadUrl(),
            name: $attachment->name,
            size: $attachment->size,
            type: $attachment->type,
            id: (string) $attachment->id,
            uploadToken: $attachment->token,
        );
    }

    /** Why the posted value is not an acceptable upload, or null when it is. */
    private function uploadError(mixed $file): ?string
    {
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return 'Choose a file to upload.';
        }
        if (!isset(AttachmentForm::TYPES[strtolower($file->getClientOriginalExtension())])) {
            return 'Upload a .txt, .csv, .png or .pdf file.';
        }
        if ((int) $file->getSize() > AttachmentForm::MAX_SIZE) {
            return 'The file is larger than 1 MB.';
        }

        return null;
    }

    /** Why the session user may not upload another file to this ticket now, or null. */
    private function quotaError(int $ticketId): ?string
    {
        if (Attachment::attachedCount($ticketId) >= Attachment::MAX_ATTACHED_PER_TICKET) {
            return $this->ticketFullMessage();
        }
        if (Attachment::pendingCount($this->userId()) >= Attachment::MAX_PENDING_PER_USER) {
            return sprintf('You already have %d uploads waiting: attach them or try again later.', Attachment::MAX_PENDING_PER_USER);
        }

        return null;
    }

    private function ticketFullMessage(): string
    {
        return sprintf('This ticket already has %d attached files.', Attachment::MAX_ATTACHED_PER_TICKET);
    }

    /**
     * The client file name, kept for display and the download name only: no
     * directory part, no control characters, at most 190 characters.
     */
    private function displayName(string $name): string
    {
        $clean = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $name)));

        return mb_substr($clean !== '' ? $clean : 'file', 0, 190);
    }
}

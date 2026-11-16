<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Mail;

use Middag\Demo\Standalone\Domain\Ticket;
use Middag\Framework\Filesystem\Contract\FilesystemInterface;
use Middag\Framework\Mail\Address;
use Middag\Framework\Mail\Attachment;
use Middag\Framework\Mail\Contract\MailerInterface;
use Middag\Framework\Mail\Mail;

/**
 * On demo.ticket.created: writes a plain-text summary of the ticket to the files
 * area (FilesystemInterface) and mails the help desk with it attached.
 */
final readonly class TicketCreatedNotifier
{
    public const string HELPDESK = 'helpdesk@middag.io';

    public function __construct(
        private MailerInterface $mailer,
        private FilesystemInterface $files,
        private string $filesRoot,
    ) {}

    public function notify(int $ticketId): void
    {
        $ticket = Ticket::find($ticketId);
        if (!$ticket instanceof Ticket) {
            return;
        }

        $path = sprintf('tickets/%d/summary.txt', $ticketId);
        $this->files->write($path, sprintf("#%d %s\nPriority: %s\nChannel: %s\n", $ticketId, $ticket->subject, $ticket->priority, $ticket->channel));

        $this->mailer->send(new Mail(
            to: [new Address(self::HELPDESK, 'Help desk')],
            subject: sprintf('New ticket #%d: %s', $ticketId, $ticket->subject),
            body: sprintf('A %s ticket was opened. The summary is attached.', $ticket->priority),
            from: Address::parse('Demo <noreply@middag.io>'),
            attachments: [new Attachment($this->filesRoot . '/' . $path, 'ticket-' . $ticketId . '.txt', 'text/plain')],
        ));
    }
}

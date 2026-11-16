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

use Middag\Framework\Filesystem\Contract\FilesystemInterface;
use Middag\Framework\Mail\Address;
use Middag\Framework\Mail\Attachment;
use Middag\Framework\Mail\Contract\MailerInterface;
use Middag\Framework\Mail\Mail;

/**
 * Mailer that captures each message as a JSON file in a filesystem (var/mail/)
 * instead of sending it. The framework ships only the NullMailer; the demo binds
 * this one when MAIL_CAPTURE=1 (and in fault mode), so a test or the outbox route
 * can read what would have been sent.
 */
final class CaptureMailer implements MailerInterface
{
    private int $sequence = 0;

    public function __construct(private readonly FilesystemInterface $outbox) {}

    public function send(Mail $mail): void
    {
        $name = sprintf('%s-%04d.json', date('YmdHis'), ++$this->sequence);

        $this->outbox->write($name, (string) json_encode([
            'to' => array_map(static fn (Address $to): string => $to->toString(), $mail->to),
            'from' => $mail->from?->toString(),
            'subject' => $mail->subject,
            'body' => $mail->body,
            'attachments' => array_map(static fn (Attachment $file): string => $file->filename(), $mail->attachments),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

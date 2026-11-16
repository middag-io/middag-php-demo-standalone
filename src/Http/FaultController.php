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

use Middag\Demo\Standalone\Mail\CaptureMailer;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Framework\Mail\Contract\MailerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Routes that exist only with DEMO_FAULT_MODE=1 (see DemoBootstrap::routes()).
 * `/_test/crash` throws, so the kernel answers with the 500 error page that
 * ErrorPageRenderer draws and reports through the ErrorReporterInterface.
 * `/_test/outbox` lists the mail the CaptureMailer captured, so an E2E spec reads
 * the outbox over HTTP instead of the server's var/mail directory.
 */
#[Auth(login: true)]
final class FaultController extends AbstractController
{
    public function crash(): never
    {
        throw new RuntimeException('The fault server crash route was called.');
    }

    public function outbox(): JsonResponse
    {
        $mailer = $this->getService(MailerInterface::class);
        $mails = $mailer instanceof CaptureMailer ? $mailer->captured() : [];

        return $this->jsonResponse(['data' => $mails]);
    }
}

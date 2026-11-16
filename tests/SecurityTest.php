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

use Middag\Demo\Standalone\Domain\User;
use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use Middag\Demo\Standalone\Webhook\TicketWebhookNotifier;
use Middag\Framework\Http\HttpClientFactory;
use Middag\Framework\Http\Retry\RetryPolicy;
use Middag\Framework\Security\Contract\PasswordHasherInterface;
use Middag\Framework\Security\Contract\SignatureVerifierInterface;
use Middag\Framework\Security\Enum\SignatureEncoding;
use Middag\Framework\Security\HmacSignatureVerifier;
use Middag\Framework\Security\NativePasswordHasher;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

/**
 * Login through the framework password hasher, and the outbound webhook: signed
 * with the HMAC verifier, sent through the framework HTTP client and retried by
 * the retry policy against a throwaway local receiver.
 *
 * @internal
 */
#[CoversNothing]
final class SecurityTest extends DemoTestCase
{
    /** @var null|resource */
    private $server;

    private string $log = '';

    protected function tearDown(): void
    {
        if (\is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        if ($this->log !== '' && is_file($this->log)) {
            unlink($this->log);
        }
        parent::tearDown();
    }

    #[Test]
    public function seededUserVerifiesThroughThePasswordHasher(): void
    {
        $hasher = $this->container->get(PasswordHasherInterface::class);
        self::assertInstanceOf(NativePasswordHasher::class, $hasher);

        $user = User::findByEmail(User::DEMO_EMAIL);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->verifyPassword(User::DEMO_PASSWORD, $hasher));
        self::assertFalse($user->verifyPassword('wrong', $hasher));
        self::assertFalse($hasher->needsRehash((string) $user->password_hash));
    }

    #[Test]
    public function webhookIsSignedSentAndRetriedOnce(): void
    {
        $url = $this->startReceiver();
        $verifier = $this->container->get(SignatureVerifierInterface::class);
        self::assertInstanceOf(HmacSignatureVerifier::class, $verifier);

        $notifier = new TicketWebhookNotifier(new HttpClientFactory(), new RetryPolicy(3, 10), $verifier, $url, 'shared-secret');
        $status = $notifier->notify('ticket.created', ['id' => 42]);

        self::assertSame(200, $status, 'the second attempt succeeds');
        $attempts = array_map(static fn (string $line): array => (array) json_decode($line, true), (array) file($this->log, FILE_IGNORE_NEW_LINES));
        self::assertCount(2, $attempts, 'the 503 was retried exactly once');

        $last = $attempts[1];
        self::assertTrue($verifier->verify('shared-secret', $last['timestamp'] . '.' . $last['body'], (string) $last['signature']));
        self::assertFalse($verifier->verify('other-secret', $last['timestamp'] . '.' . $last['body'], (string) $last['signature']));
        self::assertTrue($verifier->isFresh((int) $last['timestamp'], 300));
        self::assertSame(['event' => 'ticket.created', 'data' => ['id' => 42]], json_decode((string) $last['body'], true));
    }

    #[Test]
    public function base64EncodingSignsTheSamePayloadDifferently(): void
    {
        $hex = new HmacSignatureVerifier('sha256', SignatureEncoding::Hex);
        $base64 = new HmacSignatureVerifier('sha256', SignatureEncoding::Base64);

        self::assertSame(base64_encode((string) hex2bin($hex->computeSignature('k', 'p'))), $base64->computeSignature('k', 'p'));
    }

    private function startReceiver(): string
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new RuntimeException('No free port for the webhook receiver.');
        }
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $this->log = (string) tempnam(sys_get_temp_dir(), 'demo-webhook-');
        unlink($this->log);
        $router = __DIR__ . '/../tests/Fixtures/webhook-receiver.php';
        $this->server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            ['WEBHOOK_LOG' => $this->log] + getenv(),
        );

        for ($i = 0; $i < 50; ++$i) {
            $probe = @fsockopen('127.0.0.1', $port);
            if ($probe !== false) {
                fclose($probe);

                return 'http://127.0.0.1:' . $port . '/hook';
            }
            usleep(100_000);
        }

        throw new RuntimeException('The webhook receiver did not start.');
    }
}

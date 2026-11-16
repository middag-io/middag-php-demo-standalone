<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Webhook;

use Middag\Framework\Http\Contract\RetryPolicyInterface;
use Middag\Framework\Http\HttpClientFactory;
use Middag\Framework\Security\Contract\SignatureVerifierInterface;
use Nyholm\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Sends a signed JSON notification about a ticket to an outbound webhook.
 *
 * The body is signed with the shared secret (HMAC, hex) over "<timestamp>.<body>";
 * the receiver checks the signature and the freshness of the timestamp with the
 * same SignatureVerifierInterface. A retryable status (429, 5xx) or a transport
 * error is retried as the RetryPolicyInterface says.
 */
final readonly class TicketWebhookNotifier
{
    public const string SIGNATURE_HEADER = 'X-Demo-Signature';

    public const string TIMESTAMP_HEADER = 'X-Demo-Timestamp';

    public function __construct(
        private HttpClientFactory $clients,
        private RetryPolicyInterface $retry,
        private SignatureVerifierInterface $signatures,
        private string $url,
        private string $secret,
    ) {}

    /**
     * @param array<string, mixed> $payload
     *
     * @return int the final HTTP status, or 0 when every attempt failed in transport
     */
    public function notify(string $event, array $payload): int
    {
        $body = (string) json_encode(['event' => $event, 'data' => $payload], JSON_THROW_ON_ERROR);
        $client = $this->clients->make();
        $attempt = 0;

        while (true) {
            ++$attempt;
            $timestamp = (string) time();
            $request = new Request('POST', $this->url, [
                'Content-Type' => 'application/json',
                self::TIMESTAMP_HEADER => $timestamp,
                self::SIGNATURE_HEADER => $this->signatures->computeSignature($this->secret, $timestamp . '.' . $body),
            ], $body);

            try {
                $response = $client->sendRequest($request);
                $status = $response->getStatusCode();
                $retryAfter = $response->getHeaderLine('Retry-After');
            } catch (ClientExceptionInterface) {
                $status = null;
                $retryAfter = '';
            }

            if (!$this->retry->shouldRetry($attempt, $status)) {
                return $status ?? 0;
            }

            $delayMs = $this->retry->nextDelayMs($attempt, $status, $retryAfter !== '' ? $retryAfter : null);
            if ($delayMs === null) {
                return $status ?? 0;
            }
            usleep($delayMs * 1000);
        }
    }
}

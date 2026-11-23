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

use Middag\Demo\Standalone\Tests\Support\DemoTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

/**
 * Proves the framework 0.11.0 validation-error i18n contract end-to-end through
 * the standalone help-desk: a 422 carries a structured `{message, key, domain,
 * params?}` per field (the ui FieldMessage; `params` only when there are some) —
 * English-resolved by default (no host catalogue), with the machine `key`/`params`
 * a React/Inertia client can re-translate.
 *
 * @internal
 */
#[CoversNothing]
final class ValidationI18nContractTest extends DemoTestCase
{
    #[Test]
    public function validationErrorsCarryStructuredI18nShape(): void
    {
        $response = $this->handle('POST', '/api/tickets/dto', ['priority' => 'high'], ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame(422, $response->getStatusCode());

        $payload = $this->json($response);
        self::assertSame('validation_failed', $payload['error']);
        self::assertNotEmpty($payload['errors']);

        // Decoded as objects, so an empty JSON object stays distinct from a list.
        $errors = json_decode((string) $response->getContent(), false, 512, JSON_THROW_ON_ERROR)->errors;

        foreach (get_object_vars($errors) as $field => $entry) {
            $entries = \is_array($entry) ? $entry : [$entry];

            foreach ($entries as $error) {
                self::assertInstanceOf(stdClass::class, $error, sprintf("error for '%s' must be a structured object, not a string", $field));
                self::assertIsString($error->message ?? null);
                self::assertNotSame('', $error->message);                 // English resolved by default
                self::assertStringStartsWith('validation.', $error->key); // machine key for client i18n
                self::assertSame('validators', $error->domain);
                if (property_exists($error, 'params')) {
                    self::assertInstanceOf(stdClass::class, $error->params, sprintf("params of '%s', when present, must be a JSON object", $field));
                }
            }
        }
    }

    #[Test]
    public function typeCoercionErrorRoutesThroughTheTranslator(): void
    {
        // A non-numeric customer_id cannot coerce to the DTO's int property -> a
        // denormalization error, which 0.11.0 routes through the translator (the
        // gap-#1 fix) as a structured i18n message rather than a hardcoded string.
        $response = $this->handle(
            'POST',
            '/api/tickets/dto',
            ['subject' => 'Disk full', 'priority' => 'normal', 'channel' => 'web', 'customer_id' => 'not-a-number'],
            ['HTTP_ACCEPT' => 'application/json'],
        );

        self::assertSame(422, $response->getStatusCode());

        $payload = $this->json($response);
        self::assertArrayHasKey('customer_id', $payload['errors']);

        $entry = $payload['errors']['customer_id'];
        $first = array_is_list($entry) ? $entry[0] : $entry;

        self::assertStringStartsWith('validation.', $first['key']);
        self::assertSame('validators', $first['domain']);
        self::assertNotSame('', $first['message']);
    }
}

<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

/*
 * Router script for a throwaway "php -S" webhook receiver (SecurityTest).
 * Records each request as one JSON line in WEBHOOK_LOG and answers 503 to the
 * first request, 200 afterwards, so the sender's retry is exercised.
 */
$log = (string) getenv('WEBHOOK_LOG');
$attempt = is_file($log) ? count(file($log)) + 1 : 1;

file_put_contents($log, json_encode([
    'attempt' => $attempt,
    'signature' => $_SERVER['HTTP_X_DEMO_SIGNATURE'] ?? null,
    'timestamp' => $_SERVER['HTTP_X_DEMO_TIMESTAMP'] ?? null,
    'body' => file_get_contents('php://input'),
]) . "\n", FILE_APPEND);

http_response_code($attempt === 1 ? 503 : 200);
echo $attempt === 1 ? 'busy' : 'ok';

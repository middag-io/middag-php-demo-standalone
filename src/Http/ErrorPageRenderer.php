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

use Middag\Framework\Http\DefaultExceptionRenderer;
use Middag\Framework\Observability\Contract\ErrorReporterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The demo's exception renderer, bound to ExceptionRendererInterface so the kernel
 * uses it for every mapped failure.
 *
 * It extends the framework's DefaultExceptionRenderer through its documented
 * override point: JSON and debug output stay the framework's, and a browser gets
 * a small HTML error page with a way back. A server error (5xx) goes to the
 * ErrorReporterInterface first (the OSS NullErrorReporter by default).
 */
final readonly class ErrorPageRenderer extends DefaultExceptionRenderer
{
    public function __construct(
        private ErrorReporterInterface $reporter,
        bool $debug = false,
    ) {
        parent::__construct($debug);
    }

    public function render(Throwable $throwable, Request $request, int $statusCode, string $errorCode, string $message, bool $isJson): Response
    {
        if ($statusCode >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            $this->reporter->report($throwable, ['path' => $request->getPathInfo(), 'code' => $errorCode]);
        }

        return parent::render($throwable, $request, $statusCode, $errorCode, $message, $isJson);
    }

    protected function renderHtmlPage(Throwable $throwable, int $statusCode, string $message): Response
    {
        $title = htmlspecialchars($statusCode . ' · ' . $message, ENT_QUOTES);
        $html = <<<HTML
            <!doctype html>
            <html lang="en">
            <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{$title}</title></head>
            <body style="font-family: system-ui, sans-serif; max-width: 32rem; margin: 15vh auto; padding: 0 1rem">
            <h1 data-testid="error-page-title">{$title}</h1>
            <p><a href="/">Back to the dashboard</a></p>
            </body>
            </html>
            HTML;

        return new Response($html, $statusCode, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}

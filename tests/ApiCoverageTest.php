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

use Middag\Demo\Standalone\Tests\Coverage\ApiInventory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Drift guard of the `@api` map (coverage/api.php) against the installed libraries.
 *
 * In the `api-coverage` group: the locked CI jobs run it (the versions are fixed,
 * so a failure is the demo's), and the unpinned `highest` job leaves it out (a new
 * `@api` symbol upstream announces work, not a broken pull request).
 *
 * @internal
 */
#[CoversNothing]
#[Group('api-coverage')]
final class ApiCoverageTest extends TestCase
{
    private const array STATUSES = ['covered', 'host', 'gap'];

    /** @var null|array<string, array<string, mixed>> */
    private static ?array $api = null;

    #[Test]
    public function everyInstalledApiSymbolIsMapped(): void
    {
        $missing = array_keys(array_diff_key(ApiInventory::scan($this->root()), $this->api()));

        self::assertSame([], $missing, 'Map every new @api symbol in coverage/api.php.');
    }

    #[Test]
    public function theMapNamesNoSymbolThatIsGone(): void
    {
        $stale = array_keys(array_diff_key($this->api(), ApiInventory::scan($this->root())));

        self::assertSame([], $stale, 'Remove the entries of @api symbols the installed libraries no longer have.');
    }

    #[Test]
    public function everyEntryHasAKnownStatusAndItsProofOrReason(): void
    {
        $problems = [];
        foreach ($this->api() as $symbol => $entry) {
            $status = $entry['status'] ?? null;
            if (!\in_array($status, self::STATUSES, true)) {
                $problems[] = $symbol . ': unknown status';

                continue;
            }
            $hasProof = ($entry['tests'] ?? []) !== [] || ($entry['e2e'] ?? []) !== [];
            $hasReason = trim((string) ($entry['reason'] ?? '')) !== '';
            $hasArtifact = trim((string) ($entry['artifact'] ?? '')) !== '';

            if ($status === 'covered' && !$hasProof) {
                $problems[] = $symbol . ': covered without a test or an E2E spec';
            }
            if ($status === 'covered' && !$hasArtifact && !$hasReason) {
                $problems[] = $symbol . ': covered without the artifact or what it is reached through';
            }
            if ($status !== 'covered' && !$hasReason) {
                $problems[] = $symbol . ': ' . $status . ' without a reason';
            }
        }

        self::assertSame([], $problems);
    }

    #[Test]
    public function everyNamedTestMethodExists(): void
    {
        $problems = [];
        foreach ($this->api() as $symbol => $entry) {
            foreach ((array) ($entry['tests'] ?? []) as $reference) {
                [$class, $method] = array_pad(explode('::', (string) $reference, 2), 2, '');
                $fqcn = __NAMESPACE__ . '\\' . $class;
                if (!class_exists($fqcn) || !(new ReflectionClass($fqcn))->hasMethod($method)) {
                    $problems[] = $symbol . ': ' . $reference;
                }
            }
        }

        self::assertSame([], $problems, 'Each tests[] entry is Class::method of a test under tests/.');
    }

    #[Test]
    public function everyNamedE2eTitleExists(): void
    {
        $problems = [];
        foreach ($this->api() as $symbol => $entry) {
            foreach ((array) ($entry['e2e'] ?? []) as $reference) {
                [$file, $title] = array_pad(explode(' › ', (string) $reference, 2), 2, '');
                $path = $this->root() . '/e2e/' . $file;
                if ($title === '' || !is_file($path) || !str_contains((string) file_get_contents($path), "'" . $title . "'")) {
                    $problems[] = $symbol . ': ' . $reference;
                }
            }
        }

        self::assertSame([], $problems, "Each e2e[] entry is '<spec path under e2e/> › <test title>'.");
    }

    #[Test]
    public function everyArtifactPathExists(): void
    {
        $problems = [];
        foreach ($this->api() as $symbol => $entry) {
            $artifact = (string) ($entry['artifact'] ?? '');
            if ($artifact !== '' && !is_file($this->root() . '/' . $artifact)) {
                $problems[] = $symbol . ': ' . $artifact;
            }
        }

        self::assertSame([], $problems);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function api(): array
    {
        return self::$api ??= require $this->root() . '/coverage/api.php';
    }

    private function root(): string
    {
        return \dirname(__DIR__);
    }
}

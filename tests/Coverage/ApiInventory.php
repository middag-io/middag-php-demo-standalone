<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Tests\Coverage;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Lists the `@api` surface of the installed middag-io/framework and middag-io/ui.
 *
 * A symbol is `@api` when the doc comment right before its class, interface,
 * trait or enum declaration carries the `@api` tag. A method tagged `@api` inside
 * a class that is not itself `@api` is listed as `Class::method`. The scan reads
 * the installed vendor tree, so the inventory always matches the lock.
 */
final class ApiInventory
{
    /** Library short name => root namespace, as installed under vendor/middag-io/. */
    public const array LIBRARIES = [
        'framework' => 'Middag\Framework\\',
        'ui' => 'Middag\Ui\\',
    ];

    /**
     * @return array<string, array{lib: string, area: string, kind: string}> FQCN or
     *                                                                       Class::method => facts, sorted by key
     */
    public static function scan(string $projectRoot): array
    {
        $symbols = [];

        foreach (self::LIBRARIES as $lib => $rootNamespace) {
            $srcDir = $projectRoot . '/vendor/middag-io/' . $lib . '/src';
            if (!is_dir($srcDir)) {
                throw new RuntimeException(sprintf('middag-io/%s is not installed (%s).', $lib, $srcDir));
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                foreach (self::symbolsIn((string) file_get_contents($file->getPathname())) as $symbol => $kind) {
                    $symbols[$symbol] = [
                        'lib' => $lib,
                        'area' => self::area($symbol, $rootNamespace),
                        'kind' => $kind,
                    ];
                }
            }
        }

        ksort($symbols);

        return $symbols;
    }

    /**
     * The first namespace segment under the library root: `Middag\Framework\Http\Inertia\X` => `Http`.
     */
    public static function area(string $symbol, string $rootNamespace): string
    {
        $relative = substr($symbol, \strlen($rootNamespace));
        $segments = explode('\\', $relative);

        return \count($segments) > 1 ? $segments[0] : '(root)';
    }

    /**
     * @return array<string, string> FQCN or Class::method => kind
     */
    private static function symbolsIn(string $code): array
    {
        $tokens = token_get_all($code);
        $namespace = '';
        $lastDoc = null;
        $class = null;
        $classIsApi = false;
        $symbols = [];
        $count = \count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];
            if (!\is_array($token)) {
                if (!in_array($token, [';', '{', '}'], true)) {
                    continue;
                }
                $lastDoc = null;

                continue;
            }

            [$id, $text] = $token;

            if ($id === T_ATTRIBUTE) {
                // An attribute group sits between a doc comment and its
                // declaration: skip it whole, keeping the doc comment.
                $i = self::endOfAttribute($tokens, $i);

                continue;
            }

            if ($id === T_DOC_COMMENT) {
                $lastDoc = $text;

                continue;
            }

            if ($id === T_NAMESPACE) {
                $namespace = self::readName($tokens, $i + 1);

                continue;
            }

            if (\in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && !self::isClassConstant($tokens, $i)) {
                $name = self::nextIdentifier($tokens, $i + 1);
                if ($name === null) {
                    continue; // anonymous class
                }
                $class = $namespace . '\\' . $name;
                $classIsApi = self::hasApiTag($lastDoc);
                if ($classIsApi) {
                    $symbols[$class] = strtolower((string) token_name($id) === 'T_CLASS' ? 'class' : substr((string) token_name($id), 2));
                }
                $lastDoc = null;

                continue;
            }

            if ($id === T_FUNCTION && $class !== null && !$classIsApi && self::hasApiTag($lastDoc)) {
                $method = self::nextIdentifier($tokens, $i + 1);
                if ($method !== null) {
                    $symbols[$class . '::' . $method] = 'method';
                }
                $lastDoc = null;

                continue;
            }

            if (!\in_array($id, [T_WHITESPACE, T_COMMENT, T_FINAL, T_ABSTRACT, T_READONLY, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC], true)) {
                // Modifiers sit between a doc comment and its declaration;
                // anything else detaches the doc comment.
                $lastDoc = null;
            }
        }

        return $symbols;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function endOfAttribute(array $tokens, int $start): int
    {
        $depth = 1;
        $count = \count($tokens);
        for ($j = $start + 1; $j < $count; ++$j) {
            $token = $tokens[$j];
            if ($token === '[' || (\is_array($token) && $token[0] === T_ATTRIBUTE)) {
                ++$depth;
            } elseif ($token === ']' && --$depth === 0) {
                return $j;
            }
        }

        return $count;
    }

    private static function hasApiTag(?string $doc): bool
    {
        return $doc !== null && preg_match('/^\s*(\/\*\*|\*)\s*@api\b/m', $doc) === 1;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function isClassConstant(array $tokens, int $index): bool
    {
        for ($j = $index - 1; $j >= 0; --$j) {
            $previous = $tokens[$j];
            if (\is_array($previous) && $previous[0] === T_WHITESPACE) {
                continue;
            }

            return \is_array($previous) && $previous[0] === T_DOUBLE_COLON;
        }

        return false;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function nextIdentifier(array $tokens, int $start): ?string
    {
        $count = \count($tokens);
        for ($j = $start; $j < $count; ++$j) {
            $token = $tokens[$j];
            if (\is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }
            if (\is_array($token) && $token[0] === T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG) {
                continue;
            }

            return \is_array($token) && $token[0] === T_STRING ? $token[1] : null;
        }

        return null;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function readName(array $tokens, int $start): string
    {
        $count = \count($tokens);
        for ($j = $start; $j < $count; ++$j) {
            $token = $tokens[$j];
            if (\is_array($token) && \in_array($token[0], [T_NAME_QUALIFIED, T_STRING], true)) {
                return $token[1];
            }
            if (!\is_array($token) || $token[0] !== T_WHITESPACE) {
                break;
            }
        }

        return '';
    }
}

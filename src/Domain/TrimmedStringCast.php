<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Domain;

use Middag\Framework\Persistence\Contract\CastInterface;

/**
 * Attribute cast (CastInterface) that trims a string on write and on read, so a
 * stray space in a comment author never reaches the timeline.
 */
final class TrimmedStringCast implements CastInterface
{
    public function get(mixed $value): mixed
    {
        return \is_string($value) ? trim($value) : $value;
    }

    public function set(mixed $value): mixed
    {
        return \is_string($value) ? trim($value) : $value;
    }
}

<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class NegativeDeprecationProofTest extends TestCase
{
    public function testTriggersDeprecation(): void
    {
        trigger_error('negative proof', E_USER_DEPRECATED);
        self::assertTrue(true);
    }
}

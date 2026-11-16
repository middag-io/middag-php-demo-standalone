<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Kernel\Module;

use Middag\Framework\Kernel\Module\AbstractModule;
use RuntimeException;

/**
 * A module that always fails to boot. It is registered only with DEMO_FAULT_MODE=1,
 * to prove that the boot failure policy isolates it and the application still serves.
 */
final class FaultyModule extends AbstractModule
{
    protected const MODULE_IDNUMBER = 'faulty';

    protected const VERSION = '1.0.0';

    public function boot(): void
    {
        throw new RuntimeException('The faulty module refuses to boot.');
    }
}

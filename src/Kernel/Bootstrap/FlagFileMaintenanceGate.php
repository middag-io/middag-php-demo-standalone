<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Kernel\Bootstrap;

use Middag\Framework\Kernel\Contract\MaintenanceGateInterface;

/**
 * Maintenance gate driven by a flag file: the application is under maintenance
 * while the file exists. Bound only with DEMO_FAULT_MODE=1; otherwise the demo
 * binds the framework's NullMaintenanceGate.
 */
final readonly class FlagFileMaintenanceGate implements MaintenanceGateInterface
{
    public function __construct(private string $flagFile) {}

    public function isUnderMaintenance(): bool
    {
        return is_file($this->flagFile);
    }
}

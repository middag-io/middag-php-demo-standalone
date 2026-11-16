<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Security;

use Middag\Framework\Http\Auth\CapabilityReference;
use Middag\Framework\Http\Contract\CapabilityDefinitionInterface;

/**
 * The help-desk supervisor capability, declared as a rich capability definition.
 *
 * AgentController names it in its #[Auth] requirements; the kernel forwards the
 * requirement list to the controller, which resolves each key against the
 * session user. A host adapter would map the same reference onto its own
 * permission system.
 */
final readonly class SuperviseCapability implements CapabilityDefinitionInterface
{
    public const string KEY = 'helpdesk:supervise';

    public function capabilityReference(): CapabilityReference
    {
        return new CapabilityReference(self::KEY);
    }

    public function capabilityOptions(): array
    {
        return [];
    }
}

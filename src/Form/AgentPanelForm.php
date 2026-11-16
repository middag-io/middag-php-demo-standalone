<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Form;

use Middag\Demo\Standalone\Domain\Doctrine\Agent;
use Middag\Framework\Form\AbstractForm;
use Middag\Framework\Form\FieldFactory;
use Middag\Ui\Block\Contract\LayoutElementInterface;
use Middag\Ui\Form\Contract\FieldInterface;
use Symfony\Component\Validator\Constraints\Choice;

/**
 * The agent form the editable side panel of /agents edits: the agent's name,
 * role and active flag. The same form validates the panel's save.
 */
final class AgentPanelForm extends AbstractForm
{
    /** @return array<int, FieldInterface|LayoutElementInterface> */
    public function schema(): array
    {
        return [
            FieldFactory::text('name')->label('Name')->required()->max(120),
            FieldFactory::select('role')->label('Role')->required()
                ->options(array_combine(Agent::ROLES, array_map(ucfirst(...), Agent::ROLES)))
                // The validator does not check a select value against its options,
                // so the allowed roles ride as a custom rule.
                ->rule(new Choice(choices: Agent::ROLES)),
            FieldFactory::toggle('active')->label('Active'),
        ];
    }
}

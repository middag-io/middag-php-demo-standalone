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

use Middag\Demo\Standalone\Domain\Doctrine\SlaPolicy;
use Middag\Demo\Standalone\Domain\Doctrine\SlaPolicyRepository;
use Middag\Framework\Form\AbstractForm;
use Middag\Framework\Form\Contract\FormValidatorInterface;
use Middag\Framework\Form\FieldFactory;
use Middag\Ui\Block\Contract\LayoutElementInterface;
use Middag\Ui\Block\Section;
use Middag\Ui\Form\Contract\FieldInterface;
use Middag\Ui\Form\Group;
use Middag\Ui\Shared\Enum\ConditionOperator;

/**
 * Help-desk ticket form — exercises the framework form pipeline across the
 * reachable field surface: text/textarea, two selects, two entity_pickers backed
 * by the registered data-mapper sources (demo_customers/demo_agents), a date, and
 * a conditional field (agent_id required_when priority is high/urgent — the IN
 * operator + a server-aware condition). SLA options are data-mapper-driven.
 *
 * The schema is laid out in two sections (`ticket` and `schedule`), each holding
 * groups of fields: the layout elements of middag-io/ui (Section, Group), which
 * the InertiaRenderer serializes as `section` and `group` nodes. The create wizard
 * shows one section per step.
 *
 * Field names map 1:1 to CreateTicketCommand / UpdateTicketCommand + the
 * CreateTicketRequest rules. snake_case (AbstractField rejects others).
 */
final class TicketForm extends AbstractForm
{
    public const string SECTION_TICKET = 'ticket';

    public const string SECTION_SCHEDULE = 'schedule';

    public function __construct(FormValidatorInterface $validator, private readonly SlaPolicyRepository $sla)
    {
        parent::__construct($validator);
    }

    /** @return array<int, FieldInterface|LayoutElementInterface> */
    public function schema(): array
    {
        return [
            Section::of(self::SECTION_TICKET)->label('Ticket')->fields(
                Group::of('summary')->fields(
                    FieldFactory::text('subject')->label('Subject')->required()->max(200)
                        ->placeholder('Short summary of the issue'),
                    FieldFactory::textarea('body')->label('Description')->rows(5)->max(5000),
                ),
                Group::of('routing')->fields(
                    FieldFactory::select('channel')->label('Channel')->required()
                        ->options(['email' => 'Email', 'web' => 'Web', 'phone' => 'Phone'])
                        ->default('web'),
                    FieldFactory::select('priority')->label('Priority')->required()
                        ->options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'])
                        ->default('normal'),
                ),
                // Entity pickers backed by the registered data-mapper sources; the
                // autocomplete URLs are the session-gated JSON endpoints (?q=) the lib
                // picker fetches for live search.
                Group::of('people')->fields(
                    FieldFactory::entityPicker('customer_id')->label('Customer')->required()
                        ->source('demo_customers')->displayField('label')->valueField('value')
                        ->autocompleteHref('/api/entities/customers'),
                    FieldFactory::entityPicker('agent_id')->label('Assignee')
                        ->source('demo_agents')->displayField('label')->valueField('value')
                        ->autocompleteHref('/api/entities/agents')
                        // High/urgent tickets must be assigned (IN operator condition).
                        ->requiredWhen('priority', ConditionOperator::In, ['high', 'urgent']),
                ),
            ),
            Section::of(self::SECTION_SCHEDULE)->label('Schedule')->fields(
                FieldFactory::select('sla_policy_id')->label('SLA policy')
                    ->options($this->slaOptions()),
                Group::of('planning')->fields(
                    FieldFactory::text('tags')->label('Tags')->placeholder('comma,separated')->max(200),
                    FieldFactory::date('due_at')->label('Due date')->optional(),
                ),
            ),
        ];
    }

    /** @return array<int, string> id => "name (priority)" */
    private function slaOptions(): array
    {
        // No '' => '— none —' entry: Radix Select (the React SelectField) throws on
        // a SelectItem with an empty value, which crashed the wizard's step 2. The
        // field is optional, so the lib renders a "Select…" placeholder for the
        // unselected/none state, and the backend already maps an empty
        // sla_policy_id to null (see TicketController::wizardConfirm/update).
        $options = [];
        foreach ($this->sla->latest() as $policy) {
            /** @var SlaPolicy $policy */
            $data = $policy->toArray();
            $options[(int) ($data['id'] ?? 0)] = sprintf('%s (%s)', (string) ($data['name'] ?? ''), (string) ($data['priority'] ?? ''));
        }

        return $options;
    }
}

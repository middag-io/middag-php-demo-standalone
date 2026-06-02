<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Form;

use Middag\Demo\Standalone\Domain\Doctrine\SlaPolicy;
use Middag\Demo\Standalone\Domain\Doctrine\SlaPolicyRepository;
use Middag\Framework\Form\AbstractForm;
use Middag\Framework\Form\FieldDefinition;
use Middag\Framework\Form\FormValidator;
use Middag\Ui\Shared\Enum\ConditionOperator;

/**
 * Help-desk ticket form — exercises the framework form pipeline across the
 * reachable field surface: text/textarea, two selects, two entity_pickers backed
 * by the registered data-mapper sources (demo_customers/demo_agents), a date, and
 * a conditional field (agent_id required_when priority is high/urgent — the IN
 * operator + a server-aware condition). SLA options are data-mapper-driven.
 *
 * Field names map 1:1 to CreateTicketCommand / UpdateTicketCommand + the
 * CreateTicketRequest rules. snake_case (AbstractField rejects others).
 */
final class TicketForm extends AbstractForm
{
    public function __construct(FormValidator $validator, private readonly SlaPolicyRepository $sla)
    {
        parent::__construct($validator);
    }

    /** @return array<int, \Middag\Ui\Form\FieldInterface|\Middag\Ui\Block\LayoutElementInterface> */
    public function schema(): array
    {
        return [
            FieldDefinition::text('subject')->label('Subject')->required()->max(200)
                ->placeholder('Short summary of the issue'),
            FieldDefinition::textarea('body')->label('Description')->rows(5)->max(5000),

            FieldDefinition::select('channel')->label('Channel')->required()
                ->options(['email' => 'Email', 'web' => 'Web', 'phone' => 'Phone'])
                ->default('web'),
            FieldDefinition::select('priority')->label('Priority')->required()
                ->options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'])
                ->default('normal'),

            // Entity pickers backed by the registered data-mapper sources; the
            // autocomplete URLs are the session-gated JSON endpoints (?q=) the lib
            // picker fetches for live search.
            FieldDefinition::entityPicker('customer_id')->label('Customer')->required()
                ->source('demo_customers')->displayField('label')->valueField('value')
                ->autocompleteHref('/api/entities/customers'),
            FieldDefinition::entityPicker('agent_id')->label('Assignee')
                ->source('demo_agents')->displayField('label')->valueField('value')
                ->autocompleteHref('/api/entities/agents')
                // High/urgent tickets must be assigned (IN operator condition).
                ->requiredWhen('priority', ConditionOperator::IN, ['high', 'urgent']),

            FieldDefinition::select('sla_policy_id')->label('SLA policy')
                ->options($this->slaOptions()),

            FieldDefinition::text('tags')->label('Tags')->placeholder('comma,separated')->max(200),
            FieldDefinition::date('due_at')->label('Due date')->optional(),
        ];
    }

    /** @return array<int|string, string> id => "name (priority)" */
    private function slaOptions(): array
    {
        $options = ['' => '— none —'];
        foreach ($this->sla->latest() as $policy) {
            /** @var SlaPolicy $policy */
            $data = $policy->toArray();
            $options[(int) ($data['id'] ?? 0)] = sprintf('%s (%s)', (string) ($data['name'] ?? ''), (string) ($data['priority'] ?? ''));
        }

        return $options;
    }
}

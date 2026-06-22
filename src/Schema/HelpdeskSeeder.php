<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Schema;

use Middag\Demo\Standalone\Domain\Doctrine\Agent;
use Middag\Demo\Standalone\Domain\Doctrine\AgentRepository;
use Middag\Demo\Standalone\Domain\Doctrine\Customer;
use Middag\Demo\Standalone\Domain\Doctrine\CustomerRepository;
use Middag\Demo\Standalone\Domain\Doctrine\SlaPolicy;
use Middag\Demo\Standalone\Domain\Doctrine\SlaPolicyRepository;
use Middag\Demo\Standalone\Domain\Eloquent\Comment;
use Middag\Demo\Standalone\Domain\Eloquent\Ticket;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;

/**
 * Idempotent seeder for the help-desk demo dataset.
 *
 * Seeds the data-mapper reference tables (agents/customers/sla policies) through
 * their repositories, then the active-record tickets + comment feed through the
 * process-wide Eloquent Model connection — proving both ORM paradigms write to
 * the same SQLite engine. Re-running install:db is a no-op once agents exist.
 */
final class HelpdeskSeeder
{
    public static function seed(ConnectionAdapterInterface $adapter): void
    {
        $agents = new AgentRepository($adapter);
        if ($agents->findAll() !== []) {
            return; // already seeded
        }

        $sla = new SlaPolicyRepository($adapter);
        $customers = new CustomerRepository($adapter);
        $now = time();

        foreach ([
            ['Urgent SLA', 'urgent', 15, 240],
            ['High SLA', 'high', 30, 480],
            ['Standard SLA', 'normal', 60, 1440],
            ['Low SLA', 'low', 240, 4320],
        ] as [$name, $priority, $resp, $resol]) {
            $sla->save(new SlaPolicy(null, $name, $priority, $resp, $resol, $now));
        }

        foreach ([
            ['Ana Souza', 'ana@middag.io', 'supervisor'],
            ['Bruno Lima', 'bruno@middag.io', 'agent'],
            ['Carla Dias', 'carla@middag.io', 'agent'],
            ['Diego Melo', 'diego@middag.io', 'admin'],
        ] as [$name, $email, $role]) {
            $agents->save(new Agent(null, $name, $email, $role, true, $now));
        }

        foreach ([
            ['Joao Pereira', 'joao@acme.example', '+55 11 99999-0001', 'Acme Corp'],
            ['Maria Silva', 'maria@globex.example', '+55 21 98888-0002', 'Globex'],
            ['Pedro Santos', 'pedro@initech.example', null, 'Initech'],
            ['Lucia Costa', 'lucia@umbrella.example', '+55 31 97777-0003', 'Umbrella'],
            ['Rafael Alves', 'rafael@hooli.example', null, 'Hooli'],
            ['Sofia Nunes', 'sofia@piedpiper.example', '+55 41 96666-0004', 'Pied Piper'],
        ] as [$name, $email, $phone, $company]) {
            $customers->save(new Customer(null, $name, $email, $phone, $company, $now));
        }

        // Reload to get the assigned autoincrement ids for the relations.
        $agentRows = $agents->latest();
        $custRows = $customers->latest();
        $slaByPriority = [];
        foreach ($sla->latest() as $policy) {
            $slaByPriority[$policy->priority] = $policy->getId();
        }

        $agentIds = array_map(static fn (Agent $a): ?int => $a->getId(), $agentRows);
        $custIds = array_map(static fn (Customer $c): ?int => $c->getId(), $custRows);
        $agentNames = [];
        foreach ($agentRows as $a) {
            $agentNames[(int) $a->getId()] = $a->name;
        }
        $custNames = [];
        foreach ($custRows as $c) {
            $custNames[(int) $c->getId()] = $c->name;
        }

        // status, priority, channel, subject, tags, ageDays, [satisfaction|null]
        $specs = [
            ['new', 'urgent', 'phone', 'Cannot log in after password reset', 'auth,login', 0, null],
            ['new', 'high', 'web', 'Invoice PDF export is blank', 'billing,export', 0, null],
            ['open', 'high', 'email', 'API returns 500 on bulk import', 'api,import', 1, null],
            ['open', 'normal', 'web', 'Feature request: dark mode', 'ux,enhancement', 2, null],
            ['open', 'urgent', 'phone', 'Production dashboard not loading', 'outage,dashboard', 1, null],
            ['pending', 'normal', 'email', 'Awaiting customer logs for sync issue', 'sync,waiting', 3, null],
            ['pending', 'low', 'web', 'Clarify pricing tiers', 'sales,pricing', 4, null],
            ['resolved', 'normal', 'web', 'Typo on settings page', 'ui,copy', 5, null],
            ['resolved', 'high', 'email', 'CSV upload encoding fixed', 'import,csv', 6, null],
            ['closed', 'normal', 'web', 'How to invite a teammate', 'onboarding', 8, 5],
            ['closed', 'low', 'email', 'Update billing address', 'billing', 9, 4],
            ['closed', 'high', 'phone', 'Refund processed for duplicate charge', 'billing,refund', 10, 5],
        ];

        $i = 0;
        foreach ($specs as [$status, $priority, $channel, $subject, $tags, $ageDays, $csat]) {
            $createdAt = $now - ($ageDays * 86400) - ($i * 3600);
            $resolvedAt = in_array($status, ['resolved', 'closed'], true)
                ? $createdAt + 7200
                : null;
            $agentId = $agentIds[$i % count($agentIds)];
            $customerId = (int) $custIds[$i % count($custIds)];

            $ticket = new Ticket([
                'subject' => $subject,
                'body' => $subject . ' — reported via ' . $channel . '. Steps and context provided by the customer.',
                'status' => $status,
                'priority' => $priority,
                'channel' => $channel,
                'customer_id' => $customerId,
                'agent_id' => $status === 'new' ? null : $agentId,
                'sla_policy_id' => $slaByPriority[$priority] ?? null,
                'tags' => $tags,
                'satisfaction' => $csat,
                'due_at' => $createdAt + 86400,
                'resolved_at' => $resolvedAt,
                'created_at' => $createdAt,
            ]);
            $ticket->save();
            $ticketId = (int) $ticket->getKey();

            (new Comment([
                'ticket_id' => $ticketId,
                'author' => $custNames[$customerId] ?? 'Customer',
                'body' => 'Opening this ticket. ' . $subject . '.',
                'is_internal' => false,
                'created_at' => $createdAt + 60,
            ]))->save();

            if ($status !== 'new') {
                (new Comment([
                    'ticket_id' => $ticketId,
                    'author' => $agentNames[(int) $agentId] ?? 'Agent',
                    'body' => 'Picked this up — investigating now.',
                    'is_internal' => true,
                    'created_at' => $createdAt + 3600,
                ]))->save();
            }

            ++$i;
        }
    }
}

<?php

declare(strict_types=1);

namespace Middag\Demo\Standalone\Http;

use Middag\Demo\Standalone\Domain\Doctrine\Customer;
use Middag\Demo\Standalone\Domain\Doctrine\CustomerRepository;
use Middag\Demo\Standalone\Http\Concern\RendersPages;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Ui\Page\PageBuilder;
use Middag\Ui\Region\RegionBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Help-desk customers — the `card_grid` block showcase.
 *
 * Reporters reached the data-mapper way (CustomerRepository). The default
 * card_grid variant takes the card title from each row's `name` and renders the
 * remaining columns (email/company/phone) as labelled fields; an emptyState (with
 * a "file the first ticket" CTA) covers the zero-row case.
 */
#[Auth(login: true)]
final class CustomerController extends AbstractController
{
    use RendersPages;

    public function __construct(
        private readonly CustomerRepository $customers,
    ) {}

    public function index(): Response
    {
        $customers = $this->customers->latest();

        $rows = array_map(
            static function (Customer $c): array {
                $d = $c->toArray();

                return [
                    'id' => (int) $d['id'],
                    'name' => (string) $d['name'],
                    'email' => (string) $d['email'],
                    'company' => (string) ($d['company'] ?? '—'),
                    'phone' => (string) ($d['phone'] ?? '—'),
                ];
            },
            $customers,
        );

        $columns = [
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'company', 'label' => 'Company'],
            ['key' => 'phone', 'label' => 'Phone'],
        ];

        $contract = PageBuilder::page('demo.customers')
            ->shell('basic')
            ->layout('stack')
            ->title('Customers')
            ->subtitle('Reporters — data-mapper read rendered as a card_grid')
            ->region('content', function (RegionBuilder $region) use ($columns, $rows): void {
                $region->metricCard('customer_count', count($rows), 'Customers', icon: 'building');
                $region->cardGrid('customers', $columns, $rows, null, [
                    'emptyState' => [
                        'icon' => 'building',
                        'title' => 'No customers yet',
                        'description' => 'Customers are created with their first ticket.',
                        'cta' => ['label' => 'Open a ticket', 'href' => '/tickets/new'],
                    ],
                ]);
            })
            ->build();

        return $this->page($contract);
    }
}

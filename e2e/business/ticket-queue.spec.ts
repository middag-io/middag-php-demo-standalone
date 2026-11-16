import { test, expect, type Page, type Request } from '@playwright/test';

/**
 * The ticket queue in server mode keeps the whole query on every change: the
 * pager, a sort, a search, a filter and the page size each send their own
 * parameter plus the page, per_page, sort, direction, search and filter[...]
 * the table already holds. The spec reads the query of the Inertia request the
 * change sends, then the rows the server answers with.
 *
 * The seeded tickets used here carry the billing tag, which no other spec
 * edits: "Invoice PDF export is blank" (new, high), "Refund processed for
 * duplicate charge" (closed, high) and "Update billing address" (closed, low).
 */
const INVOICE = 'Invoice PDF export is blank';
const REFUND = 'Refund processed for duplicate charge';
const ADDRESS = 'Update billing address';

/** The next Inertia reload of the ticket queue, started by `action`. */
async function queueRequest(page: Page, action: () => Promise<void>): Promise<URLSearchParams> {
  const [request] = await Promise.all([
    page.waitForRequest(
      (r: Request) => new URL(r.url()).pathname === '/tickets' && r.headers()['x-inertia'] === 'true',
    ),
    action(),
  ]);
  await request.response();

  return new URL(request.url()).searchParams;
}

function expectQuery(params: URLSearchParams, expected: Record<string, string>): void {
  for (const [key, value] of Object.entries(expected)) {
    expect(params.get(key), `query parameter ${key}`).toBe(value);
  }
}

async function expectRows(page: Page, visible: string[], absent: string[]): Promise<void> {
  for (const subject of visible) {
    await expect(page.getByText(subject).first()).toBeVisible();
  }
  for (const subject of absent) {
    await expect(page.getByText(subject)).toHaveCount(0);
  }
}

test.describe('ticket queue keeps the query on every change', () => {
  test('the pager keeps search, sort, page size and filters', async ({ page }) => {
    await page.goto('/tickets?search=billing&sort=subject&direction=asc&per_page=2&filter[status]=new,closed');
    await expectRows(page, [INVOICE, REFUND], [ADDRESS]);

    const params = await queueRequest(page, () => page.getByRole('button', { name: 'Next page' }).click());

    expectQuery(params, {
      page: '2',
      per_page: '2',
      sort: 'subject',
      direction: 'asc',
      search: 'billing',
      'filter[status]': 'new,closed',
    });
    await expectRows(page, [ADDRESS], [INVOICE, REFUND]);
    await expect(page.getByText(/^2 \/ 2$/)).toBeVisible();
  });

  test('a sort keeps page, search, page size and filters', async ({ page, isMobile }) => {
    test.skip(isMobile, 'The mobile card list has no sortable column headers.');
    await page.goto('/tickets?search=billing&sort=subject&direction=asc&per_page=2&page=2&filter[status]=new,closed');
    await expectRows(page, [ADDRESS], [INVOICE, REFUND]);

    // Subject is sorted ascending, so the header click asks for descending.
    const params = await queueRequest(page, () => page.getByRole('button', { name: /^Subject/ }).click());

    expectQuery(params, {
      page: '2',
      per_page: '2',
      sort: 'subject',
      direction: 'desc',
      search: 'billing',
      'filter[status]': 'new,closed',
    });
    await expectRows(page, [INVOICE], [ADDRESS, REFUND]);
  });

  test('a search keeps sort, page size and filters and goes back to page 1', async ({ page }) => {
    await page.goto('/tickets?sort=subject&direction=asc&per_page=2&page=2&filter[status]=closed');

    const params = await queueRequest(page, () => page.getByPlaceholder('Search subject or tag').fill('billing'));

    expectQuery(params, {
      page: '1',
      per_page: '2',
      sort: 'subject',
      direction: 'asc',
      search: 'billing',
      'filter[status]': 'closed',
    });
    await expectRows(page, [REFUND, ADDRESS], [INVOICE]);
  });

  test('a filter keeps search, sort and page size and goes back to page 1', async ({ page }) => {
    await page.goto('/tickets?search=billing&sort=subject&direction=desc&per_page=2&page=2');
    await expectRows(page, [INVOICE], [ADDRESS, REFUND]);

    await page.getByRole('button', { name: 'Filter', exact: true }).click();
    await page.getByRole('dialog').getByRole('combobox').first().click();
    const params = await queueRequest(page, () => page.getByRole('option', { name: 'Closed', exact: true }).click());

    expectQuery(params, {
      page: '1',
      per_page: '2',
      sort: 'subject',
      direction: 'desc',
      search: 'billing',
      'filter[status]': 'closed',
    });
    await page.keyboard.press('Escape');
    await expectRows(page, [ADDRESS, REFUND], [INVOICE]);
  });

  test('the page size keeps search, sort and filters and goes back to page 1', async ({ page }) => {
    await page.goto('/tickets?search=billing&sort=subject&direction=desc&per_page=1&page=2&filter[priority]=high');
    await expectRows(page, [INVOICE], [REFUND, ADDRESS]);

    const params = await queueRequest(page, () => page.getByLabel('Rows per page').selectOption('25'));

    expectQuery(params, {
      page: '1',
      per_page: '25',
      sort: 'subject',
      direction: 'desc',
      search: 'billing',
      'filter[priority]': 'high',
    });
    await expectRows(page, [REFUND, INVOICE], [ADDRESS]);
  });
});

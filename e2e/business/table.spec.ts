import { test, expect } from '@playwright/test';

/**
 * The ticket queue in server mode: the free DenseTableBlock sends page, per_page,
 * sort, direction, search and filter[...] as query parameters, and the server
 * answers with one filtered, sorted page. The tag search uses seeded tags that
 * no other spec edits: billing is on three tickets.
 */
test('filters and pages the ticket queue on the server', async ({ page, isMobile }) => {
  await page.goto('/tickets?search=billing&sort=subject&direction=asc&per_page=2');

  await expect(page.getByText('Invoice PDF export is blank').first()).toBeVisible();
  await expect(page.getByText('Refund processed for duplicate charge').first()).toBeVisible();
  await expect(page.getByText('Update billing address')).toHaveCount(0);
  await expect(page.getByText('Cannot log in after password reset')).toHaveCount(0);

  // The desktop table has the pager; the mobile card list leaves it out.
  const next = page.getByRole('button', { name: 'Next page' });
  if (!isMobile) {
    await next.click();
    await expect(page).toHaveURL(/[?&]page=2(&|$)/);
    await expect(page.getByText('Update billing address').first()).toBeVisible();
    await expect(page.getByText('Invoice PDF export is blank')).toHaveCount(0);
  }

  await page.goto('/tickets?search=billing&filter[status]=new');
  await expect(page.getByText('Invoice PDF export is blank').first()).toBeVisible();
  await expect(page.getByText('Update billing address')).toHaveCount(0);
});

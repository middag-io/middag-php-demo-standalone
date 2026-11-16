import { test, expect } from '@playwright/test';

test('the ticket breadcrumb leads back to the list', async ({ page, isMobile }) => {
  test.skip(isMobile, 'the shell collapses the breadcrumb trail on small screens');
  await page.goto('/tickets/2');
  const trail = page.getByRole('navigation', { name: /breadcrumb/i });
  await expect(trail).toContainText('#2');
  await trail.getByRole('link', { name: 'Tickets' }).click();
  await expect(page).toHaveURL(/\/tickets$/);
});

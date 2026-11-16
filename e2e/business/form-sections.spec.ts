import { test, expect } from '@playwright/test';

/**
 * The ticket form is laid out in sections and groups (Section and Group of
 * middag-io/ui). Each section renders as a fieldset named by its label; the
 * edit page shows both, and each wizard step shows only its own section.
 */
test('the edit form renders the ticket and schedule sections', async ({ page }) => {
  await page.goto('/tickets/1/edit');

  const ticket = page.getByRole('group', { name: 'Ticket' });
  const schedule = page.getByRole('group', { name: 'Schedule' });
  await expect(ticket).toBeVisible();
  await expect(schedule).toBeVisible();

  await expect(ticket.getByLabel(/^subject/i)).toBeVisible();
  await expect(ticket.getByRole('combobox', { name: /^customer/i })).toBeVisible();
  await expect(schedule.getByLabel(/^tags/i)).toBeVisible();
  await expect(schedule.getByLabel(/^subject/i)).toHaveCount(0);
});

test('each wizard step shows only its own section', async ({ page }) => {
  await page.goto('/tickets/new?step=1');

  await expect(page.getByRole('group', { name: 'Ticket' })).toBeVisible();
  await expect(page.getByRole('group', { name: 'Schedule' })).toHaveCount(0);
});

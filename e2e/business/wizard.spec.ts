import { test, expect } from '@playwright/test';

/**
 * The two-step create wizard: step 1 holds the required core (validated by
 * CreateTicketRequest), step 2 the schedule, and the confirm step creates the
 * ticket and returns to the queue, where the new ticket is the newest row.
 */
test('creates a ticket through the two-step wizard', async ({ page, isMobile }) => {
  // The wizard keeps its partial in the session, and the desktop and mobile
  // projects share one signed-in session, so one viewport runs it.
  test.skip(isMobile, 'the wizard partial lives in the shared session');
  const subject = `Wizard ticket ${Date.now()}`;

  await page.goto('/tickets/new');
  await page.getByLabel(/^subject/i).fill(subject);
  await page.getByLabel(/^description|^body/i).first().fill('Created by the wizard spec.');

  // The entity picker searches the customers source after two characters.
  const customer = page.getByRole('combobox', { name: /^customer/i });
  await customer.fill('Maria');
  await page.getByRole('option', { name: /Maria Silva/ }).click();

  await page.getByRole('button', { name: /next|continue/i }).first().click();
  await expect(page).toHaveURL(/step=2/);

  await page.getByRole('button', { name: /create|confirm|finish|submit/i }).first().click();
  await expect(page).toHaveURL(/\/tickets$/);
  await expect(page.getByText(subject).first()).toBeVisible();
});

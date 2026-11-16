import { test, expect } from '@playwright/test';

test('closing a ticket asks for confirmation and moves it to closed', async ({ page }, testInfo) => {
  // One open seeded ticket per project: desktop and mobile share the database.
  await page.goto(testInfo.project.name === 'mobile' ? '/tickets/5' : '/tickets/4');
  await page.getByRole('button', { name: /close ticket/i }).first().click();

  const dialog = page.getByRole('alertdialog').or(page.getByRole('dialog'));
  await expect(dialog).toContainText('Close this ticket?');
  await dialog.getByRole('button', { name: /close ticket/i }).click();

  // The POST redirects back to the detail (an Inertia visit): the ticket is now
  // closed, so the action is gone and the lifecycle marks Closed as current.
  await expect(page.getByRole('button', { name: /close ticket/i })).toHaveCount(0);
  await expect(page.getByText(/Closed: Current/i).or(page.getByLabel(/Closed: Current/i)).first()).toBeAttached();
});

import { test, expect } from '@playwright/test';

test('an unknown route renders the 404 error page', async ({ page }) => {
  const response = await page.goto('/no-such-page');
  expect(response?.status()).toBe(404);
  await expect(page.getByTestId('error-page-title')).toContainText('404');
  await page.getByRole('link', { name: /back to the dashboard/i }).click();
  await expect(page).not.toHaveURL(/no-such-page/);
});

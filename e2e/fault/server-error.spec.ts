import { test, expect } from '@playwright/test';
import { login } from '../helpers/auth';

/**
 * Fault server only: /_test/crash throws, and the kernel answers with the 500
 * error page drawn by the demo's ErrorPageRenderer. The main server has no such
 * route (a PHPUnit test proves its 404).
 */
test('a crash renders the 500 error page', async ({ page }) => {
  await login(page);
  await page.waitForURL((url) => !url.pathname.startsWith('/login'));

  const response = await page.goto('/_test/crash');
  expect(response?.status()).toBe(500);
  await expect(page.getByTestId('error-page-title')).toContainText('500');
});

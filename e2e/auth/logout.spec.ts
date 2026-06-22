import { test, expect } from '@playwright/test';
import { login } from '../helpers/auth';

test.describe('logout', () => {
  test('logs out and protected routes bounce back to login', async ({ page }) => {
    await login(page);
    await expect(page).not.toHaveURL(/\/login/);

    // Logout lives behind the "Demo User" account menu in the header.
    await page.getByRole('button', { name: /demo user/i }).click();
    await page.getByRole('menuitem', { name: /log\s?out|sign out|sair/i })
      .or(page.getByRole('button', { name: /log\s?out|sign out|sair/i }))
      .or(page.getByRole('link', { name: /log\s?out|sign out|sair/i }))
      .first()
      .click();

    // Session cleared: a protected route now bounces back to /login.
    await page.goto('/tickets');
    await expect(page).toHaveURL(/\/login/);
  });
});

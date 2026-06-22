import { test, expect } from '@playwright/test';
import { login, DEMO, expectAuthenticated } from '../helpers/auth';

test.describe('authentication', () => {
  test('logs in with valid credentials and lands authenticated', async ({ page }) => {
    await login(page, DEMO.email, DEMO.password);
    await expectAuthenticated(page);
  });

  test('rejects invalid credentials and stays on the login screen', async ({ page }) => {
    await login(page, DEMO.email, 'wrong-password');
    // Invalid auth bounces back to /login (the "Invalid credentials" flash is a
    // transient toast); the durable, non-flaky proof is that the login form is
    // still on screen, i.e. the session was NOT established.
    await expect(page).toHaveURL(/\/login/);
    await expect(page.getByRole('button', { name: /sign in/i })).toBeVisible();
  });

  test('redirects an unauthenticated visitor from a protected route to login', async ({ page }) => {
    await page.goto('/tickets');
    await expect(page).toHaveURL(/\/login/);
  });
});

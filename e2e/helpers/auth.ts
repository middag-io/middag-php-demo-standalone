import { type Page, expect } from '@playwright/test';

/** Seeded demo login (User::seedDemo / HelpdeskSeeder). */
export const DEMO = { email: 'demo@middag.io', password: 'middag' } as const;

/** Drive the contract-driven login form. Resilient, role/label-based selectors. */
export async function login(page: Page, email = DEMO.email, password = DEMO.password): Promise<void> {
  await page.goto('/login');
  // Anchor the label regexes: a "Show password" toggle button also carries the
  // word "password", so an unanchored /password/i matches two elements.
  await page.getByLabel(/^e-?mail/i).fill(email);
  await page.getByLabel(/^password/i).fill(password);
  await page.getByRole('button', { name: /sign in|entrar|log\s?in/i }).click();
}

/** Assert the session is authenticated (a protected route does NOT bounce to /login). */
export async function expectAuthenticated(page: Page): Promise<void> {
  await expect(page).not.toHaveURL(/\/login/);
}

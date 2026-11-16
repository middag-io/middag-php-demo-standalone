import { test, expect } from '@playwright/test';
import { existsSync, rmSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { login } from '../helpers/auth';
import type { Page } from '@playwright/test';

async function signIn(page: Page): Promise<void> {
  await login(page);
  await page.waitForURL((url) => !url.pathname.startsWith('/login'));
}

/**
 * The second server (DEMO_FAULT_MODE=1, its own database): an isolated module,
 * the maintenance gate and database isolation from the main server.
 */
const FLAG = fileURLToPath(new URL('../../var/maintenance.flag', import.meta.url));
const MAIN = `http://localhost:${process.env.E2E_PORT ?? 8080}`;

test.afterEach(() => {
  if (existsSync(FLAG)) rmSync(FLAG);
});

test('a module that fails to boot is isolated and reported on the dashboard', async ({ page }) => {
  await signIn(page);
  await page.goto('/');
  await expect(page.getByText('Isolated modules')).toBeVisible();
});

test('the maintenance flag closes the application and reopens it', async ({ page }) => {
  writeFileSync(FLAG, '');
  const closed = await page.goto('/help');
  expect(closed?.status()).toBe(503);
  await expect(page.getByTestId('maintenance-title')).toBeVisible();

  rmSync(FLAG);
  const open = await page.goto('/help');
  expect(open?.status()).toBe(200);
});

test('a change on the fault server does not reach the main database', async ({ page, request }) => {
  const subject = `Fault-only subject ${Date.now()}`;
  await signIn(page);
  await page.goto('/tickets/3/edit');
  await page.getByLabel(/^subject/i).fill(subject);
  await page.getByRole('button', { name: /save|update|apply/i }).first().click();
  await expect(page.getByText(subject).first()).toBeVisible();

  const main = await request.get(`${MAIN}/help`);
  expect(main.status()).toBe(200);
  const mainDetail = await request.get(`${MAIN}/tickets/3`, { maxRedirects: 0 });
  expect(await mainDetail.text()).not.toContain(subject);
});

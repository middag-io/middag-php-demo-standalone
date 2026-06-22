import { test, expect } from '@playwright/test';

/**
 * Authenticated (storageState from the setup project). Navigation is driven by
 * direct visits + per-page render assertions on the contract-driven page titles
 * and subtitles — resilient across the desktop and mobile projects (the mobile
 * sidebar collapses the menu links, so in-app menu clicks are a separate,
 * documented coverage extension rather than baked into every viewport here).
 */
test.describe('primary navigation', () => {
  test('dashboard renders at the root', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByText('Help-desk dashboard')).toBeVisible();
  });

  test('tickets list renders', async ({ page }) => {
    await page.goto('/tickets');
    await expect(page).toHaveURL(/\/tickets$/);
    await expect(page.getByRole('link', { name: /new ticket/i })).toBeVisible();
    // A seeded ticket subject proves the queue rendered its rows — and survives
    // the responsive switch from a desktop table to mobile cards (no columnheaders).
    await expect(
      page.getByText(/Cannot log in|API returns 500|Awaiting customer/i).first(),
    ).toBeVisible();
  });

  test('opens a ticket detail', async ({ page }) => {
    await page.goto('/tickets/1');
    await expect(page).toHaveURL(/\/tickets\/1$/);
    await expect(page.getByText(/Ticket detail/i)).toBeVisible();
  });

  test('agents page renders', async ({ page }) => {
    await page.goto('/agents');
    await expect(page).toHaveURL(/\/agents$/);
    // A seeded agent name proves the page rendered its data (not just the shell).
    await expect(page.getByText(/Ana Souza|Bruno Lima|Carla Dias|Diego Melo/).first()).toBeVisible();
  });
});

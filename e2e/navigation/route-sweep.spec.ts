import { test, expect } from '@playwright/test';

/**
 * Visits every authenticated page route and fails when the renderer could not
 * render what the server emitted: an invalid page contract, a block type with no
 * registered renderer, an unknown layout or shell, or an uncaught page error.
 *
 * The renderer shows the first two as a role="alert" with a fixed title (in the
 * active locale, English here). Flash and validation messages can also use
 * role="alert", so the check matches on those titles, not on the role alone.
 */
const ROUTES = [
  '/',
  '/tickets',
  '/tickets/1',
  '/tickets/1/edit',
  '/tickets/new',
  '/tickets/new?step=1',
  '/agents',
  '/agents/1',
  '/customers',
  '/parity',
  '/help',
  '/coverage',
] as const;

const RENDER_FAILURES = /Invalid page contract\.|Unknown block type:|Unknown layout template:|Unknown shell:|Unknown field component/;

test.describe('route sweep', () => {
  for (const route of ROUTES) {
    test(`renders ${route} without a contract or block error`, async ({ page }) => {
      const pageErrors: string[] = [];
      page.on('pageerror', (error) => pageErrors.push(error.message));

      const response = await page.goto(route);
      expect(response?.status(), `${route} answers 200`).toBe(200);
      await page.waitForLoadState('networkidle');

      await expect(page.getByRole('alert').filter({ hasText: RENDER_FAILURES })).toHaveCount(0);
      expect(pageErrors, `${route} raises no uncaught page error`).toEqual([]);
    });
  }
});

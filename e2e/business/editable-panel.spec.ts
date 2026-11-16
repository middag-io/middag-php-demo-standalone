import { test, expect } from '@playwright/test';

/**
 * The editable side panel on /agents (signed in as the supervisor). The row's
 * "Edit" action carries the `panel` target: the free EditablePanelProvider, which
 * the demo mounts, reads the form from GET /agents/{id}/panel, and the save goes
 * back through the Inertia router to POST /agents/{id}/panel.
 */
test('edits an agent in the side panel', async ({ page, isMobile }) => {
  const name = `Diego Melo ${Date.now()}`;

  await page.goto('/agents');
  // The table renders rows on desktop and cards on a phone; a single row action
  // renders inline as a button in either.
  const row = page.getByRole(isMobile ? 'button' : 'row', { name: /Diego Melo/ }).first();
  await row.getByRole('button', { name: 'Edit' }).click();

  const panel = page.getByRole('dialog');
  await expect(panel).toBeVisible();
  await expect(panel.getByRole('heading', { name: /Diego Melo/ })).toBeVisible();
  await expect(panel.getByText('Agent', { exact: true })).toBeVisible();
  const field = panel.getByLabel(/^name/i);
  await expect(field).toHaveValue(/Diego Melo/);
  await field.fill(name);
  await panel.getByRole('button', { name: 'Save agent' }).click();

  await expect(panel).toBeHidden();
  await expect(page.getByText(name).first()).toBeVisible();
});

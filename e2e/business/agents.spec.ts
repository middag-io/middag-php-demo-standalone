import { test, expect } from '@playwright/test';

/**
 * The agents index, built by the ui CrudBuilder (signed in as the supervisor):
 * the variant cells its column configurators select, the server-side search and
 * name sort, the row link to the agent detail, and the "Edit" row action with the
 * `panel` target. The free EditablePanelProvider, which the demo mounts, reads the form
 * from GET /agents/{id}/panel, and the save goes back through the Inertia router
 * to POST /agents/{id}/panel. The detail page carries the cells CrudBuilder does
 * not express: the mailto `link` cell and the custom `sparkline` cell.
 *
 * Every test runs in the desktop and the mobile project: the table renders rows
 * on desktop and cards on a phone. The header-click sort runs on desktop only,
 * since the cards have no column headers.
 */
test('renders the roster with its variant cells and metrics', async ({ page }) => {
  await page.goto('/agents');

  // metrics region, added through CrudBuilder::page().
  await expect(page.getByText('Supervisors', { exact: true })).toBeVisible();

  await expect(page.getByText('Ana Souza').first()).toBeVisible();
  // badge cell (role), html cell (availability), progress cell (open load).
  await expect(page.getByText('supervisor', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Available').first()).toBeVisible();
  await expect(page.getByRole('progressbar').first()).toBeVisible();
  await expect(page.getByText('Open load').first()).toBeVisible();
});

test('sorts the roster by name on the server', async ({ page }) => {
  await page.goto('/agents?sort=name&direction=desc');

  // The server answers in the requested order: Carla Dias before Ana Souza.
  // (Diego Melo is left out: the panel test renames him.)
  const names = page.getByText(/^(Ana Souza|Bruno Lima|Carla Dias)$/);
  await expect(names.first()).toHaveText('Carla Dias');
  await expect(names.last()).toHaveText('Ana Souza');
});

test('sorts the roster from the Name header', async ({ page, isMobile }) => {
  test.skip(isMobile, 'the phone card list has no column headers');

  await page.goto('/agents');

  // The default sort (name, ascending) is the active one when the page loads.
  const header = page.getByRole('columnheader', { name: /name/i });
  const names = page.getByText(/^(Ana Souza|Bruno Lima|Carla Dias)$/);
  await expect(header).toHaveAttribute('aria-sort', 'ascending');
  await expect(names.first()).toHaveText('Ana Souza');

  // First click: the server sorts descending and the header shows it.
  const descending = page.waitForRequest((r) => new URL(r.url()).pathname === '/agents');
  await header.getByRole('button').click();
  const descendingParams = new URL((await descending).url()).searchParams;
  expect(descendingParams.get('sort')).toBe('name');
  expect(descendingParams.get('direction')).toBe('desc');
  await expect(header).toHaveAttribute('aria-sort', 'descending');
  await expect(names.first()).toHaveText('Carla Dias');
  await expect(names.last()).toHaveText('Ana Souza');

  // Second click clears the sort: the request carries none and the server falls
  // back to the default, which the header shows again.
  const cleared = page.waitForRequest((r) => new URL(r.url()).pathname === '/agents');
  await header.getByRole('button').click();
  const clearedParams = new URL((await cleared).url()).searchParams;
  expect(clearedParams.has('sort')).toBe(false);
  expect(clearedParams.has('direction')).toBe(false);
  await expect(header).toHaveAttribute('aria-sort', 'ascending');
  await expect(names.first()).toHaveText('Ana Souza');
  await expect(names.last()).toHaveText('Carla Dias');
});

test('searches the roster on the server', async ({ page }) => {
  await page.goto('/agents');
  await expect(page.getByText('Bruno Lima').first()).toBeVisible();

  await page.getByPlaceholder(/search/i).fill('carla');

  await expect(page).toHaveURL(/search=carla/);
  await expect(page.getByText('Carla Dias').first()).toBeVisible();
  await expect(page.getByText('Bruno Lima')).toHaveCount(0);
});

test('a row opens the agent detail', async ({ page }) => {
  await page.goto('/agents');

  await page.getByText('Bruno Lima', { exact: true }).first().click();

  await expect(page).toHaveURL(/\/agents\/\d+$/);
  await expect(page.getByText('bruno@middag.io').first()).toBeVisible();
});

test('the detail page shows the mailto link and the intake sparkline', async ({ page }) => {
  await page.goto('/agents/1');

  const mail = page.getByRole('link', { name: 'ana@middag.io' });
  await expect(mail).toBeVisible();
  await expect(mail).toHaveAttribute('href', 'mailto:ana@middag.io');
  await expect(page.getByRole('img', { name: /^trend: / })).toBeVisible();
  await expect(page.getByText('7-day intake').first()).toBeVisible();
});

test('edits an agent in the side panel', async ({ page, isMobile }) => {
  const name = `Diego Melo ${Date.now()}`;

  await page.goto('/agents');
  // A single row action renders inline as a button on a desktop row and on a
  // phone card.
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

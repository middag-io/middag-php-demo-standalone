import { test, expect } from '@playwright/test';

/**
 * Ticket attachments, end to end. The free file field posts the picked file
 * (multipart); the demo stores it as a pending upload and renders the form again
 * with the file as a FileValue that carries an upload token. Submitting that form
 * sends the value back, and the token attaches the file. The attached file then
 * downloads with the uploaded bytes.
 */
test('uploads a file, attaches it by its upload token and downloads it', async ({ page }, testInfo) => {
  // Desktop and mobile share one database: a unique name per run and project.
  const name = `notes-${testInfo.project.name}-${Date.now()}.txt`;
  const contents = `Attachment proof for ${name}\n`;

  await page.goto('/tickets/2/attachments');
  await page.locator('input[type="file"]').setInputFiles({
    name,
    mimeType: 'text/plain',
    buffer: Buffer.from(contents),
  });
  await expect(page.getByText(name).first()).toBeVisible();
  await page.getByRole('button', { name: 'Upload' }).click();

  // The pending upload comes back in the form, keyed by its token.
  await expect(page).toHaveURL(/\/tickets\/2\/attachments\?token=[0-9a-f]{32}$/);
  await expect(page.getByText(name).first()).toBeVisible();
  await expect(page.getByRole('link', { name: new RegExp(name) })).toHaveCount(0);

  // The untouched file value, token included, goes back with the form.
  await page.getByRole('button', { name: 'Attach file' }).click();
  await expect(page).toHaveURL(/\/tickets\/2\/attachments$/);
  const link = page.getByRole('link', { name: new RegExp(name) });
  await expect(link).toBeVisible();

  const href = await link.getAttribute('href');
  expect(href).toMatch(/^\/tickets\/2\/attachments\/\d+$/);
  const download = await page.request.get(href!);
  expect(download.status()).toBe(200);
  expect(download.headers()['content-disposition']).toContain(`attachment; filename=${name}`);
  expect(download.headers()['x-content-type-options']).toBe('nosniff');
  expect(await download.text()).toBe(contents);
});

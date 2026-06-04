import { test, expect } from '@playwright/test';

/**
 * Authenticated (storageState). Core help-desk business states: the error path
 * (required-field validation, which dogfoods the framework v0.11.0 i18n error
 * contract in the browser) and a success path (editing a seeded ticket through
 * the prefilled form_panel, which PUTs and redirects).
 */
test.describe('ticket lifecycle', () => {
  test('surfaces a validation message when a required field is empty (i18n contract)', async ({ page }) => {
    await page.goto('/tickets/new');

    // Advance the create wizard with an empty Subject -> required-field validation.
    await page.getByRole('button', { name: /continue|next|próximo|avançar/i }).first().click();

    // v0.11.0 resolves each error to a human {message} (EN by default).
    await expect(
      page.getByText(/should not be blank|must not be blank|is required|obrigat/i).first(),
    ).toBeVisible();
    await expect(page).toHaveURL(/\/tickets\/new/);
  });

  test('edits a seeded ticket and persists the change', async ({ page }) => {
    const subject = `E2E edited ${Date.now()}`;

    await page.goto('/tickets/1/edit');
    await expect(page.getByText(/Edit ticket #1/i)).toBeVisible();

    const subjectField = page.getByLabel(/^subject/i);
    await subjectField.fill(subject);
    await page.getByRole('button', { name: /save|update|salvar|atualizar|apply/i }).first().click();

    // The PUT redirects off the edit screen; the new subject is now shown.
    await expect(page).not.toHaveURL(/\/edit$/);
    await expect(page.getByText(subject).first()).toBeVisible();
  });
});

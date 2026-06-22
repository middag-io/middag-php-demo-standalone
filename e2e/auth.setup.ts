import { test as setup, expect } from '@playwright/test';
import { login, DEMO } from './helpers/auth';

const authFile = 'fixtures/.auth/user.json';

/**
 * Logs in once and persists the authenticated storage state, reused by the
 * desktop/mobile projects so navigation/business specs start authenticated.
 */
setup('authenticate', async ({ page }) => {
  await login(page, DEMO.email, DEMO.password);
  await expect(page).not.toHaveURL(/\/login/);
  await page.context().storageState({ path: authFile });
});

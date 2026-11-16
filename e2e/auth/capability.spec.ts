import { test, expect, type Page } from '@playwright/test';
import { login, AGENT, DEMO, expectAuthenticated } from '../helpers/auth';
import { csrfToken } from '../helpers/csrf';

/**
 * The helpdesk:supervise gate, proved with the seeded agent login, which does
 * not hold the capability. The agents page hides the supervisor columns and the
 * "Edit" panel action, and the panel endpoints answer 403 to a direct call. The
 * supervisor reads the same endpoint, so the 403 comes from the capability and
 * not from the route. The phone run checks the card layout of the same page.
 */
const DENIED_NAME = 'Denied rename';

async function expectNoSupervisorSurface(page: Page): Promise<void> {
  await page.goto('/agents');
  // The roster itself is open to every agent.
  await expect(page.getByText('Ana Souza').first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'Edit' })).toHaveCount(0);
  await expect(page.getByText('Open load', { exact: true })).toHaveCount(0);
  await expect(page.getByText('7-day intake', { exact: true })).toHaveCount(0);
}

async function expectPanelDenied(page: Page): Promise<void> {
  const read = await page.request.get('/agents/1/panel', { headers: { Accept: 'application/json' } });
  expect(read.status()).toBe(403);

  const token = await csrfToken(page.request);
  const save = await page.request.post('/agents/1/panel', {
    headers: { Accept: 'application/json', 'X-CSRF-Token': token },
    form: { name: DENIED_NAME, role: 'agent', active: '1' },
  });
  expect(save.status()).toBe(403);
}

test.describe('supervisor capability', () => {
  test('an agent without helpdesk:supervise gets no supervisor surface', async ({ page }) => {
    await login(page, AGENT.email, AGENT.password);
    await expectAuthenticated(page);

    await expectNoSupervisorSurface(page);
    await expectPanelDenied(page);

    await page.goto('/agents');
    await expect(page.getByText(DENIED_NAME)).toHaveCount(0);
  });

  test('the supervisor reads the panel the agent is denied', async ({ page }) => {
    await login(page, DEMO.email, DEMO.password);
    await expectAuthenticated(page);

    const read = await page.request.get('/agents/1/panel', { headers: { Accept: 'application/json' } });
    expect(read.status()).toBe(200);
    expect((await read.json()).submitLabel).toBe('Save agent');
  });
});

test.describe('supervisor capability on a phone', () => {
  test.use({ viewport: { width: 412, height: 839 }, isMobile: true, hasTouch: true });

  test('an agent without helpdesk:supervise gets no supervisor surface', async ({ page }) => {
    await login(page, AGENT.email, AGENT.password);
    await expectAuthenticated(page);

    await expectNoSupervisorSurface(page);
    await expectPanelDenied(page);
  });
});

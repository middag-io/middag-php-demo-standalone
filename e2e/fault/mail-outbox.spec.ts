import { test, expect, type APIRequestContext } from '@playwright/test';
import { login } from '../helpers/auth';

/**
 * Fault server only (mail capture on): creating a ticket sends the "ticket
 * created" mail with the ticket summary attached. The spec reads the captured
 * mail over HTTP from `/_test/outbox`, a route that exists only with
 * DEMO_FAULT_MODE=1, so it never reads the server's var/mail directory.
 */
interface CapturedMail {
  subject: string;
  attachments: string[];
}

async function capturedMailFor(request: APIRequestContext, subject: string): Promise<CapturedMail | undefined> {
  const response = await request.get('/_test/outbox', { headers: { Accept: 'application/json' } });
  if (!response.ok()) {
    return undefined;
  }
  const { data } = (await response.json()) as { data: CapturedMail[] };
  return data.find((mail) => typeof mail.subject === 'string' && mail.subject.includes(subject));
}

test('a new ticket sends the captured mail with its attachment', async ({ page }) => {
  await login(page);
  await page.waitForURL((url) => !url.pathname.startsWith('/login'));

  const subject = `Mail outbox ${Date.now()}`;
  const cookie = (await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN');
  expect(cookie).toBeDefined();
  const response = await page.request.post('/api/tickets', {
    headers: { Accept: 'application/json', 'X-CSRF-Token': decodeURIComponent(cookie!.value) },
    form: { subject, customer_id: '1', priority: 'normal', channel: 'web' },
  });
  expect(response.status()).toBe(201);
  const { id } = await response.json();

  await expect.poll(() => capturedMailFor(page.request, subject)).toBeDefined();
  expect((await capturedMailFor(page.request, subject))!.attachments).toContain(`ticket-${id}.txt`);
});

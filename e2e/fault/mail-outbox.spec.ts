import { test, expect } from '@playwright/test';
import { readdirSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { login } from '../helpers/auth';

/**
 * Fault server only (mail capture on): creating a ticket sends the "ticket
 * created" mail with the ticket summary attached, and the capture mailer writes
 * it as JSON under var/mail/.
 */
const MAIL_DIR = fileURLToPath(new URL('../../var/mail/', import.meta.url));

function capturedMailFor(subject: string): { subject: string; attachments: string[] } | undefined {
  let files: string[] = [];
  try {
    files = readdirSync(MAIL_DIR).filter((name) => name.endsWith('.json'));
  } catch {
    return undefined;
  }
  return files
    .map((name) => JSON.parse(readFileSync(MAIL_DIR + name, 'utf8')))
    .find((mail) => typeof mail.subject === 'string' && mail.subject.includes(subject));
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

  await expect.poll(() => capturedMailFor(subject)).toBeDefined();
  expect(capturedMailFor(subject)!.attachments).toContain(`ticket-${id}.txt`);
});

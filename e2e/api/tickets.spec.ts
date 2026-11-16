import { test, expect } from '@playwright/test';
import { csrfToken } from '../helpers/csrf';

/**
 * The JSON API with the authenticated storage state: the two validation styles,
 * CSRF, the CORS allowlist and the route middleware request id. No browser.
 */
test.describe('ticket API', () => {
  test('creates a ticket through the rules() request (201)', async ({ request }) => {
    const token = await csrfToken(request);
    const response = await request.post('/api/tickets', {
      headers: { Accept: 'application/json', 'X-CSRF-Token': token },
      form: { subject: 'API created ticket', customer_id: '1', priority: 'normal', channel: 'web' },
    });
    expect(response.status()).toBe(201);
    const body = await response.json();
    expect(typeof body.id).toBe('number');
    expect(body.ref).toHaveLength(12);
  });

  test('rejects an invalid ticket through the typed DTO (422)', async ({ request }) => {
    const token = await csrfToken(request);
    const response = await request.post('/api/tickets/dto', {
      headers: { Accept: 'application/json', 'X-CSRF-Token': token },
      form: { priority: 'high' },
    });
    expect(response.status()).toBe(422);
    expect((await response.json()).error).toBe('validation_failed');
  });

  test('refuses a write without the CSRF token', async ({ request }) => {
    const response = await request.post('/api/tickets', {
      headers: { Accept: 'application/json' },
      form: { subject: 'No token', customer_id: '1' },
    });
    expect(response.status()).toBe(419);
  });

  test('answers CORS for the allowed origin only and stamps a request id', async ({ request }) => {
    const allowed = await request.get('/api/entities/customers', {
      headers: { Accept: 'application/json', Origin: 'http://localhost:5173' },
    });
    expect(allowed.headers()['access-control-allow-origin']).toBe('http://localhost:5173');
    expect(allowed.headers()['x-request-id']).toMatch(/^[0-9a-f-]{36}$/);

    const other = await request.get('/api/entities/customers', {
      headers: { Accept: 'application/json', Origin: 'https://elsewhere.example' },
    });
    expect(other.headers()['access-control-allow-origin']).toBeUndefined();
  });
});

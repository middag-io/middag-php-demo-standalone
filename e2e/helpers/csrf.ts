import type { APIRequestContext } from '@playwright/test';

/**
 * The CSRF token the PSR-15 pipeline hands out in the readable XSRF-TOKEN cookie
 * (VerifyCsrfMiddleware). A GET primes it; unsafe calls echo it back as a header.
 */
export async function csrfToken(request: APIRequestContext): Promise<string> {
  await request.get('/help');
  const state = await request.storageState();
  const cookie = state.cookies.find((c) => c.name === 'XSRF-TOKEN');
  if (!cookie) {
    throw new Error('XSRF-TOKEN cookie not set');
  }
  return decodeURIComponent(cookie.value);
}

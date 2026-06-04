import { defineConfig, devices } from '@playwright/test';

/**
 * Full-stack E2E for the MIDDAG help-desk demo. The webServer seeds a fresh
 * SQLite database and boots the PHP built-in server (which serves the Inertia
 * shell + the built React bundle from public/build). Locally an already-running
 * `composer serve` on :8080 is reused; in CI a fresh server is started.
 */
const BASE_URL = process.env.E2E_BASE_URL ?? 'http://localhost:8080';
const STORAGE = 'fixtures/.auth/user.json';

export default defineConfig({
  testDir: '.',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  timeout: 30_000,
  expect: { timeout: 7_000 },
  reporter: process.env.CI
    ? [['github'], ['html', { open: 'never' }]]
    : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    { name: 'setup', testMatch: /auth\.setup\.ts/ },
    {
      name: 'auth-flows',
      testMatch: /auth\/.*\.spec\.ts/,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'desktop',
      testMatch: /(navigation|business)\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: { ...devices['Desktop Chrome'], storageState: STORAGE },
    },
    {
      name: 'mobile',
      testMatch: /(navigation|business)\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: { ...devices['Pixel 7'], storageState: STORAGE },
    },
  ],
  webServer: {
    command: 'cd .. && composer install:db && composer serve',
    url: BASE_URL,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
    stdout: 'ignore',
    stderr: 'pipe',
  },
});

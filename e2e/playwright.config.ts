import { defineConfig, devices } from '@playwright/test';

/**
 * Full-stack E2E for the MIDDAG help-desk demo. The webServer seeds a fresh
 * SQLite database and boots the PHP built-in server (which serves the Inertia
 * shell + the built React bundle from public/build). Locally an already-running
 * server on the port is reused; in CI a fresh server is started. E2E_PORT moves
 * the server off :8080 when that port is taken.
 */
const PORT = Number(process.env.E2E_PORT ?? 8080);
const FAULT_PORT = Number(process.env.E2E_FAULT_PORT ?? PORT + 1);
const BASE_URL = `http://localhost:${PORT}`;
const FAULT_URL = `http://localhost:${FAULT_PORT}`;
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
    {
      name: 'api',
      testMatch: /api\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: { storageState: STORAGE },
    },
    {
      name: 'errors',
      testMatch: /errors\/.*\.spec\.ts/,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      // Second server: DEMO_FAULT_MODE=1 on its own database. Serial, because
      // the maintenance flag is server state.
      name: 'fault',
      testMatch: /fault\/.*\.spec\.ts/,
      fullyParallel: false,
      workers: 1,
      use: { ...devices['Desktop Chrome'], baseURL: FAULT_URL },
    },
  ],
  webServer: [
    {
      command: `cd .. && php bin/console install:db && php -S localhost:${PORT} -t public public/index.php`,
      url: BASE_URL,
      reuseExistingServer: !process.env.CI,
      timeout: 120_000,
      stdout: 'ignore',
      stderr: 'pipe',
    },
    {
      // The variables reach the PHP process environment; DemoEnv reads them even
      // when variables_order leaves $_ENV empty.
      command: `cd .. && rm -f var/fault.sqlite && php bin/console install:db && php -S localhost:${FAULT_PORT} -t public public/index.php`,
      url: `${FAULT_URL}/login`,
      reuseExistingServer: !process.env.CI,
      timeout: 120_000,
      stdout: 'ignore',
      stderr: 'pipe',
      env: { DB_DSN: 'sqlite:var/fault.sqlite', DEMO_FAULT_MODE: '1', MAIL_CAPTURE: '1' },
    },
  ],
});

import { defineConfig, devices } from '@playwright/test';

// Determine the base URL
// Priority: E2E_BASE_URL env var > DDEV_PRIMARY_URL (for DDEV) > localhost:5273
const baseURL = process.env.E2E_BASE_URL || process.env.DDEV_PRIMARY_URL || 'http://localhost:5273';

// Check if we're using DDEV
const isDDEV = !!process.env.DDEV_PRIMARY_URL;
const connectWsEndpoint = process.env.PW_TEST_CONNECT_WS_ENDPOINT || (isDDEV ? 'ws://playwright:3000/' : undefined);
const shouldStartLaravelServer = process.env.E2E_START_LARAVEL_SERVER === '1';
const testingEnvPrefix = [
  'APP_ENV=testing',
  'DB_DATABASE=db_test',
  'SCOUT_DRIVER=collection',
  'MEILISEARCH_HOST=http://localhost:9999',
  'SESSION_DRIVER=database',
].join(' ');

export default defineConfig({
  testDir: 'tests/e2e/specs',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    // Ignore HTTPS errors for DDEV self-signed certificates
    ignoreHTTPSErrors: isDDEV,
    connectOptions: connectWsEndpoint ? { wsEndpoint: connectWsEndpoint } : undefined,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  reporter: [['list']],

  webServer: shouldStartLaravelServer
    ? {
        command: `bun run build && ${testingEnvPrefix} php artisan migrate:fresh --env=testing && ${testingEnvPrefix} php artisan serve --env=testing --host=0.0.0.0 --port=8088 --no-reload`,
        url: 'http://127.0.0.1:8088/login',
        reuseExistingServer: true,
        timeout: 120_000,
        stdout: 'pipe',
        stderr: 'pipe',
      }
    : !isDDEV
      ? {
          command: 'npm run dev',
          url: baseURL,
          reuseExistingServer: true,
          timeout: 120_000,
          stdout: 'pipe',
          stderr: 'pipe',
        }
      : undefined,
});

import { defineConfig, devices } from '@playwright/test';

// Determine the base URL
// Priority: E2E_BASE_URL env var > DDEV_PRIMARY_URL (for DDEV) > localhost:5173
const baseURL = process.env.E2E_BASE_URL || process.env.DDEV_PRIMARY_URL || 'http://localhost:5173';

// Check if we're using DDEV
const isDDEV = !!process.env.DDEV_PRIMARY_URL;

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
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  reporter: [['list']],

  // Only auto-start dev server if not using DDEV
  // For DDEV, the server should already be running
  ...(!isDDEV && {
    webServer: {
      command: 'npm run dev',
      url: baseURL,
      reuseExistingServer: true,
      timeout: 120_000,
      stdout: 'pipe',
      stderr: 'pipe',
    },
  }),
});


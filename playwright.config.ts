import { defineConfig, devices } from '@playwright/test';

/**
 * P2P end-to-end suite for Ramcar Spend Flow.
 *
 * The suite drives the real Filament UI against the Herd site. It never clicks
 * "Approve" on the AP Supervisor screen, so nothing is ever parked/pushed to SAP.
 *
 * Before the run, `global-setup.ts` seeds 12 fresh pending goods receipts under
 * the E2E PO/GR prefixes (4599xxxxxx / 5999xxxxxx) and writes the manifest the
 * specs read. That keeps the suite repeatable without touching other local data.
 *
 * Run: npx playwright test
 * Headed: npx playwright test --headed
 * One scenario: npx playwright test tests/e2e/p2p/returns.spec.ts
 */
export default defineConfig({
    testDir: './tests/e2e/p2p',
    globalSetup: './tests/e2e/p2p/global-setup.ts',
    fullyParallel: false,
    // The scenarios share one database, so they must not run concurrently.
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    timeout: 300_000,
    expect: { timeout: 20_000 },
    outputDir: 'storage/app/playwright-results',
    reporter: [
        ['list'],
        ['html', { open: 'never', outputFolder: 'storage/app/playwright-report' }],
    ],
    use: {
        baseURL: process.env.P2P_BASE_URL ?? 'http://ramcarspendflow.test',
        // Reuse the installed Chrome so no browser download is needed.
        channel: 'chrome',
        headless: true,
        ignoreHTTPSErrors: true,
        viewport: { width: 1500, height: 1000 },
        actionTimeout: 30_000,
        navigationTimeout: 60_000,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'off',
    },
    projects: [
        {
            name: 'p2p',
            use: { ...devices['Desktop Chrome'], channel: 'chrome' },
        },
    ],
});

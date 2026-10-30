import { defineConfig, devices } from '@playwright/test';
export default defineConfig({
    testDir: './tests/browser',
    fullyParallel: false,
    workers: 1,
    timeout: 45000,
    use: { baseURL: 'http://127.0.0.1:8010', trace: 'retain-on-failure', screenshot: 'only-on-failure' },
    reporter: [['list'], ['html', { open: 'never' }]],
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
        { name: 'mobile', use: { ...devices['iPhone 13'], defaultBrowserType: 'chromium' } },
    ],
});

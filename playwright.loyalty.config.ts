import { defineConfig } from '@playwright/test';
import fs from 'node:fs';
const { database } = JSON.parse(fs.readFileSync('test-results/loyalty-qa-env.json', 'utf8'));
export default defineConfig({
    outputDir: 'test-results/loyalty-v2-run',
    testDir: './tests/Browser/loyalty-v2',
    workers: 1,
    fullyParallel: false,
    timeout: 90000,
    reporter: [['list'], ['json', { outputFile: 'docs/audits/loyalty-v2/e2e-results.json' }]],
    use: {
        actionTimeout: 15000,
        baseURL: 'http://127.0.0.1:8012',
        headless: false,
        viewport: { width: 1440, height: 1000 },
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    webServer: {
        command: 'php -d extension=pdo_sqlite -S 127.0.0.1:8012 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
        cwd: './public',
        stdout: 'ignore',
        stderr: 'ignore',
        url: 'http://127.0.0.1:8012/login',
        reuseExistingServer: false,
        timeout: 60000,
        env: {
            APP_ENV: 'qa',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: database,
            DB_URL: '',
            APP_URL: 'http://127.0.0.1:8012',
            SESSION_DRIVER: 'file',
            CACHE_STORE: 'array',
            MAIL_MAILER: 'array',
            QUEUE_CONNECTION: 'sync',
        },
    },
});

import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
const database = path.resolve('storage/app/loyalty-' + Date.now() + '-qa.sqlite');
fs.writeFileSync(database, '');
const env = {
    ...process.env,
    APP_ENV: 'qa',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    DB_URL: '',
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'array',
    MAIL_MAILER: 'array',
    QUEUE_CONNECTION: 'sync',
};
for (const args of [
    ['artisan', 'migrate', '--force'],
    ['artisan', 'db:seed', '--class=LoyaltyV2QaSeeder', '--force'],
]) {
    const result = spawnSync('php', ['-d', 'extension=pdo_sqlite', ...args], { env, stdio: 'inherit' });
    if (result.status !== 0) process.exit(result.status ?? 1);
}
fs.mkdirSync('test-results', { recursive: true });
fs.writeFileSync('test-results/loyalty-qa-env.json', JSON.stringify({ database }, null, 2));
console.log('QA aislada preparada:', database);

import { spawnSync } from 'node:child_process';
for (const args of [
    ['scripts/prepare-loyalty-qa.mjs'],
    ['node_modules/@playwright/test/cli.js', 'test', '--config', 'playwright.loyalty.config.ts', ...process.argv.slice(2)],
]) {
    const result = spawnSync(process.execPath, args, { stdio: 'inherit' });
    if (result.status !== 0) process.exit(result.status ?? 1);
}

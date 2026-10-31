import fs from 'node:fs';
import { spawn, spawnSync } from 'node:child_process';
import assert from 'node:assert/strict';
function php(args, env = process.env) {
    const r = spawnSync('php', args, { env, encoding: 'utf8' });
    if (r.status !== 0) throw new Error(r.stderr || r.stdout);
    return r.stdout;
}
const { database } = JSON.parse(php(['scripts/probe-loyalty-mysql.php', 'create-database']));
const env = {
    ...process.env,
    APP_ENV: 'qa',
    DB_CONNECTION: 'mysql',
    DB_DATABASE: database,
    DB_URL: '',
    CACHE_STORE: 'array',
    MAIL_MAILER: 'array',
    QUEUE_CONNECTION: 'sync',
};
php(['artisan', 'migrate', '--force'], env);
const data = JSON.parse(php(['scripts/probe-loyalty-mysql.php', 'fixtures'], env));
function worker(mode) {
    return new Promise((resolve, reject) => {
        const p = spawn('php', ['scripts/probe-loyalty-mysql.php', mode, JSON.stringify(data)], { env });
        let out = '',
            err = '';
        p.stdout.on('data', (v) => (out += v));
        p.stderr.on('data', (v) => (err += v));
        p.on('exit', (code) => {
            if (code !== 0) return reject(new Error(err || out));
            try {
                resolve(JSON.parse(out));
            } catch {
                reject(new Error(mode + ': ' + out));
            }
        });
    });
}
const reservations = await Promise.all([worker('reserve'), worker('reserve')]);
assert.equal(reservations.filter((r) => r.reserved).length, 1);
const gifts = await Promise.all([worker('gift'), worker('gift'), worker('gift')]);
assert.equal(new Set(gifts.map((g) => g.batch)).size, 1);
const report = JSON.parse(php(['scripts/probe-loyalty-mysql.php', 'report', JSON.stringify(data)], env));
assert.deepEqual(report, { stock: 0, reservations: 1, gift_batches: 1, gift_entries: 1, balance: 175 });
fs.mkdirSync('docs/audits/loyalty-v2', { recursive: true });
fs.writeFileSync(
    'docs/audits/loyalty-v2/mysql-concurrency.json',
    JSON.stringify(
        {
            database,
            reservations,
            gifts,
            report,
            note: 'Dedicated local QA schema retained; no production data touched. Two simultaneous stock requests and three simultaneous same-token gift sends; not a load benchmark.',
        },
        null,
        2,
    ),
);
console.log('PASS: concurrencia real MySQL', JSON.stringify(report));

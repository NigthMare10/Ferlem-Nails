// Browser integration tests use response fixtures; no customer balances are changed.
import fs from 'node:fs';
import assert from 'node:assert/strict';
import { chromium } from '@playwright/test';
const browser = await chromium.launch({ args: ['--disable-features=LocalNetworkAccessChecks'] });
const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
const page = await context.newPage();
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
const base = 'http://127.0.0.1:8010';
await page.goto(base + '/clientes/login');
await page.getByLabel('Correo electrónico').fill('cliente.prueba@lemus.test');
await page.locator('input[name=password]').fill('PruebaCliente!2026');
await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();
await page.waitForURL('**/clientes');
const template = await (await context.request.get(base + '/clientes/recompensas')).text();
const match = template.match(/(<script data-page="app" type="application\/json">)([\s\S]*?)(<\/script>)/);
assert(match, 'Inertia page template is available');
const initial = JSON.parse(match[2]);
let state;
let disconnected = false;
let forcedExpired = false;
let getRequests = 0;
let postTokens = [];
const reset = () => {
    state = structuredClone(initial);
    state.props.balance = 200;
    state.props.qualifying = 200;
    state.props.rewards = [
        { id: 9001, name: 'Diseño de prueba', benefit_type: 'gift', points_cost: 60, stock: 2, per_customer_limit: 3, used_by_customer: 0, is_active: true },
    ];
    state.props.redemptions = [];
    state.props.redemption_history = { current_page: 1, last_page: 1, total: 0 };
    state.props.flash = {};
    state.props.errors = {};
    forcedExpired = false;
};
reset();
await page.route(
    (url) => url.pathname.startsWith('/clientes'),
    async (route) => {
        const request = route.request();
        const url = new URL(request.url());
        if (request.method() === 'POST' && url.pathname.includes('/canjes')) {
            postTokens.push(request.postDataJSON().operation_token);
            if (disconnected) return route.abort('failed');
            state.props.balance = 140;
            state.props.rewards[0].stock = 1;
            state.props.rewards[0].used_by_customer = 1;
            state.props.redemptions = [
                {
                    id: 1234,
                    status: 'reserved',
                    points_cost: 60,
                    loyalty_reward_id: 9001,
                    reward_snapshot: { name: 'Diseño de prueba', benefit_type: 'gift' },
                    expires_at: new Date(Date.now() + 24 * 3600000).toISOString(),
                },
            ];
            state.props.flash = { success: 'Recompensa reservada por 24 horas. Preséntala al personal.' };
            return route.fulfill({ status: 200, contentType: 'application/json', headers: { 'X-Inertia': 'true' }, body: JSON.stringify(state) });
        }
        if (request.method() !== 'GET') return route.fallback();
        getRequests++;
        state.url = url.pathname + url.search;
        state.props.section = url.pathname.includes('recompensas') ? 'rewards' : url.pathname.includes('perfil') ? 'profile' : 'home';
        if (forcedExpired && state.props.redemptions[0]?.status === 'reserved') {
            state.props.redemptions[0].status = 'expired';
            state.props.balance = 200;
            state.props.rewards[0].stock = 2;
            state.props.rewards[0].used_by_customer = 0;
            state.props.redemption_history.total = 1;
        }
        if (request.headers()['x-inertia']) {
            const partial = request.headers()['x-inertia-partial-data'];
            const body = partial
                ? { ...state, props: Object.fromEntries(Object.entries(state.props).filter(([key]) => partial.split(',').includes(key))) }
                : state;
            return route.fulfill({ status: 200, contentType: 'application/json', headers: { 'X-Inertia': 'true' }, body: JSON.stringify(body) });
        }
        return route.fulfill({
            status: 200,
            contentType: 'text/html',
            body: template.replace(match[0], match[1] + JSON.stringify(state).replaceAll('<', '\\u003c') + match[3]),
        });
    },
);

// Lost response, retry, and rapid double tap: the retry must use the original UUID.
await page.goto(base + '/clientes/recompensas');
await page.getByRole('heading', { name: 'Saldo Canjeable' }).waitFor();
disconnected = true;
await page.getByRole('button', { name: /Reservar recompensa \(24h\)/ }).click();
await page.getByRole('alert').filter({ hasText: 'Revisa tu conexión' }).waitFor();
disconnected = false;
await page.getByRole('button', { name: /Reservar recompensa \(24h\)/ }).dblclick();
await page.getByText('Reservado - Pendiente de entrega', { exact: true }).waitFor();
assert(postTokens.length >= 2);
assert.equal(postTokens.length, 2, 'A double tap cannot create a second successful operation');
assert.equal(postTokens[0], postTokens[1], 'A disconnected retry reuses the same UUID');
assert.equal(state.props.balance, 140);
console.log('PASS: network failure, retry UUID and active reservation feedback');

// A visible page reconciles a due reservation into history; it is not lost.
await page.clock.install();
state.props.redemptions[0].expires_at = new Date(Date.now() + 1000).toISOString();
await page.reload();
await page.getByText('Reservado - Pendiente de entrega', { exact: true }).waitFor();
forcedExpired = true;
await page.clock.fastForward(2000);
await page.getByRole('heading', { name: 'Mis canjes anteriores' }).waitFor();
assert.equal(await page.getByText('Reservado - Pendiente de entrega', { exact: true }).count(), 0);
assert.equal(state.props.balance, 200);
assert.equal(state.props.rewards[0].stock, 2);
console.log('PASS: automatic expiry reconciliation, refund display and history');

// Staff delivery appears on the quiet refresh without refunding the customer.
forcedExpired = false;
state.props.balance = 140;
state.props.redemptions[0].status = 'reserved';
state.props.redemptions[0].expires_at = new Date(Date.now() + 3600000).toISOString();
await page.reload();
await page.getByText('Reservado - Pendiente de entrega', { exact: true }).waitFor();
state.props.redemptions[0].status = 'confirmed';
await page.clock.fastForward(60001);
await page.getByRole('heading', { name: 'Mis canjes anteriores' }).waitFor();
assert.equal(state.props.balance, 140);
console.log('PASS: staff confirmation is reflected without a second debit/refund');

// Hidden tab: no polling and no decorative animations left alive.
state.props.redemptions[0].status = 'reserved';
await page.reload();
await page.getByText('Reservado - Pendiente de entrega', { exact: true }).waitFor();
await page.evaluate(() => {
    Object.defineProperty(document, 'hidden', { configurable: true, value: true });
    document.dispatchEvent(new Event('visibilitychange'));
});
const beforeHidden = getRequests;
await page.clock.fastForward(180000);
assert.equal(getRequests, beforeHidden, 'Hidden tabs make no refresh requests');
assert.equal(await page.evaluate(() => document.getAnimations().filter((a) => a.playState === 'running').length), 0);
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/clientes') && response.request().headers()['x-inertia'] === 'true'),
    page.evaluate(() => {
        Object.defineProperty(document, 'hidden', { configurable: true, value: false });
        document.dispatchEvent(new Event('visibilitychange'));
    }),
]);
console.log('PASS: hidden-tab suspension and return synchronization');

// Motion and performance checks: same local app, touch viewport, 4x CPU throttle.
// Use a NEW context: Playwright's mocked clock is shared by pages in a context.
await page.close();
const perfContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
const perfPage = await perfContext.newPage();
perfPage.on('pageerror', (error) => errors.push(error.message));
reset();
state.url = '/clientes';
state.props.section = 'home';
await perfPage.route(
    (url) => url.pathname === '/clientes',
    (route) =>
        route.fulfill({
            status: 200,
            contentType: 'text/html',
            body: template.replace(match[0], match[1] + JSON.stringify(state).replaceAll('<', '\\u003c') + match[3]),
        }),
);
const cdp = await perfContext.newCDPSession(perfPage);
await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
await perfPage.goto(base + '/clientes');
await perfPage.locator('[data-motion-reveal]').first().waitFor();
await perfPage.waitForFunction(() => document.getAnimations().some((a) => a.playState === 'running'));
const sample = await perfPage.evaluate(async () => {
    const frames = [];
    const tasks = [];
    const observer = new PerformanceObserver((list) => tasks.push(...list.getEntries().map((entry) => entry.duration)));
    observer.observe({ type: 'longtask' });
    const start = performance.now();
    let previous = start;
    await new Promise((resolve) => {
        const tick = (now) => {
            frames.push(now - previous);
            previous = now;
            if (now - start < 7200) requestAnimationFrame(tick);
            else resolve();
        };
        requestAnimationFrame(tick);
    });
    observer.disconnect();
    const sorted = frames.slice(1).sort((a, b) => a - b);
    return {
        cpuThrottle: 4,
        sampleWindowMs: 7200,
        viewport: '390x844',
        frames: sorted.length,
        medianFrameMs: sorted[Math.floor(sorted.length / 2)],
        p95FrameMs: sorted[Math.floor(sorted.length * 0.95)],
        maxFrameMs: sorted.at(-1),
        longTasks: tasks,
        runningAfterSequence: document.getAnimations().filter((a) => a.playState === 'running' && a.effect?.getTiming().iterations !== Infinity).length,
        continuousAnimations: document.getAnimations().filter((a) => a.playState === 'running' && a.effect?.getTiming().iterations === Infinity).length,
    };
});
assert.equal(sample.runningAfterSequence, 0, 'Entrance motion must finish');
assert(sample.continuousAnimations > 0 && sample.continuousAnimations <= 5, 'Continuous ambient motion stays bounded');
await perfPage.getByRole('button', { name: 'Pausar animaciones' }).click();
assert.equal(await perfPage.evaluate(() => document.getAnimations().filter((a) => a.playState === 'running').length), 0);
await perfPage.reload();
await perfPage.getByRole('button', { name: 'Activar animaciones' }).waitFor();
assert.equal(await perfPage.evaluate(() => document.getAnimations().filter((a) => a.playState === 'running').length), 0);
await perfPage.getByRole('button', { name: 'Activar animaciones' }).click();
console.log('PASS: continuous ambient motion, pause control and persisted preference');
await perfPage.emulateMedia({ reducedMotion: 'reduce' });
await perfPage.reload();
await perfPage.locator('[data-testid=loyalty-balance]').waitFor();
assert.equal(await perfPage.evaluate(() => document.getAnimations().filter((a) => a.playState === 'running').length), 0);
assert.equal((await perfPage.locator('[data-testid=loyalty-balance]').innerText()).trim(), '200');
console.log('PASS: reduced motion shows final points immediately');
assert.deepEqual(errors, []);
fs.mkdirSync('docs/audits/customer-motion', { recursive: true });
fs.writeFileSync(
    'docs/audits/customer-motion/browser-performance.json',
    JSON.stringify(
        {
            observedAt: new Date().toISOString(),
            sample,
            javascriptErrors: errors,
            note: 'Chromium desktop CPU throttling is not a physical low-end phone or a load/concurrency test.',
        },
        null,
        2,
    ),
);
console.log('PERFORMANCE', JSON.stringify(sample));
await browser.close();

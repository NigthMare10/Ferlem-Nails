import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
// Chromium classifies fulfilled visual fixtures as public-address responses.
// Disable only its loopback classification check for this local test browser.
const browser = await chromium.launch({ args: ['--disable-features=LocalNetworkAccessChecks'] });
const directory = 'docs/screenshots/loyalty-stitch';
fs.mkdirSync(directory, { recursive: true });
const errors = [];
const resourceErrors = [];
const context = await browser.newContext({ viewport: { width: 390, height: 884 }, deviceScaleFactor: 1 });
const page = await context.newPage();
const settleMotion = async () => {
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    await page.evaluate(() =>
        Promise.all(
            document
                .getAnimations()
                .filter((animation) => animation.effect?.getTiming().iterations !== Infinity)
                .map((animation) => animation.finished.catch(() => {})),
        ),
    );
};
page.on('pageerror', (e) => {
    errors.push(e.message);
    console.error('PAGE_ERROR', e.message);
});
page.on('response', (response) => {
    if (response.status() >= 400 && !response.url().endsWith('/clientes/qr')) resourceErrors.push(`${response.status()} ${response.url()}`);
});
page.on('console', (message) => {
    if (message.type() === 'error') console.error('CONSOLE', message.text());
});
for (const [name, route] of [
    ['login', '/clientes/login'],
    ['register', '/clientes/registro'],
    ['recovery', '/clientes/olvide-contrasena'],
]) {
    await page.goto('http://127.0.0.1:8010' + route);
    await page.evaluate(() => document.fonts.ready);
    await settleMotion();
    await page.screenshot({ path: `${directory}/${name}-actual.png`, fullPage: true });
    console.log(name, await page.locator('h1').textContent(), 'overflow:', await page.evaluate(() => document.documentElement.scrollWidth > innerWidth));
}
await page.goto('http://127.0.0.1:8010/clientes/login');
await page.getByLabel('Correo electrónico').fill('cliente.prueba@lemus.test');
await page.locator('input[name="password"]').fill('PruebaCliente!2026');
await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();
await page.waitForURL('**/clientes');
for (const [name, route] of [
    ['home', '/clientes'],
    ['rewards', '/clientes/recompensas'],
    ['profile', '/clientes/perfil'],
]) {
    await page.goto('http://127.0.0.1:8010' + route);
    await page.evaluate(() => document.fonts.ready);
    await settleMotion();
    await page.screenshot({ path: `${directory}/${name}-actual.png`, fullPage: true });
    console.log(name, 'overflow:', await page.evaluate(() => document.documentElement.scrollWidth > innerWidth));
}
await page.getByRole('button', { name: 'Mostrar mi QR temporal' }).click();
await page.locator('dialog[open]').waitFor();
console.log(
    'QR image:',
    await page
        .locator('dialog img')
        .getAttribute('src')
        .then((s) => s.startsWith('data:image/png')),
);
await settleMotion();
await page.screenshot({ path: `${directory}/qr-actual.png` });
await page.getByRole('button', { name: 'Cerrar QR' }).click();
await page.getByRole('button', { name: 'Editar nombre completo' }).click();
await page.getByRole('button', { name: 'Guardar cambios' }).waitFor();
console.log('Profile editor opens');

// Visual fixtures are injected into responses only: never modify the user's database.
let currentFixture;
await page.route(
    (url) => url.pathname.startsWith('/clientes'),
    async (route) => {
        if (route.request().method() !== 'GET') return route.continue();
        const response = await route.fetch();
        const body = await response.text();
        const match = body.match(/(<script data-page="app" type="application\/json">)([\s\S]*?)(<\/script>)/);
        if (!match) return route.fulfill({ response, body });
        const data = JSON.parse(match[2]);
        if (data.component !== 'Customer/Portal') return route.fulfill({ response, body });
        Object.assign(data.props, {
            customer: { ...data.props.customer, name: 'Sofía Lemus', email: 'sofia.lemus@couture.com', phone: '+52 55 4912 8304', birthday: '1994-10-14' },
            balance: 2450,
            visits: 12,
            qualifying: 3800,
            levels: [
                { name: data.props.section === 'profile' ? 'Clienta Preferente' : 'Nivel Amatista', points: 0, benefits: 'Beneficios de prueba visual' },
                { name: 'Nivel Diamante', points: 5000, benefits: 'Beneficios de prueba visual' },
            ],
            rewards: [
                {
                    id: 901,
                    name: 'Spa de Cutículas & Hidratación Esencial',
                    description: 'Tratamiento nutritivo profundo con infusión botánica y sellado térmico.',
                    points_cost: data.props.section === 'rewards' ? 2100 : 2800,
                    benefit_type: 'service',
                    stock: null,
                },
                { id: 902, name: 'Esmaltado Semipermanente Acabado Aurora', points_cost: 1800, benefit_type: 'service', stock: null },
                { id: 903, name: 'Set de Cuidado Botánico & Sérum Fortalecedor', points_cost: 2800, benefit_type: 'gift', stock: 5 },
                { id: 904, name: 'Manicura Esculpida & Nail Art Couture', points_cost: 3500, benefit_type: 'service', stock: null },
            ],
            redemptions: [
                {
                    id: 999,
                    status: 'reserved',
                    expires_at: '2030-01-01T20:00:00-06:00',
                    points_cost: 1200,
                    reward_snapshot: { name: 'Aceite Nutritivo Elixir Lemus', points_cost: 1200 },
                },
            ],
        });
        currentFixture = data;
        await route.fulfill({ response, body: body.replace(match[0], match[1] + JSON.stringify(data).replaceAll('<', '\\u003c') + match[3]) });
    },
);
for (const [name, route] of [
    ['home', '/clientes'],
    ['rewards', '/clientes/recompensas'],
    ['profile', '/clientes/perfil'],
]) {
    await page.goto('http://127.0.0.1:8010' + route);
    await page
        .locator('.customer-world h1')
        .waitFor()
        .catch(async (error) => {
            console.log('Fixture failed:', await page.content());
            throw error;
        });
    await page.evaluate(() => document.fonts.ready);
    await settleMotion();
    await page.screenshot({ path: `${directory}/${name}-fixture.png`, fullPage: true });
    if (name === 'rewards') {
        const disabled = await page.getByRole('button', { name: /No disponible/ }).count();
        if (disabled !== 2) throw new Error(`Expected two points-gated rewards, got ${disabled}`);
        await page.getByRole('button', { name: 'Regalos', exact: true }).click();
        await page.waitForFunction(() => document.querySelectorAll('article').length === 1);
        if ((await page.locator('article').count()) !== 1) throw new Error('Reward type filter failed');
        console.log('Reward eligibility and filters verified against visual fixtures');
    }
}

let submittedProfile;
await page.route(
    (url) => url.pathname === '/clientes/perfil',
    async (route) => {
        if (route.request().method() !== 'PATCH') return route.fallback();
        submittedProfile = route.request().postDataJSON();
        await route.fulfill({ status: 200, contentType: 'application/json', headers: { 'X-Inertia': 'true' }, body: JSON.stringify(currentFixture) });
    },
);
await page.getByRole('button', { name: 'Editar nombre completo' }).click();
await page.locator('input#name').fill('Edición abandonada');
await page.getByRole('button', { name: 'Editar teléfono de contacto' }).click();
await page.locator('input#phone').fill('9999-1111');
await Promise.all([
    page.waitForResponse((response) => response.request().method() === 'PATCH' && response.url().endsWith('/clientes/perfil')),
    page.getByRole('button', { name: 'Guardar cambios' }).click(),
]);
if (submittedProfile.name !== 'Sofía Lemus' || submittedProfile.birthday !== '1994-10-14' || submittedProfile.phone !== '9999-1111')
    throw new Error('A hidden profile edit was submitted');
console.log('Profile edits submit only the selected field');

let qrRequests = 0;
await page.route(
    (url) => url.pathname === '/clientes/qr',
    async (route) => {
        qrRequests++;
        await route.fulfill(
            qrRequests === 1
                ? { status: 200, contentType: 'application/json', body: JSON.stringify({ token: 'a'.repeat(64), expires_in: 1 }) }
                : { status: 503, body: 'Unavailable' },
        );
    },
);
await page.getByRole('button', { name: /Mostrar mi QR temporal/ }).click();
await page.getByText('Tu QR ha vencido. Genera uno nuevo para identificarte.').waitFor();
await page.getByRole('button', { name: 'Generar nuevo QR' }).click();
await page.getByRole('alert').filter({ hasText: 'No pudimos generar tu QR' }).waitFor();
if (await page.locator('dialog[open]').count()) throw new Error('QR regeneration error is hidden behind the modal');
console.log('Expired QR and regeneration error states verified');

const reference = await browser.newPage({ viewport: { width: 390, height: 884 } });
for (const name of ['login', 'register', 'recovery', 'home', 'rewards', 'profile']) {
    await reference.goto('file://' + path.resolve(`docs/design/stitch/${name}.html`).replaceAll('\\', '/'));
    await reference.evaluate(() => document.fonts.ready);
    // Wait for the CDN's generated utilities, not an arbitrary animation delay.
    await reference.waitForFunction(() =>
        [...document.styleSheets].some((sheet) => {
            try {
                return [...sheet.cssRules].some((rule) => rule.cssText.includes('--tw-'));
            } catch {
                return false;
            }
        }),
    );
    await reference.screenshot({ path: `${directory}/${name}-reference.png`, fullPage: true });
}
console.log('BROWSER_ERRORS', JSON.stringify(errors));
const admin = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
admin.on('pageerror', (e) => errors.push(e.message));
await admin.goto('http://127.0.0.1:8010/login');
await admin.locator('input[type=email]').fill('superadmin.prueba@lemus.test');
await admin.locator('input[type=password]').fill('PruebaAdmin!2026');
await admin.locator('button[type=submit]').click();
await admin.waitForURL((url) => url.pathname !== '/login');
await admin.goto('http://127.0.0.1:8010/configuration/loyalty/rewards');
await admin.getByRole('heading', { name: 'Fidelidad y recompensas' }).waitFor();
await admin.evaluate(() => document.fonts.ready);
await admin.screenshot({ path: `${directory}/admin-actual.png`, fullPage: true });
console.log('Existing application sidebar preserved:', await admin.locator('.app-sidebar').count());
await admin.getByRole('button', { name: /Nueva recompensa/ }).click();
await admin.locator('#reward-form').waitFor();
console.log('Reward creation form opens');
await admin.getByRole('button', { name: 'Cancelar', exact: true }).click();
await admin.getByRole('button', { name: 'Historial de canjes', exact: true }).click();
await admin.getByRole('heading', { name: 'Todos los canjes' }).waitFor();
await admin.getByRole('button', { name: 'Niveles', exact: true }).click();
await admin.getByRole('heading', { name: 'Niveles de fidelidad' }).waitFor();
console.log('Redemptions and level tabs work');
for (const width of [320, 768, 1440]) {
    await page.setViewportSize({ width, height: 884 });
    await page.goto('http://127.0.0.1:8010/clientes/perfil');
    await page.locator('.customer-world h1').waitFor();
    console.log('Customer responsive', width, 'overflow:', await page.evaluate(() => document.documentElement.scrollWidth > innerWidth));
    await admin.setViewportSize({ width, height: 884 });
    await admin.goto('http://127.0.0.1:8010/configuration/loyalty/rewards');
    await admin.getByRole('heading', { name: 'Fidelidad y recompensas' }).waitFor();
    console.log('Admin responsive', width, 'overflow:', await admin.evaluate(() => document.documentElement.scrollWidth > innerWidth));
}
console.log('FINAL_BROWSER_ERRORS', JSON.stringify(errors));
if (errors.length || resourceErrors.length) throw new Error(JSON.stringify({ errors, resourceErrors }));
await browser.close();

import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from '@playwright/test';
import { QRCodeReader, RGBLuminanceSource, BinaryBitmap, HybridBinarizer } from '@zxing/library';

const browser = await chromium.launch({ args: ['--disable-features=LocalNetworkAccessChecks'] });
try {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const base = 'http://127.0.0.1:8010';
    const directory = 'docs/audits/customer-motion/continuous';
    fs.mkdirSync(directory, { recursive: true });
    await page.goto(base + '/clientes/login');
    await page.getByLabel('Correo electrónico').fill('cliente.prueba@lemus.test');
    await page.locator('input[name=password]').fill('PruebaCliente!2026');
    await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();
    await page.waitForURL('**/clientes');
    // Stress layout using response-only fixtures, never editing customer data.
    await page.route(
        (url) => url.pathname.startsWith('/clientes'),
        async (route) => {
            if (route.request().method() !== 'GET' || route.request().headers()['x-inertia']) return route.fallback();
            const response = await route.fetch();
            const body = await response.text();
            const match = body.match(/(<script data-page="app" type="application\/json">)([\s\S]*?)(<\/script>)/);
            if (!match) return route.fulfill({ response, body });
            const data = JSON.parse(match[2]);
            if (data.component !== 'Customer/Portal') return route.fulfill({ response, body });
            data.props.customer.name = 'María Fernanda de los Ángeles';
            data.props.balance = 2450;
            data.props.qualifying = 3800;
            data.props.levels = [
                { name: 'Nivel Amatista', points: 0 },
                { name: 'Nivel Diamante', points: 5000 },
            ];
            data.props.rewards = [
                { id: 901, name: 'Spa de Cutículas & Hidratación Esencial con tratamiento botánico', points_cost: 2100, benefit_type: 'service', stock: null },
                { id: 902, name: 'Set de Cuidado Botánico & Sérum Fortalecedor', points_cost: 2800, benefit_type: 'gift', stock: 5 },
            ];
            data.props.redemptions = [
                {
                    id: 999,
                    status: 'reserved',
                    expires_at: '2030-01-01T20:00:00-06:00',
                    points_cost: 1200,
                    reward_snapshot: { name: 'Aceite Nutritivo Elixir Lemus de edición especial' },
                },
            ];
            return route.fulfill({ response, body: body.replace(match[0], match[1] + JSON.stringify(data).replaceAll('<', '\\u003c') + match[3]) });
        },
    );
    const results = [];
    for (const width of [320, 390, 768]) {
        await page.setViewportSize({ width, height: 844 });
        for (const route of ['/clientes', '/clientes/recompensas', '/clientes/perfil']) {
            await page.goto(base + route);
            await page.locator('.customer-world h1').waitFor();
            await page.evaluate(() => document.fonts.ready);
            await page.evaluate(() =>
                Promise.all(
                    document
                        .getAnimations()
                        .filter((a) => a.effect?.getTiming().iterations !== Infinity)
                        .map((a) => a.finished.catch(() => {})),
                ),
            );
            const layout = await page.evaluate(() => {
                const targets = [
                    ...document.querySelectorAll(
                        '.customer-header a, .customer-header button, .reward-filters button, .customer-content h1, .customer-content h3',
                    ),
                ];
                return {
                    overflow: document.documentElement.scrollWidth > innerWidth,
                    clipped: targets
                        .filter((el) => {
                            const box = el.getBoundingClientRect();
                            return box.left < -1 || box.right > innerWidth + 1 || el.scrollWidth > el.clientWidth + 2;
                        })
                        .map((el) => el.textContent.trim()),
                };
            });
            assert.equal(layout.overflow, false, `${width} ${route}: overflow`);
            assert.deepEqual(layout.clipped, [], `${width} ${route}: clipped controls/text`);
            results.push({ width, route, ...layout });
            await page.screenshot({ path: `${directory}/${width}-${route.split('/').at(-1)}.png`, fullPage: true });
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await page.waitForFunction(() =>
                [...document.querySelectorAll('[data-motion-sheen]')].every((el) => {
                    const rect = el.parentElement.getBoundingClientRect();
                    return rect.bottom >= 0 || getComputedStyle(el).animationPlayState === 'paused';
                }),
            );
        }
    }
    await page.evaluate(() => window.scrollTo(0, 0));
    // The real QR remains decodable; decoration never crosses its quiet zone.
    await page.setViewportSize({ width: 320, height: 568 });
    await page.getByRole('button', { name: 'Mostrar mi QR temporal' }).click();
    await page.locator('dialog[open] img').waitFor();
    const image = await page.locator('dialog img').evaluate(async (img) => {
        await img.decode();
        const canvas = document.createElement('canvas');
        canvas.width = img.naturalWidth;
        canvas.height = img.naturalHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);
        const rgba = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        return {
            width: canvas.width,
            height: canvas.height,
            pixels: Array.from({ length: canvas.width * canvas.height }, (_, i) => (rgba[i * 4] + rgba[i * 4 + 1] * 2 + rgba[i * 4 + 2]) / 4),
        };
    });
    const decoded = new QRCodeReader()
        .decode(new BinaryBitmap(new HybridBinarizer(new RGBLuminanceSource(Uint8ClampedArray.from(image.pixels), image.width, image.height))))
        .getText();
    await page.getByText('Código alternativo', { exact: true }).click();
    assert.equal(decoded, await page.locator('dialog code').textContent());
    const dialog = await page.locator('dialog').evaluate((el) => {
        const rect = el.getBoundingClientRect();
        return { left: rect.left, right: rect.right, top: rect.top, bottom: rect.bottom, scrollable: el.scrollHeight > el.clientHeight };
    });
    assert(dialog.left >= 0 && dialog.right <= 320 && dialog.top >= 0 && dialog.bottom <= 568, 'QR dialog fits a short phone');
    await page.getByRole('button', { name: 'Cerrar QR' }).focus();
    await page.screenshot({ path: `${directory}/qr-320.png` });
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('dialog[open]').count(), 0);
    assert.deepEqual(errors, []);
    fs.writeFileSync(`${directory}/results.json`, JSON.stringify({ results, qrDecoded: true, dialog, errors }, null, 2));
    console.log('PASS: responsive clipping, real QR decoding, short-screen modal and Escape dismissal');
} finally {
    await browser.close();
}

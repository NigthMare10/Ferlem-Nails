import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';

const evidence = 'docs/audits/reward-scheduling';
async function admin(page: Page) {
    await page.goto('/login');
    await page.locator('input[type=email]').fill('admin@lemus.test');
    await page.locator('input[type=password]').fill('LocalTesting123!');
    await page.locator('button[type=submit]').click();
    await page.waitForURL((url) => url.pathname !== '/login');
}

test('registro inmediato y encabezado con nombre largo a 320 y 390 px', async ({ page }) => {
    fs.mkdirSync(evidence, { recursive: true });
    await page.setViewportSize({ width: 320, height: 844 });
    await page.goto('/clientes/registro');
    await page.getByLabel('Nombre completo').fill('María Fernanda de los Ángeles Rodríguez Hernández');
    await page.getByLabel('Correo electrónico').fill(`mobile-${Date.now()}@example.test`);
    await page.locator('input[name=password]').fill('LocalTesting123!');
    await page.locator('input[name=password_confirmation]').fill('LocalTesting123!');
    await page.getByRole('button', { name: 'Crear cuenta', exact: true }).click();
    await page.waitForURL('**/clientes');
    await expect(page.getByText('Verifica tu correo', { exact: false })).toHaveCount(0);
    await expect(page.locator('.header-member strong')).toContainText('Rodríguez');
    for (const width of [320, 390]) {
        await page.setViewportSize({ width, height: 844 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
        const member = await page.locator('.header-member').boundingBox();
        const controls = await page.locator('.header-trailing').boundingBox();
        expect(member!.x + member!.width).toBeLessThanOrEqual(controls!.x + 1);
        await page.screenshot({ path: `${evidence}/registered-${width}.png`, fullPage: true });
    }
    const qr = await page.request.post('/clientes/qr', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent((await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN')!.value),
        },
    });
    expect(qr.ok()).toBeTruthy();
});

test('comidas semanales en modal completa y adaptable', async ({ page }) => {
    await admin(page);
    await page.goto('/configuration/users');
    await page.locator('tbody tr').filter({ hasText: 'empleada@lemus.test' }).getByRole('button', { name: 'Acciones del usuario' }).click();
    await page.getByText('Permisos, orden y comida', { exact: true }).click();
    await page.getByRole('button', { name: 'Configurar comidas' }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog.getByRole('checkbox')).toHaveCount(7);
    for (const day of ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'])
        await expect(dialog.getByRole('checkbox', { name: day, exact: true })).toBeChecked();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(dialog).toHaveClass(/v-dialog--fullscreen/);
    await page.screenshot({ path: `${evidence}/weekly-meals-mobile.png` });
    await dialog.getByRole('button', { name: 'Guardar comidas' }).click();
    await expect(dialog).not.toBeVisible();
    await page.getByRole('button', { name: 'Configurar comidas' }).click();
    await expect(page.getByRole('dialog').getByRole('checkbox', { name: 'Domingo', exact: true })).toBeChecked();
});

test('foto real de premio, canje e Inicio permiten agendar sin cobro', async ({ page, browser, baseURL }) => {
    await admin(page);
    await page.goto('/configuration/loyalty/rewards');
    await page.getByRole('button', { name: 'Añadir premio', exact: true }).click();
    const prize = page.getByRole('dialog', { name: 'Añadir premio', exact: true });
    const option = prize.getByLabel('Servicio existente').locator('option').filter({ hasText: 'Baño de acrílico' });
    await prize.getByLabel('Servicio existente').selectOption((await option.getAttribute('value'))!);
    await prize.getByLabel('Puntos necesarios para canjear').fill('10');
    await prize.locator('input[type=file]').setInputFiles('public/images/stitch/home-1.webp');
    await expect(prize.getByAltText('Vista previa del premio')).toBeVisible();
    await prize.getByRole('button', { name: 'Guardar premio', exact: true }).click();
    await expect(prize).not.toBeVisible();
    const context = await browser.newContext({ baseURL, viewport: { width: 390, height: 844 } });
    const client = await context.newPage();
    await client.goto('/clientes/login');
    await client.getByLabel('Correo electrónico').fill('clienta@lemus.test');
    await client.locator('input[name=password]').fill('LocalTesting123!');
    await client.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();
    await client.waitForURL('**/clientes');
    await client.goto('/clientes/recompensas');
    const reward = client.locator('.reward-collection article').filter({ hasText: 'Baño de acrílico' });
    await expect(reward.locator('img')).toHaveAttribute('src', /\/rewards\/\d+\/image/);
    await reward.getByRole('button', { name: 'Reservar recompensa (24h)', exact: true }).click();
    await page.goto('/');
    const inbox = page.locator('.reward-inbox-row').filter({ hasText: 'Baño de acrílico' });
    await expect(inbox).toContainText('Sofía Ramírez');
    await inbox.getByRole('button', { name: 'Agendar premio gratis' }).click();
    const booking = page.getByRole('dialog');
    await expect(booking.getByLabel('Nombre de la clienta')).toHaveValue('Sofía Ramírez');
    await expect(booking.getByText('Premio gratuito', { exact: false }).first()).toBeVisible();
    await booking.getByLabel('Fecha', { exact: true }).fill('2030-01-14');
    await expect(booking.getByLabel('Hora', { exact: true })).toBeEnabled();
    await booking.getByLabel('Hora', { exact: true }).locator('..').click();
    await page.getByRole('listbox').getByRole('option').first().click();
    await booking.getByRole('button', { name: 'Guardar cita', exact: true }).click();
    await page.waitForURL(/appointments/);
    const appointment = page.locator('.appointment-card').filter({ hasText: 'Baño de acrílico' });
    await expect(appointment).toHaveCount(1);
    await expect(page.getByText('Premio gratis · Sin cobro', { exact: true }).first()).toBeVisible();
    await expect(appointment.getByText('Atender y cobrar', { exact: true })).toHaveCount(0);
    await page.screenshot({ path: `${evidence}/free-appointment.png`, fullPage: true });
    await client.reload();
    await expect(client.getByText('Cita agendada · Premio gratuito')).toBeVisible();
    await context.close();
});

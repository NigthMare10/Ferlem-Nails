import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';

const root = '/configuration/loyalty/rewards';
async function adminLogin(page: Page) {
    await page.goto('/login');
    await page.locator('input[type=email]').fill('admin@lemus.test');
    await page.locator('input[type=password]').fill('LocalTesting123!');
    await page.locator('button[type=submit]').click();
    await page.waitForURL((u) => u.pathname !== '/login', { timeout: 15000 });
    await page.goto(root);
    await expect(page.getByRole('heading', { name: 'Fidelidad y recompensas', exact: true })).toBeVisible();
}
async function clientLogin(page: Page, email = 'clienta@lemus.test') {
    await page.goto('/clientes/login');
    await page.getByLabel('Correo electrónico').fill(email);
    await page.locator('input[name=password]').fill('LocalTesting123!');
    await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();
    await page.waitForURL('**/clientes');
}
async function tab(page: Page, name: string) {
    await page.locator('.loyalty-v2 .tabs').getByRole('link', { name, exact: true }).click();
}
async function addPrize(page: Page, service: string) {
    await page.getByRole('button', { name: 'Añadir premio', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Añadir premio', exact: true });
    const option = dialog.getByLabel('Servicio existente').locator('option').filter({ hasText: service });
    await dialog.getByLabel('Servicio existente').selectOption((await option.getAttribute('value'))!);
    await dialog.getByLabel('Puntos necesarios para canjear').fill('50');
    await dialog.getByLabel('Cupos ilimitados').uncheck();
    await dialog.getByLabel('Cupos disponibles ahora').fill('2');
    await dialog.getByLabel('Máximo por clienta').fill('2');
    await dialog.getByRole('button', { name: 'Guardar premio', exact: true }).click();
    await expect(dialog).not.toBeVisible();
}
async function selectRecipients(page: Page) {
    await page.getByRole('button', { name: 'Crear regalo', exact: true }).click();
    const d = page.getByRole('dialog', { name: 'Crear regalo', exact: true });
    await expect(d.getByLabel('Seleccionar Sofía Ramírez')).toBeVisible();
    await d.getByRole('button', { name: 'Seleccionar esta página', exact: true }).click();
    await expect(d.getByLabel('Seleccionar Sinverificar de prueba')).toBeChecked();
    await d.getByLabel('Seleccionar Sinverificar de prueba').uncheck();
    await expect(d.getByText('2 clientas seleccionadas', { exact: true })).toBeVisible();
    await d.getByRole('button', { name: 'Continuar', exact: true }).click();
    return d;
}
async function reviewSend(d: any) {
    await d.getByRole('button', { name: 'Revisar regalo', exact: true }).click();
    await d.getByLabel('Confirmo las clientas y el regalo.').check();
    await d.getByRole('button', { name: 'Enviar regalo a 2 clientas', exact: true }).click();
}

test('recorrido real: premios, múltiples canjes, regalos, máximo nivel y administración', async ({ page, browser, baseURL }) => {
    test.setTimeout(180000);
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await adminLogin(page);
    await test.step('crear dos premios desde servicios existentes', async () => {
        await addPrize(page, 'Esmalte en gel');
        await addPrize(page, 'Pedicura');
        await expect(page.locator('.ledger tbody tr').filter({ hasText: 'Esmalte en gel' })).toContainText('20 pts al pagar');
    });
    await test.step('pausar y reactivar varios premios juntos', async () => {
        for (const n of ['Esmalte en gel', 'Pedicura']) await page.getByRole('checkbox', { name: `Seleccionar ${n}`, exact: true }).check();
        await page.getByRole('button', { name: 'Pausar seleccionados' }).click();
        let d = page.getByRole('dialog', { name: '¿Pausar estos premios?' });
        await d.getByRole('button', { name: 'Confirmar cambios' }).click();
        await expect(d).not.toBeVisible();
        for (const n of ['Esmalte en gel', 'Pedicura']) await page.getByRole('checkbox', { name: `Seleccionar ${n}`, exact: true }).check();
        await page.getByRole('button', { name: 'Reactivar seleccionados' }).click();
        d = page.getByRole('dialog', { name: 'Reactivar premios' });
        await d.getByRole('button', { name: 'Confirmar cambios' }).click();
        await expect(d).not.toBeVisible();
    });
    const context = await browser.newContext({ baseURL, viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    const client = await context.newPage();
    client.on('pageerror', (e) => errors.push(e.message));
    await test.step('clienta llega al nivel máximo sin siguiente nivel ficticio', async () => {
        await clientLogin(client);
        await expect(client.getByText('Diamante', { exact: true }).first()).toBeVisible();
        await expect(client.getByText('Meta alcanzada', { exact: true })).toBeVisible();
        await expect(client.locator('[data-testid=loyalty-balance]')).toHaveText('2,120');
    });
    await test.step('reservar dos recompensas distintas', async () => {
        await client.getByRole('link', { name: 'Recompensas', exact: true }).click();
        for (const n of ['Esmalte en gel', 'Pedicura']) {
            const row = client.locator('.reward-collection article').filter({ hasText: n });
            await row.getByRole('button', { name: 'Reservar recompensa (24h)', exact: true }).click();
        }
        await expect(client.getByText('Reservado - Pendiente de entrega', { exact: true })).toHaveCount(2);
    });
    await test.step('editar premio después de reservar conserva el costo histórico', async () => {
        await page.getByRole('button', { name: 'Editar Esmalte en gel', exact: true }).click();
        const d = page.getByRole('dialog', { name: 'Editar premio vinculado' });
        await d.getByLabel('Puntos necesarios para canjear').fill('75');
        await d.getByRole('button', { name: 'Guardar cambios', exact: true }).click();
        await expect(d).not.toBeVisible();
    });
    await test.step('entregar un canje sin segundo débito', async () => {
        await tab(page, 'Historial');
        await page.locator('.ledger tbody tr').filter({ hasText: 'Esmalte en gel' }).getByRole('button', { name: 'Ver detalle' }).click();
        const detail = page.getByRole('dialog', { name: 'Detalle de canje' });
        await expect(detail).toContainText('50');
        await detail.getByRole('button', { name: 'Confirmar entrega', exact: true }).click();
        const d = page.getByRole('dialog', { name: 'Confirmar entrega', exact: true });
        await d.getByRole('checkbox').check();
        await d.getByRole('button', { name: 'Confirmar entrega', exact: true }).click();
        await expect(d).not.toBeVisible();
    });
    await test.step('cancelar el segundo canje devuelve puntos y cupo', async () => {
        await page.locator('.ledger tbody tr').filter({ hasText: 'Pedicura' }).getByRole('button', { name: 'Ver detalle' }).click();
        await page.getByRole('dialog', { name: 'Detalle de canje' }).getByRole('button', { name: 'Cancelar reserva', exact: true }).click();
        const d = page.getByRole('dialog', { name: 'Cancelar reserva', exact: true });
        await d.getByLabel('Motivo de cancelación').fill('La clienta prefiere otro servicio');
        await d.getByRole('button', { name: 'Cancelar y devolver puntos' }).click();
        await expect(d).not.toBeVisible();
        await client.goto('/clientes');
        await expect(client.locator('[data-testid=loyalty-balance]')).toHaveText('2,070');
        await expect(client.getByText('Meta alcanzada', { exact: true })).toBeVisible();
    });
    await test.step('historial sin resultados y recuperación de filtros', async () => {
        await page.getByLabel('Buscar canje').fill('no-existe-qa');
        await page.getByRole('button', { name: 'Buscar', exact: true }).click();
        await expect(page.getByText('No hay canjes con estos filtros.')).toBeVisible();
        await page.getByRole('button', { name: 'Limpiar filtros' }).click();
        await expect(page.locator('.ledger tbody tr')).toHaveCount(2);
    });
    await test.step('regalar puntos a dos clientas con revisión explícita', async () => {
        await tab(page, 'Regalos');
        const d = await selectRecipients(page);
        await d.getByLabel('Puntos por clienta').fill('200');
        await d.getByLabel('Motivo del regalo').fill('Regalo QA de bienvenida');
        await d.getByLabel('Mensaje para la clienta').fill('Gracias por ser parte de Studio Lemus');
        await reviewSend(d);
        const result = page.getByRole('dialog', { name: 'Resultado y detalle del regalo' });
        await expect(result.getByText('Puntos acreditados', { exact: true })).toHaveCount(2);
        await result.getByRole('button', { name: 'Cerrar', exact: true }).click();
        await client.goto('/clientes');
        await expect(client.locator('[data-testid=loyalty-balance]')).toHaveText('2,270');
        await expect(client.getByText('Diamante', { exact: true }).first()).toBeVisible();
    });
    await test.step('regalar servicios y confirmar uno sin puntos ni ventas', async () => {
        const d = await selectRecipients(page);
        await d.getByLabel('Tipo de regalo').selectOption('service');
        const opt = d.getByLabel('Servicio a regalar').locator('option').filter({ hasText: 'Pedicura' });
        await d.getByLabel('Servicio a regalar').selectOption((await opt.getAttribute('value'))!);
        await d.getByLabel('Motivo del regalo').fill('Servicios de cortesía QA');
        await reviewSend(d);
        const result = page.getByRole('dialog', { name: 'Resultado y detalle del regalo' });
        await expect(result.getByText('Disponible para entrega', { exact: true })).toHaveCount(2);
        await result.locator('article').filter({ hasText: 'Sofía Ramírez' }).getByRole('button', { name: 'Confirmar entrega' }).click();
        const confirm = page.getByRole('dialog', { name: 'Entregar servicio de regalo' });
        await confirm.getByRole('checkbox').check();
        await confirm.getByRole('button', { name: 'Confirmar', exact: true }).click();
        await expect(confirm).not.toBeVisible();
        await expect(result.getByText('Entregado', { exact: true })).toHaveCount(1);
        await result.locator('article').filter({ hasText: 'Carla de prueba' }).getByRole('button', { name: 'Cancelar regalo' }).click();
        const cancel = page.getByRole('dialog', { name: 'Cancelar regalo disponible' });
        await cancel.getByLabel('Motivo de cancelación').fill('Cortesía cancelada en prueba');
        await cancel.getByRole('button', { name: 'Confirmar', exact: true }).click();
        await expect(cancel).not.toBeVisible();
        await expect(result.getByText('Cancelado', { exact: true })).toBeVisible();
        await result.getByRole('button', { name: 'Cerrar', exact: true }).click();
    });
    await test.step('los regalos aparecen en el portal correcto', async () => {
        await client.goto('/clientes/recompensas');
        await expect(client.getByRole('heading', { name: 'Regalos para ti' })).toBeVisible();
        await expect(client.getByText('Servicio entregado', { exact: true })).toBeVisible();
        await expect(client.getByText('+200 puntos de regalo', { exact: true })).toBeVisible();
        await client.goto('/clientes');
        await expect(client.locator('[data-testid=loyalty-balance]')).toHaveText('2,270');
    });
    await test.step('editar un nivel con revisión sin alterar saldos', async () => {
        await tab(page, 'Niveles');
        await page.getByRole('button', { name: 'Editar nivel Diamante' }).click();
        const d = page.getByRole('dialog', { name: 'Editar nivel', exact: true });
        await d.getByLabel('Beneficios informativos').fill('Atenciones especiales según disponibilidad. Nivel máximo.');
        await d.getByRole('button', { name: 'Revisar cambios' }).click();
        const confirm = page.getByRole('dialog', { name: 'Confirmar cambios de nivel' });
        await confirm.getByRole('button', { name: 'Guardar cambios' }).click();
        await expect(confirm).not.toBeVisible();
        await client.goto('/clientes');
        await expect(client.getByText('Meta alcanzada', { exact: true })).toBeVisible();
    });
    await test.step('recorrido de navegación y captura final', async () => {
        await tab(page, 'Premios');
        await expect(page.locator('.app-sidebar')).toBeVisible();
        await page.getByRole('link', { name: 'Ver servicios →' }).click();
        await expect(page).toHaveURL(/configuration\/services/);
        await page.goto(root + '?tab=gifts');
        fs.mkdirSync('docs/audits/loyalty-v2', { recursive: true });
        await page.screenshot({ path: 'docs/audits/loyalty-v2/admin-final.png', fullPage: true });
        await client.goto('/clientes/recompensas');
        await client.evaluate(() =>
            Promise.all(
                document
                    .getAnimations()
                    .filter((a) => a.effect?.getTiming().iterations !== Infinity)
                    .map((a) => a.finished.catch(() => {})),
            ),
        );
        await client.screenshot({ path: 'docs/audits/loyalty-v2/client-final.png', fullPage: true });
    });
    expect(errors).toEqual([]);
    await context.close();
});

test('respuesta perdida después del envío: reintento real no duplica puntos', async ({ page, browser, baseURL }) => {
    await adminLogin(page);
    await tab(page, 'Regalos');
    const ctx = await browser.newContext({ baseURL, reducedMotion: 'reduce' });
    const client = await ctx.newPage();
    await clientLogin(client);
    const balance = Number((await client.locator('[data-testid=loyalty-balance]').innerText()).replaceAll(',', ''));
    await page.getByRole('button', { name: 'Crear regalo', exact: true }).click();
    const d = page.getByRole('dialog', { name: 'Crear regalo', exact: true });
    await expect(d.getByLabel('Seleccionar Sofía Ramírez')).toBeVisible();
    await d.getByLabel('Seleccionar Sofía Ramírez').check();
    await d.getByRole('button', { name: 'Continuar', exact: true }).click();
    await d.getByLabel('Puntos por clienta').fill('10');
    await d.getByLabel('Motivo del regalo').fill('Prueba de respuesta perdida');
    await d.getByRole('button', { name: 'Revisar regalo', exact: true }).click();
    await d.getByRole('checkbox').check();
    await page.route('**/configuration/loyalty/gifts', async (route) => {
        if (route.request().method() !== 'POST') return route.fallback();
        await route.fetch();
        await route.abort('failed');
    });
    await d.getByRole('button', { name: 'Enviar regalo a 1 clienta', exact: true }).click();
    await expect(d.getByText(/Conexión interrumpida/)).toBeVisible();
    await client.reload();
    await expect(client.locator('[data-testid=loyalty-balance]')).toHaveText(new Intl.NumberFormat('en-US').format(balance + 10));
    await page.unroute('**/configuration/loyalty/gifts');
    await d.getByRole('button', { name: 'Enviar regalo a 1 clienta', exact: true }).click();
    await expect(d).not.toBeVisible();
    await client.reload();
    await expect(client.locator('[data-testid=loyalty-balance]')).toHaveText(new Intl.NumberFormat('en-US').format(balance + 10));
    await ctx.close();
});

for (const width of [320, 768, 1440])
    for (const section of ['rewards', 'history', 'levels', 'gifts']) {
        test(`interfaz ${section} a ${width}px sin recortes`, async ({ page }) => {
            await page.setViewportSize({ width, height: 900 });
            await adminLogin(page);
            await page.goto(`${root}?tab=${section}`);
            await expect(page.locator('.loyalty-v2 .tabs')).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)).toBe(false);
            for (const button of await page.locator('.loyalty-v2 button:visible').all()) {
                const box = await button.boundingBox();
                if (box) expect(box.x + box.width).toBeLessThanOrEqual(width + 1);
            }
        });
    }

test('modal: validación nativa, descartar y Escape mantienen navegación', async ({ page }) => {
    await adminLogin(page);
    await page.getByRole('button', { name: 'Añadir premio', exact: true }).click();
    const d = page.getByRole('dialog', { name: 'Añadir premio', exact: true });
    await d.getByRole('button', { name: 'Guardar premio', exact: true }).click();
    await expect(d).toBeVisible();
    await d.getByRole('button', { name: 'Cancelar', exact: true }).click();
    await expect(d).not.toBeVisible();
    await page.getByRole('button', { name: 'Añadir premio', exact: true }).click();
    await d.getByLabel('Descripción pública').fill('Cambios sin guardar');
    await page.keyboard.press('Escape');
    const discard = page.getByRole('dialog', { name: '¿Descartar cambios?' });
    await expect(discard).toBeVisible();
    await discard.getByRole('button', { name: 'Seguir editando' }).click();
    await expect(d.getByLabel('Descripción pública')).toHaveValue('Cambios sin guardar');
    await d.getByRole('button', { name: 'Cancelar', exact: true }).click();
    await discard.getByRole('button', { name: 'Descartar', exact: true }).click();
    await expect(d).not.toBeVisible();
});

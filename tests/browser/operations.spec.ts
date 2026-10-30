import { test, expect } from '@playwright/test';
import { formatTime } from '../../resources/js/utils/time';

async function selectOption(page: any, label: string, option: string) {
    // Vuetify's visible field is the pointer target; its readonly input is
    // intentionally covered by the selected-value presentation.
    await page
        .locator('.v-select')
        .filter({ has: page.getByRole('combobox', { name: label, exact: true }) })
        .locator('.v-field')
        .click();
    await page.getByRole('option', { name: option, exact: true }).click();
}

async function login(page: any, customer = false, email?: string) {
    await page.goto(customer ? '/clientes/login' : '/login');
    await page.getByLabel('Correo electrónico').fill(email ?? (customer ? 'clienta@lemus.test' : 'admin@lemus.test'));
    await page.getByLabel('Contraseña', { exact: true }).fill('LocalTesting123!');
    await page.getByRole('button', { name: customer ? 'Entrar' : 'Iniciar sesión', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
}
test('customer profile, private navigation and QR', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await login(page, true);
    await expect(page.getByRole('heading', { name: 'Sofía Ramírez' })).toBeVisible();
    await page.getByRole('button', { name: 'Mostrar mi QR de fidelidad' }).click();
    await expect(page.getByAltText('QR temporal de fidelidad')).toBeVisible();
    await page.getByRole('button', { name: 'Cerrar', exact: true }).click();
    await page.goto('/clientes/progreso');
    await expect(page.getByRole('heading', { name: 'Mi progreso' })).toBeVisible();
    await page.goto('/clientes/recompensas');
    await expect(page.getByRole('heading', { name: 'Mis recompensas' })).toBeVisible();
    const response = await page.goto('/configuration/users');
    expect(response?.status()).toBeLessThan(500);
    await expect(page).toHaveURL(/\/login$/);
    expect(errors).toEqual([]);
});
test('admin key pages responsive and without javascript errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await login(page);
    for (const url of [
        '/sales/new',
        '/appointments',
        '/invoices',
        '/expenses',
        '/configuration/users',
        '/configuration/services',
        '/configuration/loyalty/rewards',
        '/configuration/business-hours',
        '/configuration/daily-close',
        '/configuration/users/2/compensation',
        '/appointments/history',
        '/earnings',
    ]) {
        const response = await page.goto(url);
        expect(response?.status(), url).toBe(200);
        await expect(page.locator('h1').first()).toBeVisible();
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1),
            url,
        ).toBeTruthy();
    }
    expect(errors).toEqual([]);
});
test('catalog promotions and repeated mobile sheet close releases scrolling', async ({ page }, info) => {
    await login(page);
    await page.goto('/sales/new');
    await page.getByRole('tab', { name: 'Promociones', exact: true }).click();
    await expect(page.getByText('Promoción de temporada', { exact: true })).toBeVisible();
    await expect(page.getByText('Esmalte en gel', { exact: true })).toHaveCount(0);
    await page.getByRole('tab', { name: 'Servicios', exact: true }).click();
    await page.getByRole('button', { name: 'Agregar Baño de acrílico', exact: true }).click();
    if (info.project.name === 'mobile') {
        for (let n = 0; n < 3; n++) {
            await page.getByTestId('sale-mobile-summary').click();
            await page.getByRole('button', { name: 'Cerrar resumen' }).click();
            await expect
                .poll(() =>
                    page.evaluate(() => document.documentElement.classList.contains('v-overlay-scroll-blocked')),
                )
                .toBeFalsy();
        }
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        expect(await page.evaluate(() => window.scrollY)).toBeGreaterThan(0);
    }
});

test('sale checkout and QR award update only the identified customer', async ({ page, browser }, info) => {
    await login(page);
    await page.goto('/sales/new');
    await page.getByRole('button', { name: 'Agregar Esmalte en gel', exact: true }).click();
    await page.getByRole('button', { name: 'Descuentos individuales por servicio' }).click();
    await selectOption(page, 'Descuento de este servicio', 'Porcentaje');
    await page.getByLabel('Porcentaje', { exact: true }).fill('10');
    await page.getByLabel('Motivo obligatorio', { exact: true }).fill('Descuento de QA local');
    if (info.project.name === 'mobile') await page.getByTestId('sale-mobile-checkout-button').click();
    else await page.getByRole('button', { name: /^Cobrar / }).click();
    await page.getByRole('button', { name: 'Confirmar y generar recibo' }).click();
    await expect(page).toHaveURL(/\/sales\/\d+\/receipt/);
    await expect(page.getByText('L 315.00', { exact: true }).first()).toBeVisible();
    const customerContext = await browser.newContext();
    const customerPage = await customerContext.newPage();
    await login(customerPage, true);
    await customerPage.reload();
    const before = Number(await customerPage.getByTestId('loyalty-balance').textContent());
    const token = await customerPage.evaluate(async () => {
        const response = await fetch('/clientes/qr', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')!.getAttribute('content')!,
                Accept: 'application/json',
            },
        });
        return (await response.json()).token;
    });
    await page.getByRole('button', { name: 'Asignar puntos', exact: true }).click();
    await page.getByRole('button', { name: 'Abrir cámara', exact: true }).click();
    await expect(page.getByText(/No se pudo abrir la cámara/)).toBeVisible();
    await page.getByLabel('Código temporal alternativo').fill(token);
    await page.getByRole('button', { name: 'Verificar clienta' }).click();
    await expect(page.getByText('Confirmar asociación con Sofía Ramírez.')).toBeVisible();
    await page.getByRole('button', { name: 'Confirmar asignación' }).click();
    await expect(page.getByRole('heading', { name: /Asignar puntos/ })).toHaveCount(0);
    await customerPage.reload();
    const after = Number(await customerPage.getByTestId('loyalty-balance').textContent());
    expect(after - before).toBe(20);
    await customerContext.close();
});

test('responsive width matrix and customer screenshot', async ({ page }, info) => {
    await login(page);
    for (const width of [320, 360, 390, 430, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/sales/new');
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),
            `sales at ${width}`,
        ).toBeTruthy();
        await page.goto('/configuration/users/2/permissions');
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),
            `employee options at ${width}`,
        ).toBeTruthy();
        await page.goto('/appointments?view=day&date=2030-01-07&month=2030-01');
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),
            `agenda at ${width}`,
        ).toBeTruthy();
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, true);
    await page.goto('/clientes/perfil');
    await page.screenshot({ path: 'test-results/customer-profile-390.png', fullPage: true });
});

test('appointment services can be edited with AM PM controls', async ({ page }, info) => {
    await login(page);
    await page.goto('/appointments?view=day&date=2030-01-07&month=2030-01');
    const card = page.locator('.appointment-card').filter({ hasText: 'Cita QA editable' });
    if (info.project.name === 'mobile') {
        await card.getByRole('button', { name: 'Más acciones', exact: true }).click();
        await page.locator('.v-overlay--active .v-list').getByText('Editar servicios', { exact: true }).click();
    } else await card.getByRole('button', { name: 'Editar servicios', exact: true }).click();
    await expect(page.getByRole('combobox', { name: 'Hora de inicio · AM/PM' })).toHaveValue('AM');
    while ((await page.getByRole('button', { name: 'Quitar servicio', exact: true }).count()) > 1)
        await page.getByRole('button', { name: 'Quitar servicio', exact: true }).last().click();
    await selectOption(page, 'Agregar servicio', 'Pedicura');
    await page.getByRole('button', { name: 'Guardar cambios', exact: true }).click();
    await expect(page).toHaveURL(/\/appointments\?/);
    await expect(page.getByText('Cita QA editable', { exact: true })).toBeVisible();
});

test('time labels cover noon and midnight without changing stored values', () => {
    expect(formatTime('00:00')).toBe('12:00 a. m.');
    expect(formatTime('12:00')).toBe('12:00 p. m.');
    expect(formatTime('13:05')).toBe('1:05 p. m.');
    expect(formatTime('23:59:00')).toBe('11:59 p. m.');
});

test('employee order, meal and delegated checkout do not grant reports', async ({ page, browser }, info) => {
    await login(page);
    await page.goto('/configuration/users?search=empleada%40lemus.test');
    await page
        .getByRole('button', {
            name: info.project.name === 'mobile' ? 'Acciones de Empleada QA' : 'Acciones del usuario',
            exact: true,
        })
        .click();
    await page.locator('.v-overlay--active .v-list').getByText('Permisos, orden y comida', { exact: true }).click();
    await page.getByRole('checkbox', { name: 'Cobrar servicios de cualquier empleado', exact: true }).check();
    await page.getByRole('button', { name: 'Guardar permisos', exact: true }).click();
    await expect(page.getByText('Permisos individuales actualizados.', { exact: true })).toBeVisible();
    while (await page.getByRole('button', { name: 'Quitar descanso', exact: true }).count())
        await page.getByRole('button', { name: 'Quitar descanso', exact: true }).last().click();
    await page.getByRole('button', { name: 'Agregar comida', exact: true }).click();
    await expect(page.getByRole('combobox', { name: 'Inicio comida · AM/PM', exact: true })).toHaveValue('PM');
    await page.getByLabel('Orden de vista (menor aparece primero)', { exact: true }).fill('3');
    await page.getByRole('button', { name: 'Guardar horario y orden', exact: true }).click();
    await expect(page.getByText('Orden y comidas actualizados.', { exact: true })).toBeVisible();
    await page.goto('/appointments?view=day&date=2030-01-07&month=2030-01');
    await expect(page.getByText('Comidas · No disponible', { exact: true })).toBeVisible();
    const context = await browser.newContext();
    const employee = await context.newPage();
    await login(employee, false, 'empleada@lemus.test');
    await employee.goto('/sales/new');
    await expect(employee.getByRole('combobox', { name: 'Atendida por', exact: true })).toBeVisible();
    const response = await employee.goto('/earnings');
    expect(response?.status()).toBe(403);
    await context.close();
});

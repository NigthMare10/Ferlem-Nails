import { test, expect } from '@playwright/test';

test('recovery requests an email link and never asks for a new password before verification', async ({ page }) => {
    await page.goto('/clientes/olvide-contrasena');
    await expect(page.getByLabel('Nueva contraseña', { exact: true })).toHaveCount(0);
    await expect(page.getByLabel('Confirmar nueva contraseña', { exact: true })).toHaveCount(0);
    await page.getByLabel('Correo electrónico', { exact: true }).fill('clienta@lemus.test');
    await page.getByRole('button', { name: 'Enviar enlace de recuperación', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('recibirás un enlace', { timeout: 30000 });
    await page.goto('/clientes/login');
    await page.getByLabel('Correo electrónico', { exact: true }).fill('clienta@lemus.test');
    await page.getByLabel('Contraseña', { exact: true }).fill('LocalTesting123!');
    await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).click();
    await expect(page).toHaveURL(/\/clientes$/);
});

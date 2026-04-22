import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

import {
    getDetalleFacturaDigitalActionScript,
    transformDetalleFacturaDigitalHtml,
} from '../resources/js/Pages/Stitch/detalleFacturaDigitalEnhancements.js';

const currentDir = dirname(fileURLToPath(import.meta.url));
const referenceHtml = readFileSync(
    resolve(currentDir, '../references/stitch-export/stitch_sistema_pos_u_as_y_pesta_as/detalle_de_factura_digital/code.html'),
    'utf8',
);

const userHtml = transformDetalleFacturaDigitalHtml(referenceHtml, { isAdmin: false });
const adminHtml = transformDetalleFacturaDigitalHtml(referenceHtml, { isAdmin: true });
const userActionScript = getDetalleFacturaDigitalActionScript({ isAdmin: false });
const adminActionScript = getDetalleFacturaDigitalActionScript({ isAdmin: true });

test('detalle factura digital renderiza imprimir con handler real', () => {
    assert.match(userHtml, /data-stitch-print="true"/);
    assert.match(userHtml, />\s*Imprimir\s*</);
    assert.match(userActionScript, /printTarget\.print\(\)/);
});

test('detalle factura digital resuelve volver segun contexto', () => {
    assert.match(userHtml, /href="\/inicio-de-cobro"/);
    assert.match(userHtml, />\s*Volver al Cobro\s*</);
    assert.match(userActionScript, /\/inicio-de-cobro/);

    assert.match(adminHtml, /href="\/historial-de-facturas"/);
    assert.match(adminHtml, />\s*Volver al Historial\s*</);
    assert.match(adminActionScript, /\/historial-de-facturas/);
});

test('detalle factura digital expone engranaje con menu salir y logout real', () => {
    assert.match(userHtml, /data-stitch-settings="true"/);
    assert.match(adminHtml, /data-stitch-settings="true"/);
    assert.match(userActionScript, /button\.textContent = 'Salir'/);
    assert.match(userActionScript, /logout\(\)/);
    assert.match(adminActionScript, /data-stitch-detail-menu/);
});

test('detalle factura digital admin tiene navegacion funcional', () => {
    assert.match(adminHtml, /href="\/reportes-de-ventas-analytics"[^>]*>Panel</);
    assert.match(adminHtml, /href="\/historial-de-facturas"[^>]*>Facturas</);
    assert.match(adminHtml, /href="\/gestion-de-empleados-admin"[^>]*>Empleados</);
    assert.match(adminHtml, /href="\/reportes-de-ventas-analytics"[^>]*>Analitica</);
});

test('detalle factura digital elimina tarjeta e imagen rota', () => {
    assert.doesNotMatch(userHtml, /Tarjeta de Credito|Tarjeta de Crédito|4242|Metodo|Método/i);
    assert.doesNotMatch(adminHtml, /Tarjeta de Credito|Tarjeta de Crédito|4242|Metodo|Método/i);
    assert.doesNotMatch(adminHtml, /<img alt="Admin profile"/i);
    assert.match(adminHtml, /data-stitch-profile-badge="true"/);
    assert.match(adminHtml, />AD</);
});

test('detalle factura digital elimina placeholder decorativo roto', () => {
    assert.doesNotMatch(userHtml, /Aesthetic background detail/i);
    assert.doesNotMatch(adminHtml, /Aesthetic background detail/i);
});

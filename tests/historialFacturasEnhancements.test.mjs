import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

import {
    getHistorialFacturasActionScript,
    transformHistorialFacturasHtml,
} from '../resources/js/Pages/Stitch/historialFacturasEnhancements.js';

const currentDir = dirname(fileURLToPath(import.meta.url));
const referenceHtml = readFileSync(
    resolve(currentDir, '../references/stitch-export/stitch_sistema_pos_u_as_y_pesta_as/historial_de_facturas/code.html'),
    'utf8',
);

const historyPayload = {
    summary: {
        total_invoices_label: '2',
        total_invoices_count: 2,
        total_revenue_label: 'L 1,552.50',
        average_ticket_label: 'L 776.25',
        services_count_label: '3',
        range_label: '21 Abr 2026 - 22 Abr 2026',
    },
    employees: ['Administracion General', 'Prueba'],
    invoices: [
        {
            public_id: '01historialabc',
            number: 'FNL-CM-00000001',
            reference: 'REF-00000001',
            issued_date: '22 Abr 2026',
            issued_date_short: '22 Abr 2026',
            issued_time: '11:15 am',
            issued_timezone: 'America/Tegucigalpa',
            operator_name: 'Administracion General',
            status_label: 'Pagada',
            status_key: 'pagada',
            total_formatted: 'L 1,035.00',
            total_raw: '1035.00',
            detail_url: '/detalle-de-factura-digital/01historialabc',
        },
        {
            public_id: '01historialxyz',
            number: 'FNL-CM-00000002',
            reference: 'REF-00000002',
            issued_date: '21 Abr 2026',
            issued_date_short: '21 Abr 2026',
            issued_time: '04:30 pm',
            issued_timezone: 'America/Tegucigalpa',
            operator_name: 'Prueba',
            status_label: 'Pagada',
            status_key: 'pagada',
            total_formatted: 'L 517.50',
            total_raw: '517.50',
            detail_url: '/detalle-de-factura-digital/01historialxyz',
        },
    ],
    routes: {
        overview: '/reportes-de-ventas-analytics',
        history: '/historial-de-facturas',
        export: '/historial-de-facturas/exportar-csv',
        latest_invoice: '/detalle-de-factura-digital/01historialabc',
    },
};

const transformedHtml = transformHistorialFacturasHtml(referenceHtml, historyPayload);
const actionScript = getHistorialFacturasActionScript();

test('historial de facturas conecta navegacion y elimina botones muertos', () => {
    assert.match(transformedHtml, /href="\/reportes-de-ventas-analytics"[\s\S]*?<span>Resumen<\/span>/);
    assert.match(transformedHtml, /href="\/historial-de-facturas"[\s\S]*?<span>Historial de Facturas<\/span>/);
    assert.match(transformedHtml, /data-history-filter="pagada"/);
    assert.doesNotMatch(transformedHtml, />\s*Pendientes\s*</);
    assert.doesNotMatch(transformedHtml, />\s*Reembolsos\s*</);
});

test('historial de facturas conecta buscador y filtros visibles', () => {
    assert.match(transformedHtml, /data-history-search="true"/);
    assert.match(transformedHtml, /data-history-employee="true"/);
    assert.match(transformedHtml, /data-history-search-value=/);
    assert.match(actionScript, /applyFilters/);
    assert.match(actionScript, /data-history-search-value/);
});

test('historial de facturas conecta ver factura y exportar csv', () => {
    assert.match(transformedHtml, /href="\/detalle-de-factura-digital\/01historialabc"[^>]*>Ver Factura</);
    assert.match(transformedHtml, /href="\/historial-de-facturas\/exportar-csv"/);
});

test('historial de facturas expone engranaje para menu salir', () => {
    assert.match(transformedHtml, /data-stitch-settings="true"/);
    assert.match(actionScript, /toggleSettingsMenu\(\)/);
});

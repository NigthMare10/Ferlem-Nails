const HISTORY_SETTINGS_TRIGGER = '<button type="button" class="material-symbols-outlined cursor-pointer hover:text-primary transition-colors" data-stitch-settings="true" data-stitch-history-action="true" aria-label="Abrir menu de sesion">settings</button>';
const HISTORY_PROFILE_BADGE = '<div class="w-8 h-8 rounded-full border border-outline-variant/30 bg-surface-container-highest flex items-center justify-center text-[10px] font-bold text-[#7d562d]">AD</div>';

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function buildHistorySidebar(history) {
    const latestInvoiceUrl = history?.routes?.latest_invoice;
    const latestInvoiceAction = latestInvoiceUrl
        ? `<a href="${escapeHtml(latestInvoiceUrl)}" target="_top" data-history-latest-link="true" data-stitch-static-route="true" class="mt-auto block bg-gradient-to-br from-primary to-primary-container text-white p-4 rounded-xl font-medium tracking-tight editorial-shadow active:opacity-90 transition-opacity text-center">Ver Factura</a>`
        : '<span class="mt-auto block bg-gradient-to-br from-primary to-primary-container text-white/70 p-4 rounded-xl font-medium tracking-tight editorial-shadow text-center pointer-events-none opacity-70">Ver Factura</span>';

    return `<aside class="h-screen w-64 fixed left-0 top-0 bg-surface-container-low flex flex-col p-6 space-y-4 font-['Manrope'] tracking-wide z-40">
<div class="mb-8 px-2">
<h1 class="text-xl font-serif text-primary">FERLEM NAILS</h1>
<p class="text-xs text-stone-500 uppercase tracking-[0.2em]">Portal de Gestion</p>
</div>
<nav class="flex-1 space-y-2">
<a href="${escapeHtml(history?.routes?.overview ?? '/reportes-de-ventas-analytics')}" target="_top" data-stitch-static-route="true" class="flex items-center gap-3 p-3 text-stone-500 hover:bg-white/50 transition-all scale-95 duration-150">
<span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
<span>Resumen</span>
</a>
<a href="${escapeHtml(history?.routes?.history ?? '/historial-de-facturas')}" target="_top" data-stitch-static-route="true" class="flex items-center gap-3 p-3 bg-surface-container-lowest text-on-surface rounded-lg font-semibold transition-all">
<span class="material-symbols-outlined" data-icon="receipt_long">receipt_long</span>
<span>Historial de Facturas</span>
</a>
<button type="button" data-stitch-history-action="true" data-history-filter="pagada" class="flex w-full items-center gap-3 p-3 text-stone-500 hover:bg-white/50 transition-all scale-95 duration-150 text-left">
<span class="material-symbols-outlined" data-icon="check_circle">check_circle</span>
<span>Pagadas</span>
</button>
</nav>
${latestInvoiceAction}
</aside>`;
}

function buildHistoryHeader(history) {
    return `<header class="fixed top-0 right-0 left-64 h-20 bg-surface/70 backdrop-blur-md z-30 flex justify-between items-center px-8 border-none">
<h2 class="text-2xl font-headline italic text-on-surface">Historial de Facturas</h2>
<div class="flex items-center gap-6">
<div class="relative flex items-center bg-surface-container-low px-4 py-2 rounded-full border border-outline-variant/20 focus-within:border-primary transition-all">
<span class="material-symbols-outlined text-stone-400 text-sm">search</span>
<input data-history-search="true" value="${escapeHtml(history?.filters?.query ?? '')}" class="bg-transparent border-none focus:ring-0 text-sm w-48 placeholder:text-stone-400" placeholder="Buscar folio o cliente..." type="text"/>
</div>
<div class="flex items-center gap-4 text-stone-500">
<span class="material-symbols-outlined text-stone-400">notifications</span>
${HISTORY_SETTINGS_TRIGGER}
${HISTORY_PROFILE_BADGE}
</div>
</div>
</header>`;
}

function buildHistorySummary(summary) {
    return `<section class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
<div class="bg-surface-container-lowest p-8 rounded-xl editorial-shadow relative overflow-hidden group">
<div class="absolute -right-4 -top-4 opacity-5 group-hover:opacity-10 transition-opacity">
<span class="material-symbols-outlined text-9xl" style="font-variation-settings: 'wght' 100;">receipt</span>
</div>
<p class="text-xs uppercase tracking-[0.2em] text-stone-500 mb-2">Total Facturas</p>
<h3 class="text-4xl font-headline font-bold text-on-surface tracking-tighter">${escapeHtml(summary?.total_invoices_label ?? '0')}</h3>
<div class="mt-4 flex items-center gap-2 text-xs text-green-600">
<span class="material-symbols-outlined text-sm">trending_up</span>
<span>Historial operativo disponible</span>
</div>
</div>
<div class="bg-surface-container-lowest p-8 rounded-xl editorial-shadow relative overflow-hidden group md:col-span-2">
<div class="absolute right-0 top-0 w-1/2 h-full opacity-10 bg-gradient-to-l from-primary-container to-transparent"></div>
<p class="text-xs uppercase tracking-[0.2em] text-stone-500 mb-2">Recaudacion Total</p>
<div class="flex items-baseline gap-2">
<h3 class="text-4xl font-headline font-bold text-on-surface tracking-tighter">${escapeHtml(summary?.total_revenue_label ?? 'L 0.00')}</h3>
<span class="text-stone-400 font-serif italic">HNL</span>
</div>
<div class="mt-4 flex items-center gap-6">
<div class="flex flex-col">
<span class="text-[10px] uppercase text-stone-400 tracking-widest">Promedio Ticket</span>
<span class="font-semibold text-primary">${escapeHtml(summary?.average_ticket_label ?? 'L 0.00')}</span>
</div>
<div class="w-px h-8 bg-outline-variant/30"></div>
<div class="flex flex-col">
<span class="text-[10px] uppercase text-stone-400 tracking-widest">Servicios Realizados</span>
<span class="font-semibold text-on-surface">${escapeHtml(summary?.services_count_label ?? '0')}</span>
</div>
</div>
</div>
</section>`;
}

function buildEmployeeOptions(history) {
    const selectedValue = history?.filters?.employeePublicId ?? '';
    const employees = history?.employees ?? [];

    return ['<option value="">Todos los empleados</option>']
        .concat(employees.map((employee) => `<option value="${escapeHtml(employee.publicId)}"${employee.publicId === selectedValue ? ' selected' : ''}>${escapeHtml(employee.name)}</option>`))
        .join('');
}

function buildHistoryFilters(history) {
    return `<section class="bg-surface-container-low rounded-xl p-4 mb-8 flex flex-wrap items-center justify-between gap-4">
<div class="flex flex-wrap items-center gap-4">
<div class="relative group">
<label class="absolute -top-2 left-3 px-1 bg-surface-container-low text-[10px] font-bold text-primary uppercase tracking-tighter">Rango de Fecha</label>
<div class="flex items-center gap-2 bg-surface-container-lowest px-4 py-2.5 rounded-lg border-b-2 border-surface-container-highest transition-all">
<span class="material-symbols-outlined text-stone-400 text-sm">calendar_month</span>
<span class="text-sm font-medium">${escapeHtml(history?.summary?.range_label ?? 'Sin registros')}</span>
</div>
</div>
<div class="relative group">
<label class="absolute -top-2 left-3 px-1 bg-surface-container-low text-[10px] font-bold text-primary uppercase tracking-tighter">Atendido por</label>
<select data-history-employee="true" class="appearance-none bg-surface-container-lowest px-4 py-2.5 pr-10 rounded-lg border-none border-b-2 border-surface-container-highest focus:ring-0 focus:border-primary text-sm font-medium transition-all cursor-pointer">
${buildEmployeeOptions(history)}
</select>
<span class="material-symbols-outlined absolute right-3 top-3 text-stone-400 pointer-events-none text-sm">expand_more</span>
</div>
</div>
<div class="flex gap-2">
<a href="${escapeHtml(history?.routes?.export ?? '/historial-de-facturas/exportar-csv')}" target="_top" data-history-export-link="true" data-stitch-static-route="true" class="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-secondary hover:bg-secondary/5 transition-colors rounded-lg">
<span class="material-symbols-outlined text-sm">download</span>
Exportar CSV
</a>
</div>
</section>`;
}

function buildEmployeeBadge(name) {
    const initials = String(name ?? 'FN')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase() || 'FN';

    return `<div class="w-8 h-8 rounded-full bg-tertiary-fixed flex items-center justify-center overflow-hidden text-[10px] font-bold text-primary">${escapeHtml(initials)}</div>`;
}

function buildHistoryRows(history) {
    const invoices = history?.invoices ?? [];

    if (!invoices.length) {
        return '<tr data-history-empty-state="true"><td colspan="6" class="px-8 py-12 text-center text-sm text-stone-500">No hay facturas disponibles para la sucursal activa.</td></tr>';
    }

    return invoices.map((invoice) => `
<tr data-history-row="true" data-history-status="${escapeHtml(invoice.status_key)}" data-history-employee-ids="${escapeHtml((invoice.operator_public_ids ?? []).join('|'))}" data-history-detail-url="${escapeHtml(invoice.detail_url)}" data-history-search-value="${escapeHtml(invoice.search_index ?? '')}" class="hover:bg-surface-container-low transition-colors group">
<td class="px-8 py-6 font-headline font-semibold text-on-surface">${escapeHtml(invoice.number)}</td>
<td class="px-8 py-6">
<div class="flex flex-col">
<span class="text-sm font-medium">${escapeHtml(invoice.issued_date)}</span>
<span class="text-xs text-stone-400">${escapeHtml(invoice.issued_time)}</span>
</div>
</td>
<td class="px-8 py-6">
<div class="flex items-center gap-3">
${buildEmployeeBadge(invoice.operator_name)}
<span class="text-sm">${escapeHtml(invoice.operator_name)}</span>
</div>
</td>
<td class="px-8 py-6">
<span class="px-3 py-1 bg-secondary-container/30 text-on-secondary-container text-[10px] font-bold uppercase tracking-widest rounded-full border border-secondary-container">${escapeHtml(invoice.status_label)}</span>
</td>
<td class="px-8 py-6 text-right font-headline font-bold text-on-surface">${escapeHtml(invoice.total_formatted)}</td>
<td class="px-8 py-6 text-right">
<a href="${escapeHtml(invoice.detail_url)}" target="_top" data-stitch-static-route="true" class="text-xs font-semibold uppercase tracking-widest text-primary hover:opacity-80">Ver Factura</a>
</td>
</tr>`).join('');
}

function buildHistoryTable(history) {
    const totalCount = history?.invoices?.length ?? 0;

    return `<section class="bg-surface-container-lowest rounded-xl editorial-shadow overflow-hidden">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-surface-container-low/50 border-b border-outline-variant/10">
<th class="px-8 py-5 text-[11px] font-bold uppercase tracking-[0.2em] text-stone-400">Folio</th>
<th class="px-8 py-5 text-[11px] font-bold uppercase tracking-[0.2em] text-stone-400">Fecha y Hora</th>
<th class="px-8 py-5 text-[11px] font-bold uppercase tracking-[0.2em] text-stone-400">Atendido Por</th>
<th class="px-8 py-5 text-[11px] font-bold uppercase tracking-[0.2em] text-stone-400">Estado</th>
<th class="px-8 py-5 text-[11px] font-bold uppercase tracking-[0.2em] text-stone-400 text-right">Monto Total</th>
<th class="px-8 py-5 text-right text-[11px] font-bold uppercase tracking-[0.2em] text-stone-400">Accion</th>
</tr>
</thead>
<tbody class="divide-y divide-outline-variant/10">
${buildHistoryRows(history)}
</tbody>
</table>
<div class="px-8 py-6 bg-surface-container-low/30 border-t border-outline-variant/10 flex items-center justify-between">
<span data-history-results-count="true" class="text-xs text-stone-500 font-medium">Mostrando ${escapeHtml(totalCount)} de ${escapeHtml(totalCount)} facturas</span>
<span class="text-xs text-stone-400">Historial actualizado para la sucursal activa</span>
</div>
</section>`;
}

export function transformHistorialFacturasHtml(html, history) {
    let output = html;

    output = output.replace(/<aside class="h-screen w-64 fixed left-0 top-0 bg-surface-container-low flex flex-col p-6 space-y-4 font-\['Manrope'\] tracking-wide z-40">[\s\S]*?<\/aside>/, buildHistorySidebar(history));
    output = output.replace(/<header class="fixed top-0 right-0 left-64 h-20 bg-surface\/70 backdrop-blur-md z-30 flex justify-between items-center px-8 border-none">[\s\S]*?<\/header>/, buildHistoryHeader(history));
    output = output.replace(/<section class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">[\s\S]*?<\/section>/, buildHistorySummary(history?.summary));
    output = output.replace(/<section class="bg-surface-container-low rounded-xl p-4 mb-8 flex flex-wrap items-center justify-between gap-4">[\s\S]*?<\/section>/, buildHistoryFilters(history));
    output = output.replace(/<section class="bg-surface-container-lowest rounded-xl editorial-shadow overflow-hidden">[\s\S]*?<\/section>/, buildHistoryTable(history));
    output = output.replace(/<!-- Floating Action for Quick Add \(Editorial Touch\) -->[\s\S]*?<\/button>/, '');
    output = output.replace(/<div class="w-8 h-8 rounded-full overflow-hidden bg-surface-container-highest border border-outline-variant\/30">[\s\S]*?<\/div>/, HISTORY_PROFILE_BADGE);

    return output;
}

export function getHistorialFacturasActionScript() {
    return [
        "  if (currentScreen === 'Historial de Facturas' && !window.__ferlemHistorialFacturasBound) {",
        '    window.__ferlemHistorialFacturasBound = true;',
        '    const initHistoryFilters = () => {',
        "      const searchInput = document.querySelector('[data-history-search=\"true\"]');",
        "      const employeeSelect = document.querySelector('[data-history-employee=\"true\"]');",
        "      const resultsCounter = document.querySelector('[data-history-results-count=\"true\"]');",
        "      const rows = Array.from(document.querySelectorAll('[data-history-row=\"true\"]'));",
        "      const filterButtons = Array.from(document.querySelectorAll('[data-history-filter]'));",
        "      const settingsTrigger = document.querySelector('[data-stitch-settings=\"true\"][data-stitch-history-action=\"true\"]');",
        "      const exportLink = document.querySelector('[data-history-export-link=\"true\"]');",
        "      const latestInvoiceLink = document.querySelector('[data-history-latest-link=\"true\"]');",
        "      const normalizeHistoryValue = (value) => String(value || '').toLowerCase().trim();",
        "      let activeFilter = 'all';",
        '      const updateFilterStyles = () => {',
        '        filterButtons.forEach((button) => {',
        "          const isActive = button.getAttribute('data-history-filter') === activeFilter;",
        "          button.classList.toggle('bg-surface-container-lowest', isActive);",
        "          button.classList.toggle('text-on-surface', isActive);",
        "          button.classList.toggle('rounded-lg', isActive);",
        "          button.classList.toggle('font-semibold', isActive);",
        "          button.classList.toggle('text-stone-500', !isActive);",
        '        });',
        '      };',
        '      const updateExportHref = (query, employee) => {',
        '        if (!(exportLink instanceof HTMLAnchorElement)) return;',
        "        const url = new URL(exportLink.href, window.location.origin);",
        "        if (query) { url.searchParams.set('q', query); } else { url.searchParams.delete('q'); }",
        "        if (employee) { url.searchParams.set('employee', employee); } else { url.searchParams.delete('employee'); }",
        "        if (activeFilter !== 'all') { url.searchParams.set('status', activeFilter); } else { url.searchParams.delete('status'); }",
        '        exportLink.href = url.pathname + url.search;',
        '      };',
        '      const applyFilters = () => {',
        "        const query = normalizeHistoryValue(searchInput && 'value' in searchInput ? searchInput.value : '');",
        "        const employee = normalizeHistoryValue(employeeSelect && 'value' in employeeSelect ? employeeSelect.value : '');",
        '        let visibleCount = 0;',
        '        let latestVisibleUrl = null;',
        '        rows.forEach((row) => {',
        "          const searchValue = normalizeHistoryValue(row.getAttribute('data-history-search-value'));",
        "          const employeeIds = normalizeHistoryValue(row.getAttribute('data-history-employee-ids')).split('|').filter(Boolean);",
        "          const matchesSearch = !query || searchValue.includes(query);",
        "          const matchesEmployee = !employee || employeeIds.includes(employee);",
        "          const matchesFilter = activeFilter === 'all' || normalizeHistoryValue(row.getAttribute('data-history-status')) === activeFilter;",
        '          const visible = matchesSearch && matchesEmployee && matchesFilter;',
        "          row.style.display = visible ? '' : 'none';",
        '          if (visible) {',
        '            visibleCount += 1;',
        "            if (!latestVisibleUrl) latestVisibleUrl = row.getAttribute('data-history-detail-url');",
        '          }',
        '        });',
        '        if (resultsCounter) {',
        "          resultsCounter.textContent = 'Mostrando ' + visibleCount + ' de ' + rows.length + ' facturas';",
        '        }',
        '        if (latestInvoiceLink instanceof HTMLAnchorElement && latestVisibleUrl) {',
        '          latestInvoiceLink.href = latestVisibleUrl;',
        '        }',
        '        updateExportHref(query, employee);',
        '      };',
        '      filterButtons.forEach((button) => {',
        "        button.addEventListener('click', (event) => {",
        '          event.preventDefault();',
        "          activeFilter = button.getAttribute('data-history-filter') || 'all';",
        '          updateFilterStyles();',
        '          applyFilters();',
        '        });',
        '      });',
        "      searchInput && searchInput.addEventListener('input', applyFilters);",
        "      employeeSelect && employeeSelect.addEventListener('change', applyFilters);",
        '      if (settingsTrigger instanceof HTMLElement) {',
        "        settingsTrigger.onclick = (event) => {",
        '          event.preventDefault();',
        '          event.stopPropagation();',
        '          toggleSettingsMenu();',
        '        };',
        '      }',
        "      if (!rows.length) {",
        "        updateExportHref(normalizeHistoryValue(searchInput && 'value' in searchInput ? searchInput.value : ''), normalizeHistoryValue(employeeSelect && 'value' in employeeSelect ? employeeSelect.value : ''));",
        '        return;',
        '      }',
        '      updateFilterStyles();',
        '      applyFilters();',
        '    };',
        "    if (document.readyState === 'complete') {",
        '      initHistoryFilters();',
        '    } else {',
        "      window.addEventListener('load', initHistoryFilters, { once: true });",
        '    }',
        '  }',
    ].join('\n');
}

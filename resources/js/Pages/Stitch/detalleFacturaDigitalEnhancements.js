const DETAIL_PROFILE_BADGES = {
    admin: '<div data-stitch-profile-badge="true" class="w-10 h-10 rounded-full border border-outline-variant/20 bg-white flex items-center justify-center text-[10px] font-bold text-[#7d562d]">AD</div>',
    user: '<div data-stitch-profile-badge="true" class="w-10 h-10 rounded-full border border-outline-variant/20 bg-white flex items-center justify-center text-[10px] font-bold text-[#7d562d]">FN</div>',
};

const DETAIL_SETTINGS_TRIGGER = '<button type="button" class="material-symbols-outlined text-stone-500 cursor-pointer" data-stitch-settings="true" data-stitch-detail-action="true" aria-label="Abrir menu de sesion">settings</button>';

const ADMIN_NAV = `<nav class="hidden md:flex items-center space-x-8">
<a href="/reportes-de-ventas-analytics" target="_top" data-stitch-static-route="true" class="text-stone-500 dark:text-stone-400 font-['Noto_Serif'] font-normal hover:text-stone-900 dark:hover:text-stone-100 transition-colors cursor-pointer active:opacity-70">Panel</a>
<a href="/historial-de-facturas" target="_top" data-stitch-static-route="true" class="text-[#7d562d] dark:text-[#eab786] border-b-2 border-[#7d562d] pb-1 font-['Noto_Serif'] font-normal cursor-pointer active:opacity-70">Facturas</a>
<a href="/gestion-de-empleados-admin" target="_top" data-stitch-static-route="true" class="text-stone-500 dark:text-stone-400 font-['Noto_Serif'] font-normal hover:text-stone-900 dark:hover:text-stone-100 transition-colors cursor-pointer active:opacity-70">Empleados</a>
<a href="/reportes-de-ventas-analytics" target="_top" data-stitch-static-route="true" class="text-stone-500 dark:text-stone-400 font-['Noto_Serif'] font-normal hover:text-stone-900 dark:hover:text-stone-100 transition-colors cursor-pointer active:opacity-70">Analitica</a>
</nav>`;

function getDetailActionsAside(isAdmin) {
    const backHref = isAdmin ? '/historial-de-facturas' : '/inicio-de-cobro';
    const backLabel = isAdmin ? 'Volver al Historial' : 'Volver al Cobro';

    return `<aside class="md:w-64 space-y-8 order-2 md:order-1">
<div class="space-y-4">
<button type="button" data-stitch-print="true" data-stitch-detail-action="true" class="w-full flex items-center justify-center gap-2 editorial-gradient text-on-primary py-4 px-6 rounded-md font-semibold tracking-wide shadow-lg hover:opacity-90 transition-all cursor-pointer active:scale-95">
<span class="material-symbols-outlined">print</span>
<span>Imprimir</span>
</button>
<a href="${backHref}" target="_top" data-stitch-back="true" data-stitch-static-route="true" data-stitch-detail-action="true" class="w-full flex items-center justify-center gap-2 border border-outline-variant text-secondary py-4 px-6 rounded-md font-semibold tracking-wide hover:bg-surface-container-low transition-all cursor-pointer active:scale-95">
<span class="material-symbols-outlined">arrow_back</span>
<span>${backLabel}</span>
</a>
</div>
<div class="p-6 bg-surface-container-low rounded-xl space-y-6">
<h3 class="font-headline text-lg italic text-primary">Detalles de Cobro</h3>
<div class="space-y-4">
<div>
<p class="text-xs uppercase tracking-widest text-outline mb-1">Referencia</p>
<p class="font-semibold text-on-surface">#TXN-90210-LUX</p>
</div>
<div>
<p class="text-xs uppercase tracking-widest text-outline mb-1">Estado</p>
<span class="px-3 py-1 bg-secondary-container text-on-secondary-container text-xs rounded-full font-bold">PAGADA</span>
</div>
</div>
</div>
</aside>`;
}

export function transformDetalleFacturaDigitalHtml(html, { isAdmin }) {
    let output = html;

    output = output.replace(
        /<div class="text-2xl font-serif italic text-stone-900 dark:text-stone-50">[\s\S]*?<\/div>/,
        `<div class="text-2xl font-serif italic text-stone-900 dark:text-stone-50">${isAdmin ? 'FERLEM NAILS Admin' : 'FERLEM NAILS'}</div>`,
    );
    output = output.replace(/<nav class="hidden md:flex items-center space-x-8">[\s\S]*?<\/nav>/, isAdmin ? ADMIN_NAV : '');
    output = output.replace(/<aside class="md:w-64 space-y-8 order-2 md:order-1">[\s\S]*?<\/aside>/, getDetailActionsAside(isAdmin));
    output = output.replace(/<span class="material-symbols-outlined text-stone-500 cursor-pointer">settings<\/span>/g, DETAIL_SETTINGS_TRIGGER);
    output = output.replace(/<button class="material-symbols-outlined p-2 hover:bg-\[#f6f3f2\] rounded-full transition-colors" data-icon="settings">settings<\/button>/g, '<button type="button" class="material-symbols-outlined p-2 hover:bg-[#f6f3f2] rounded-full transition-colors" data-stitch-settings="true" data-stitch-detail-action="true" data-icon="settings" aria-label="Abrir menu de sesion">settings</button>');
    output = output.replace(/<img alt="Admin profile"[^>]*>/, isAdmin ? DETAIL_PROFILE_BADGES.admin : DETAIL_PROFILE_BADGES.user);
    output = output.replace(/<p class="text-\[10px\] text-outline leading-tight">Moneda: HNL<br\/>Precios incluyen IVA<\/p>/, '<p class="text-[10px] text-outline leading-tight">Moneda: HNL<br/>Operacion asociada al perfil autenticado</p>');
    output = output.replace(/<!-- Contextual Aesthetic Decoration \(Asymmetry\) -->[\s\S]*?<\/div>\s*<\/body>/, '</body>');
    output = output.replace(/Aesthetic background detail/gi, '');

    return output;
}

export function getDetalleFacturaDigitalActionScript({ isAdmin }) {
    const backRoute = isAdmin ? '/historial-de-facturas' : '/inicio-de-cobro';

    return [
        "  if (currentScreen === 'Detalle de Factura Digital' && !window.__ferlemDetalleFacturaBound) {",
        '    window.__ferlemDetalleFacturaBound = true;',
        "    const detailInvoiceBackRoute = '" + backRoute + "';",
        "    const detailInvoiceSettingsMenuId = 'stitch-settings-menu';",
        '    const closeDetailInvoiceSettingsMenu = () => {',
        '      const existingMenu = document.getElementById(detailInvoiceSettingsMenuId);',
        '      if (existingMenu) existingMenu.remove();',
        '    };',
        '    const toggleDetailInvoiceSettingsMenu = () => {',
        '      const existingMenu = document.getElementById(detailInvoiceSettingsMenuId);',
        '      if (existingMenu) {',
        '        existingMenu.remove();',
        '        return;',
        '      }',
        "      const menu = document.createElement('div');",
        '      menu.id = detailInvoiceSettingsMenuId;',
        "      menu.setAttribute('role', 'menu');",
        "      menu.setAttribute('data-stitch-detail-menu', 'true');",
        "      const button = document.createElement('button');",
        "      button.type = 'button';",
        "      button.textContent = 'Salir';",
        "      button.onclick = (event) => { event.preventDefault(); logout(); };",
        '      menu.appendChild(button);',
        '      document.body.appendChild(menu);',
        '    };',
        '    const bindDetailInvoiceActions = () => {',
        "      document.querySelectorAll('[data-stitch-print=\"true\"]').forEach((button) => {",
        '        button.onclick = (event) => {',
        '          event.preventDefault();',
        "          window.parent.postMessage({ type: 'ferlem:print-detail' }, '*');",
        '        };',
        '      });',
        "      document.querySelectorAll('[data-stitch-back=\"true\"]').forEach((link) => {",
        '        link.onclick = (event) => {',
        '          event.preventDefault();',
        '          window.top.location.assign(detailInvoiceBackRoute);',
        '        };',
        '      });',
        "      document.querySelectorAll('[data-stitch-settings=\"true\"]').forEach((button) => {",
        '        button.onclick = (event) => {',
        '          event.preventDefault();',
        '          event.stopPropagation();',
        '          toggleDetailInvoiceSettingsMenu();',
        '        };',
        '      });',
        '    };',
        "    document.addEventListener('click', (event) => {",
        '      const target = event.target;',
        '      if (!(target instanceof HTMLElement)) return;',
        "      if (target.closest('[data-stitch-settings=\"true\"]')) return;",
        "      if (!target.closest('#stitch-settings-menu')) closeDetailInvoiceSettingsMenu();",
        '    });',
        '    bindDetailInvoiceActions();',
        '  }',
    ].join('\n');
}

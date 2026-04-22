export type AdminNavItem = {
    icon: string;
    label: string;
    href: string;
    active?: boolean;
};

export const adminRouteMap = {
    checkout: '/inicio-de-cobro',
    reports: '/reportes-de-ventas-analytics',
    invoices: '/historial-de-facturas',
    employees: '/gestion-de-empleados-admin',
    pricing: '/ajuste-de-precios-admin',
    settings: '/configuracion-admin',
    logout: '/logout',
} as const;

export function buildAdminPrimaryNav(activeKey: 'reports' | 'employees' | 'pricing'): AdminNavItem[] {
    return [
        { icon: 'point_of_sale', label: 'Inicio de Cobro', href: adminRouteMap.checkout },
        { icon: 'badge', label: 'Gestión de Empleados', href: adminRouteMap.employees, active: activeKey === 'employees' },
        { icon: 'payments', label: 'Ajuste de Precios', href: adminRouteMap.pricing, active: activeKey === 'pricing' },
        { icon: 'query_stats', label: 'Analítica', href: adminRouteMap.reports, active: activeKey === 'reports' },
    ];
}

export function buildEmployeePerformanceLinks(employeePublicId: string) {
    return {
        resumen: adminRouteMap.reports,
        rendimiento: `/rendimiento-por-empleado/${employeePublicId}`,
        ganancias: `/rendimiento-por-empleado/${employeePublicId}/ganancias`,
        historial: `/rendimiento-por-empleado/${employeePublicId}/historial-completo`,
        exportar: `/rendimiento-por-empleado/${employeePublicId}/exportar`,
        equipo: adminRouteMap.employees,
        panel: adminRouteMap.reports,
        configuracion: adminRouteMap.settings,
    };
}

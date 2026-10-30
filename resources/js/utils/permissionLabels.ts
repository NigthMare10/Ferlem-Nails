const exact: Record<string, string> = {
    'sales.checkout_any': 'Cobrar servicios de cualquier empleado',
    'sales.apply_item_discount': 'Aplicar descuentos individuales por servicio',
    'sales.apply_frequent_discount': 'Aplicar descuento de clienta frecuente',
    'appointments.edit_services': 'Agregar o quitar servicios de citas',
    'appointments.update_any': 'Modificar citas de otras personas',
    'loyalty.award': 'Vincular clientas y asignar puntos de ventas',
    'loyalty.redeem': 'Confirmar entrega o cancelar reserva de recompensas',
    'loyalty.manage': 'Administrar el catálogo de recompensas y miembros',
    'loyalty.adjust': 'Hacer ajustes extraordinarios de puntos',
};
const sections: Record<string, string> = {
    sales: 'Ventas',
    appointments: 'Agenda',
    users: 'Usuarios',
    services: 'Servicios',
    settings: 'Configuración',
    reports: 'Reportes',
    expenses: 'Gastos',
    payroll: 'Nómina',
    notifications: 'Notificaciones',
    daily_close: 'Cierre diario',
};
const actions: Record<string, string> = {
    access: 'Acceder',
    create: 'Crear',
    update: 'Editar',
    view: 'Ver',
    view_own: 'Ver propios',
    view_all: 'Ver todos',
    cancel: 'Anular',
    reprint: 'Reimprimir',
    assign: 'Asignar personal',
    perform: 'Realizar servicios',
    toggle_status: 'Activar/desactivar',
    assign_role: 'Asignar roles',
    reset_password: 'Restablecer contraseñas',
    delete: 'Eliminar',
    manage_deposit: 'Registrar adelantos',
    resolve_deposit: 'Resolver/devolver adelantos',
    convert_to_sale: 'Completar y cobrar',
    mark_no_show: 'Marcar inasistencia',
    upload_transfer_proof: 'Adjuntar comprobante de transferencia',
    view_transfer_proof: 'Ver comprobante de transferencia',
    view_projection: 'Ver proyecciones',
    manage_categories: 'Administrar categorías',
    configure: 'Configurar',
    generate: 'Generar',
    mark_paid: 'Marcar pagado',
    cancel_obligation: 'Anular obligación',
    send: 'Enviar',
    manage: 'Administrar',
};
export function permissionLabel(permission: string): string {
    if (exact[permission]) return exact[permission];
    const [section, ...rest] = permission.split('.');
    return `${sections[section] ?? section}: ${actions[rest.join('.')] ?? rest.map((p) => actions[p] ?? p).join(' · ')}`;
}

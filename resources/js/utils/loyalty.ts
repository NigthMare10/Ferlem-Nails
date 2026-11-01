export const redemptionLabels: Record<string, string> = {
    scheduled: 'Cita agendada',
    reserved: 'Pendiente de entrega',
    confirmed: 'Entregada',
    cancelled: 'Cancelada',
    expired: 'Vencida',
};
export const redemptionColors: Record<string, string> = {
    reserved: 'warning',
    confirmed: 'success',
    cancelled: 'default',
    expired: 'error',
};
export function loyaltyDate(value: string) {
    return new Intl.DateTimeFormat('es-HN', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'America/Tegucigalpa',
    }).format(new Date(value));
}

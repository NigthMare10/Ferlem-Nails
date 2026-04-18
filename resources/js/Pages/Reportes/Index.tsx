import PageHeader from '@/Components/PageHeader';
import StatCard from '@/Components/StatCard';
import AppShell from '@/shells/AppShell';
import { formatMoney } from '@/shared/formatters';
import { Head } from '@inertiajs/react';

export default function ReportesIndex({ resumen }: { resumen: Record<string, number> }) {
    return (
        <AppShell title="Reportes base" section="reportes">
            <Head title="Reportes" />

            <div className="space-y-8">
                <PageHeader
                    eyebrow="Reportes"
                    title="Indicadores resumidos de la sucursal"
                    description="La primera versión prioriza métricas ligeras y consultas eficientes preparadas para crecer a reportes agregados."
                />

                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Ventas" value={formatMoney(resumen.ventas_hoy)} helper="Acumulado del día" />
                    <StatCard label="Órdenes" value={String(resumen.ordenes_hoy ?? 0)} helper="Operaciones registradas" />
                    <StatCard label="Facturadas" value={String(resumen.ordenes_facturadas ?? 0)} helper="Con comprobante emitido" />
                    <StatCard label="Ticket promedio" value={formatMoney(resumen.ticket_promedio)} helper="Media por orden" />
                </div>
            </div>
        </AppShell>
    );
}

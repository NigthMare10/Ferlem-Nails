import PageHeader from '@/Components/PageHeader';
import StatCard from '@/Components/StatCard';
import AppShell from '@/shells/AppShell';
import { formatMoney } from '@/shared/formatters';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Dashboard({ resumen, puedeVerReportes, sesionCajaActiva }: { resumen: Record<string, number> | null; puedeVerReportes: boolean; sesionCajaActiva?: { public_id: string } | null }) {
    const permissions = ((usePage().props as { auth: { user?: { permissions?: string[] } | null } }).auth.user?.permissions ?? []) as string[];
    const canViewClients = permissions.includes('clientes.ver');
    const canViewAgenda = permissions.includes('agenda.ver');
    const canViewInvoices = permissions.includes('facturas.ver');
    const canViewReports = permissions.includes('reportes.ver_sucursal') || permissions.includes('reportes.ver_global');

    return (
        <AppShell title="Panel operativo" section="admin">
            <Head title="Panel" />

            <div className="space-y-8">
                <PageHeader
                    eyebrow="Resumen diario"
                    title="Vista general de FERLEM NAILS"
                    description="Consulta rápidamente el pulso de la sucursal: órdenes, ventas, ticket promedio y estado de caja."
                    actions={
                        <>
                            <Link href="/pos" className="primary-action">Ir al POS</Link>
                            <Link href="/caja" className="secondary-action">Gestionar caja</Link>
                        </>
                    }
                />

                {puedeVerReportes && resumen ? (
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        <StatCard label="Ventas de hoy" value={formatMoney(resumen.ventas_hoy)} helper="Total vendido en la sucursal activa" />
                        <StatCard label="Órdenes de hoy" value={String(resumen.ordenes_hoy ?? 0)} helper="Operaciones registradas hoy" />
                        <StatCard label="Órdenes facturadas" value={String(resumen.ordenes_facturadas ?? 0)} helper="Facturas emitidas correctamente" />
                        <StatCard label="Ticket promedio" value={formatMoney(resumen.ticket_promedio)} helper="Promedio por orden cerrada" />
                    </div>
                ) : (
                    <div className="rounded-3xl border border-stone-200 bg-white/85 p-6 text-sm leading-7 text-stone-600 shadow-[0_20px_60px_rgba(107,72,63,0.08)]">
                        Tu perfil no tiene acceso a métricas financieras ni reportes. Desde aquí puedes continuar con los módulos operativos permitidos en la sucursal activa.
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
                    <div className="card-panel">
                        <h3 className="font-display text-3xl text-stone-900">Prioridades operativas</h3>
                        <div className="mt-6 grid gap-4 md:grid-cols-2">
                            {canViewClients ? <Link href="/clientes" className="rounded-3xl border border-stone-200 p-5 transition hover:border-brand-copper hover:bg-brand-copper/5"><p className="text-xs uppercase tracking-[0.3em] text-stone-500">Clientes</p><h4 className="mt-3 text-2xl font-semibold">Mantén perfiles al día</h4></Link> : null}
                            {canViewAgenda ? <Link href="/agenda" className="rounded-3xl border border-stone-200 p-5 transition hover:border-brand-copper hover:bg-brand-copper/5"><p className="text-xs uppercase tracking-[0.3em] text-stone-500">Agenda</p><h4 className="mt-3 text-2xl font-semibold">Programa citas sin fricción</h4></Link> : null}
                            {canViewInvoices ? <Link href="/facturas" className="rounded-3xl border border-stone-200 p-5 transition hover:border-brand-copper hover:bg-brand-copper/5"><p className="text-xs uppercase tracking-[0.3em] text-stone-500">Facturación</p><h4 className="mt-3 text-2xl font-semibold">Consulta historial en HNL</h4></Link> : null}
                            {canViewReports ? <Link href="/reportes" className="rounded-3xl border border-stone-200 p-5 transition hover:border-brand-copper hover:bg-brand-copper/5"><p className="text-xs uppercase tracking-[0.3em] text-stone-500">Reportes</p><h4 className="mt-3 text-2xl font-semibold">Revisa métricas rápidas</h4></Link> : null}
                        </div>
                    </div>

                    <div className="card-panel">
                        <p className="text-xs uppercase tracking-[0.3em] text-stone-500">Caja</p>
                        <h3 className="mt-3 font-display text-3xl text-stone-900">Estado actual</h3>
                        <p className="mt-4 text-sm text-stone-600">
                            {sesionCajaActiva ? 'Ya existe una sesión de caja abierta para continuar cobrando en el POS.' : 'Aún no hay una sesión de caja abierta para esta sucursal.'}
                        </p>
                        <Link href="/caja" className="primary-action mt-6 w-full justify-center">{sesionCajaActiva ? 'Ver caja activa' : 'Abrir caja'}</Link>
                    </div>
                </div>
            </div>
        </AppShell>
    );
}

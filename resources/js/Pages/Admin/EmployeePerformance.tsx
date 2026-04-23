import { Link } from '@inertiajs/react';

import EmployeePerformanceShell from '@/Components/admin/EmployeePerformanceShell';

type Employee = {
    publicId: string;
    name: string;
    role: string;
    image: string;
    level: string;
    since: string;
    supportingLabel: string;
    overviewUrl: string;
    performanceUrl: string;
    earningsUrl: string;
    historyUrl: string;
    exportUrl: string;
    teamUrl: string;
    dashboardUrl: string;
    metrics: {
        totalRevenue: string;
        revenueDelta: string;
        serviceTime: string;
        averageTicket: string;
        invoiceCount: string;
        invoiceCountNote: string;
        servicesCount: string;
        servicesCountNote: string;
    };
    chart: Array<{ label: string; value: string; height: number }>;
    specialties: Array<{ service: string; share: number }>;
    appointments: Array<{ date: string; service: string; client: string; status: string; revenue: string }>;
    insight: string;
};

export default function EmployeePerformance({ title, employee }: { title: string; employee: Employee }) {
    return (
        <EmployeePerformanceShell title={title} employee={employee} activeKey="performance">
            <section className="flex flex-col justify-between gap-8 md:flex-row md:items-end">
                <div className="flex items-start gap-8">
                    <div className="relative">
                        <img alt={employee.name} className="h-64 w-48 rounded-xl object-cover shadow-lg" src={employee.image} />
                        <div className="absolute -bottom-4 -right-4 flex flex-col items-center justify-center rounded-xl bg-primary p-3 text-white shadow-xl">
                            <span className="text-xs uppercase tracking-tighter">Nivel</span>
                            <span className="text-xl font-serif font-bold">{employee.level}</span>
                        </div>
                    </div>
                    <div className="max-w-md space-y-2 pt-4">
                        <span className="text-xs font-medium uppercase tracking-[0.2em] text-secondary">Equipo de Alto Desempeño</span>
                        <h2 className="text-5xl font-serif font-bold leading-tight tracking-tight text-on-surface">{employee.name}</h2>
                        <p className="text-xl font-serif italic text-outline">{employee.role}</p>
                    </div>
                </div>

                <div className="flex flex-col items-end gap-2 text-right">
                    <span className="text-xs uppercase tracking-widest text-outline">En el equipo desde</span>
                    <span className="text-lg font-serif">{employee.since}</span>
                    <div className="mt-2 text-sm font-semibold text-secondary">{employee.supportingLabel}</div>
                </div>
            </section>

            <section className="mt-12 grid grid-cols-1 gap-6 md:grid-cols-4">
                <div className="flex h-32 flex-col justify-between rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6 shadow-sm">
                    <span className="text-xs font-bold uppercase tracking-widest text-outline">Ingresos Totales</span>
                    <div className="flex items-baseline gap-2"><span className="text-3xl font-serif text-on-surface">{employee.metrics.totalRevenue}</span><span className="text-xs font-bold text-green-600">{employee.metrics.revenueDelta}</span></div>
                </div>
                <div className="flex h-32 flex-col justify-between rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6 shadow-sm">
                    <span className="text-xs font-bold uppercase tracking-widest text-outline">Tiempo por Servicio</span>
                    <div className="flex items-baseline gap-2"><span className="text-3xl font-serif text-on-surface">{employee.metrics.serviceTime}</span><span className="text-xs font-bold text-stone-400">prom.</span></div>
                </div>
                <div className="flex h-32 flex-col justify-between rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6 shadow-sm">
                    <span className="text-xs font-bold uppercase tracking-widest text-outline">Ticket Promedio</span>
                    <div className="flex items-baseline gap-2"><span className="text-3xl font-serif text-on-surface">{employee.metrics.averageTicket}</span></div>
                </div>
                <div className="flex h-32 flex-col justify-between rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6 shadow-sm">
                    <span className="text-xs font-bold uppercase tracking-widest text-outline">Facturas Atendidas</span>
                    <div className="flex items-baseline gap-2"><span className="text-3xl font-serif text-on-surface">{employee.metrics.invoiceCount}</span><span className="text-xs font-medium text-on-surface-variant">{employee.metrics.invoiceCountNote}</span></div>
                </div>
            </section>

            <section className="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-3">
                <div className="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-8 shadow-sm lg:col-span-2">
                    <div className="mb-8 flex items-center justify-between">
                        <div>
                            <h3 className="text-2xl font-serif text-on-surface">Tendencia de Ingresos</h3>
                            <p className="text-sm text-outline">Ingresos proyectados vs reales (mensual)</p>
                        </div>
                        <div className="flex gap-2"><span className="inline-block h-3 w-3 rounded-full bg-primary-container"></span><span className="text-xs text-outline-variant">Este Mes</span></div>
                    </div>
                    <div className="relative flex h-64 items-end justify-between gap-2">
                        <div className="absolute inset-0 flex flex-col justify-between border-b border-outline-variant/20">
                            <div className="w-full border-t border-outline-variant/10"></div>
                            <div className="w-full border-t border-outline-variant/10"></div>
                            <div className="w-full border-t border-outline-variant/10"></div>
                            <div className="w-full border-t border-outline-variant/10"></div>
                        </div>
                        {employee.chart.map((entry, index) => (
                            <div key={entry.label} className={`group flex flex-1 flex-col items-center justify-end gap-2 ${index === 3 ? 'opacity-50' : ''}`}>
                                <div className={`w-12 rounded-t-lg transition-all ${index === 2 ? 'bg-gradient-to-br from-primary to-primary-container shadow-lg' : index === 3 ? 'border-2 border-dashed border-outline-variant/30 bg-surface-container-high' : 'bg-surface-container-high'}`} style={{ height: `${entry.height}%` }}></div>
                                <span className={`text-[10px] font-bold ${index === 2 ? 'text-primary' : 'text-outline'}`}>{entry.label}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex flex-col rounded-xl border border-outline-variant/10 bg-surface-container-low p-8">
                    <h3 className="mb-6 text-2xl font-serif text-on-surface">Especialidades Principales</h3>
                    <div className="flex-1 space-y-6">
                        {employee.specialties.length ? employee.specialties.map((specialty, index) => (
                            <div key={specialty.service} className="space-y-2">
                                <div className="flex justify-between text-sm"><span className="font-bold text-on-surface">{specialty.service}</span><span className="text-outline">{specialty.share}%</span></div>
                                <div className="h-1.5 w-full overflow-hidden rounded-full bg-surface-container-highest"><div className={`h-full ${index === 0 ? 'bg-primary' : index === 1 ? 'bg-primary-container' : 'bg-secondary-container'}`} style={{ width: `${specialty.share}%` }}></div></div>
                            </div>
                        )) : <p className="text-sm text-outline">Sin servicios facturados para este empleado.</p>}
                    </div>
                    <div className="mt-8 border-t border-outline-variant/20 pt-6"><p className="text-xs italic leading-relaxed text-outline">{employee.insight}</p></div>
                </div>
            </section>

            <section className="mt-8 space-y-6">
                <div className="flex items-end justify-between">
                    <div>
                        <h3 className="text-3xl font-serif leading-none text-on-surface">Historial Reciente</h3>
                        <p className="mt-2 text-sm text-outline">Últimos servicios facturados por {employee.name}</p>
                    </div>
                    <Link href={employee.historyUrl} className="text-sm font-bold tracking-tighter text-secondary underline underline-offset-4">Ver Historial Completo</Link>
                </div>

                <div className="overflow-hidden rounded-xl border border-outline-variant/10 bg-surface-container-lowest shadow-sm">
                    <table className="w-full border-collapse text-left">
                        <thead>
                            <tr className="border-b border-outline-variant/10 bg-surface-container-low/50">
                                <th className="px-6 py-4 text-xs font-bold uppercase tracking-widest text-outline">Fecha</th>
                                <th className="px-6 py-4 text-xs font-bold uppercase tracking-widest text-outline">Servicio</th>
                                <th className="px-6 py-4 text-xs font-bold uppercase tracking-widest text-outline">Cliente</th>
                                <th className="px-6 py-4 text-xs font-bold uppercase tracking-widest text-outline">Estado</th>
                                <th className="px-6 py-4 text-right text-xs font-bold uppercase tracking-widest text-outline">Ingresos</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-outline-variant/5">
                            {employee.appointments.length ? employee.appointments.map((appointment) => (
                                <tr key={`${appointment.date}-${appointment.client}`} className="transition-colors hover:bg-surface-container-low">
                                    <td className="px-6 py-5 text-sm font-medium">{appointment.date}</td>
                                    <td className="px-6 py-5 text-lg font-serif">{appointment.service}</td>
                                    <td className="px-6 py-5 text-sm text-on-surface-variant">{appointment.client}</td>
                                    <td className="px-6 py-5"><span className="inline-flex items-center rounded-full bg-secondary-container px-2 py-0.5 text-[10px] font-bold text-on-secondary-container">{appointment.status}</span></td>
                                    <td className="px-6 py-5 text-right text-lg font-serif">{appointment.revenue}</td>
                                </tr>
                            )) : <tr><td colSpan={5} className="px-6 py-8 text-center text-sm text-outline">Sin facturas reales para este empleado.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </section>
        </EmployeePerformanceShell>
    );
}

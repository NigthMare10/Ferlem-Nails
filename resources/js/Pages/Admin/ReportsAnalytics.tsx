import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AdminSettingsMenu from '@/Components/admin/AdminSettingsMenu';
import { buildAdminPrimaryNav } from '@/lib/adminNavigation';

type Dataset = {
    summary: {
        ingresos: string;
        variacionIngresos: string;
        ticketPromedio: string;
        variacionTicket: string;
        citas: string;
        variacionCitas: string;
        categoriaPrincipal: string;
        variacionCategoria: string;
    };
    trend: number[];
    previousTrend: number[];
    servicePopularity: Array<{ name: string; value: number }>;
    staffPerformance: Array<{ employeePublicId: string; name: string; department: string; revenue: string; image: string }>;
    retention: {
        value: string;
        delta: string;
        note: string;
    };
    insight: {
        title: string;
        body: string;
    };
};

type Props = {
    title: string;
    calendarOptions: Array<{ label: string; key: string }>;
    datasets: Record<string, Dataset>;
};

function buildPath(values: number[]) {
    const points = values.map((value, index) => `${index * 333.33},${value}`).join(' ');
    return `M ${points}`;
}

export default function ReportsAnalytics({ title, calendarOptions, datasets }: Props) {
    const [selectedPeriod, setSelectedPeriod] = useState(calendarOptions[0]?.key ?? '');
    const navItems = useMemo(() => buildAdminPrimaryNav('reports'), []);

    const fallbackDataset = useMemo(() => datasets[calendarOptions[0]?.key ?? ''] ?? Object.values(datasets)[0], [calendarOptions, datasets]);
    const currentDataset = datasets[selectedPeriod] ?? fallbackDataset;
    const selectedLabel = calendarOptions.find((option) => option.key === selectedPeriod)?.label ?? calendarOptions[0]?.label ?? 'Abril 2026';

    const chartPrimary = useMemo(() => buildPath(currentDataset?.trend ?? [78, 58, 30, 40]), [currentDataset]);
    const chartSecondary = useMemo(() => buildPath(currentDataset?.previousTrend ?? [90, 80, 60, 70]), [currentDataset]);

    const cyclePeriod = () => {
        if (calendarOptions.length < 2) {
            return;
        }

        const currentIndex = calendarOptions.findIndex((option) => option.key === selectedPeriod);
        const nextIndex = currentIndex === -1 ? 0 : (currentIndex + 1) % calendarOptions.length;
        setSelectedPeriod(calendarOptions[nextIndex].key);
    };

    return (
        <div className="flex min-h-screen overflow-x-hidden bg-background text-on-background antialiased">
            <Head title={title} />

            <aside className="fixed left-0 top-0 z-50 hidden h-full w-64 flex-col bg-[#f6f3f2] px-6 py-8 md:flex">
                <div className="mb-12">
                    <h1 className="font-serif text-xl text-stone-800">FERLEM NAILS</h1>
                    <p className="mt-1 font-label text-[10px] uppercase tracking-widest text-stone-500">Operación Profesional</p>
                </div>

                <nav className="flex-1 space-y-6">
                    <div className="space-y-4">
                        {navItems.map((item) => (
                            <Link
                                key={item.label}
                                href={item.href}
                                className={`group flex items-center gap-3 transition-all duration-300 ease-in-out ${item.active ? 'border-r-2 border-[#7d562d] font-bold text-[#7d562d]' : 'text-stone-500 hover:text-stone-900'}`}
                            >
                                <span className="material-symbols-outlined text-xl">{item.icon}</span>
                                <span className="font-label text-xs uppercase tracking-widest">{item.label}</span>
                            </Link>
                        ))}
                    </div>

                    <div className="pt-8">
                        <Link href="/inicio-de-cobro" className="block w-full rounded-md bg-gradient-to-br from-primary to-primary-container py-3 text-center font-label text-[10px] uppercase tracking-[0.2em] text-white shadow-sm transition-opacity hover:opacity-90">
                            Nueva Cita
                        </Link>
                    </div>
                </nav>

                <div className="mt-auto pt-8">
                    <AdminSettingsMenu showSettingsLink />
                </div>
            </aside>

            <main className="min-h-screen flex-1 md:ml-64">
                <header className="sticky top-0 z-40 flex w-full items-center justify-between bg-[#fcf9f8] px-6 py-4 md:px-8">
                    <h2 className="text-2xl font-serif italic text-stone-900">Reportes de Ventas Analytics</h2>

                    <div className="flex items-center gap-4 md:gap-6">
                        <button type="button" onClick={cyclePeriod} className="flex items-center gap-2 text-stone-500 transition-colors hover:text-stone-900">
                            <span className="font-label text-xs tracking-widest">{selectedLabel.toUpperCase()}</span>
                            <span className="material-symbols-outlined text-sm">calendar_today</span>
                        </button>
                        <button type="button" className="material-symbols-outlined rounded-full p-2 transition-colors hover:bg-[#f6f3f2]">notifications</button>
                        <AdminSettingsMenu iconOnly showSettingsLink />
                    </div>
                </header>

                <div className="mx-auto max-w-7xl space-y-12 p-6 md:p-8">
                    <section className="grid grid-cols-1 gap-6 md:grid-cols-4">
                        <div className="group relative overflow-hidden rounded-xl bg-surface-container-lowest p-8">
                            <div className="absolute right-0 top-0 p-4 opacity-10"><span className="material-symbols-outlined text-6xl">payments</span></div>
                            <p className="mb-2 font-label text-[10px] uppercase tracking-widest text-secondary">Ingresos Totales</p>
                            <h3 className="text-4xl font-headline tracking-tighter text-on-surface">{currentDataset.summary.ingresos}</h3>
                            <div className="mt-4 flex items-center gap-1 text-xs font-medium text-green-600"><span className="material-symbols-outlined text-sm">trending_up</span><span>{currentDataset.summary.variacionIngresos}</span></div>
                        </div>
                        <div className="group relative rounded-xl bg-surface-container-low p-8">
                            <p className="mb-2 font-label text-[10px] uppercase tracking-widest text-secondary">Ticket Promedio</p>
                            <h3 className="text-4xl font-headline tracking-tighter text-on-surface">{currentDataset.summary.ticketPromedio}</h3>
                            <div className="mt-4 flex items-center gap-1 text-xs font-medium text-stone-500"><span className="material-symbols-outlined text-sm">remove</span><span>{currentDataset.summary.variacionTicket}</span></div>
                        </div>
                        <div className="group relative overflow-hidden rounded-xl bg-surface-container-lowest p-8">
                            <p className="mb-2 font-label text-[10px] uppercase tracking-widest text-secondary">Citas</p>
                            <h3 className="text-4xl font-headline tracking-tighter text-on-surface">{currentDataset.summary.citas}</h3>
                            <div className="mt-4 flex items-center gap-1 text-xs font-medium text-green-600"><span className="material-symbols-outlined text-sm">trending_up</span><span>{currentDataset.summary.variacionCitas}</span></div>
                        </div>
                        <div className="group relative rounded-xl bg-surface-container-low p-8">
                            <p className="mb-2 font-label text-[10px] uppercase tracking-widest text-secondary">Categoría Principal</p>
                            <h3 className="text-4xl font-headline tracking-tighter text-on-surface">{currentDataset.summary.categoriaPrincipal}</h3>
                            <div className="mt-4 flex items-center gap-1 text-xs font-medium text-stone-500"><span className="material-symbols-outlined text-sm">stars</span><span>{currentDataset.summary.variacionCategoria}</span></div>
                        </div>
                    </section>

                    <section className="rounded-xl bg-surface-container-lowest p-8 md:p-10">
                        <div className="mb-12 flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                            <div>
                                <h2 className="mb-2 text-3xl font-headline">Tendencia de Ingresos</h2>
                                <p className="max-w-md font-body text-sm text-stone-500">Comparativa diaria de ingresos brutos entre periodos para el ciclo operativo actual.</p>
                            </div>
                            <div className="flex gap-4">
                                <div className="flex items-center gap-2 text-xs font-label"><span className="h-3 w-3 rounded-full bg-primary"></span><span>Periodo actual</span></div>
                                <div className="flex items-center gap-2 text-xs font-label"><span className="h-3 w-3 rounded-full bg-outline-variant"></span><span>Periodo base</span></div>
                            </div>
                        </div>
                        <div className="relative h-64 w-full border-b border-surface-container-highest pb-2">
                            <svg className="h-full w-full opacity-70" preserveAspectRatio="none" viewBox="0 0 1000 100">
                                <path d={chartPrimary} fill="transparent" stroke="#7d562d" strokeWidth="2"></path>
                                <path d={chartSecondary} fill="transparent" stroke="#d4c2c3" strokeWidth="1.5"></path>
                            </svg>
                            <div className="flex w-full justify-between pt-4 font-label text-[10px] uppercase tracking-widest text-stone-400"><span>Semana 01</span><span>Semana 02</span><span>Semana 03</span><span>Semana 04</span></div>
                        </div>
                    </section>

                    <div className="grid grid-cols-1 gap-12 pt-4 lg:grid-cols-2">
                        <section>
                            <div className="mb-8 flex items-center gap-4"><h2 className="text-2xl font-headline">Popularidad de Servicios</h2><div className="h-px flex-1 bg-surface-container-highest"></div></div>
                            <div className="space-y-8">
                                {currentDataset.servicePopularity.map((service) => (
                                    <div key={service.name} className="group">
                                        <div className="mb-3 flex items-end justify-between"><span className="font-body font-semibold text-on-surface">{service.name}</span><span className="font-label text-xs text-stone-500">{service.value}%</span></div>
                                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-surface-container-high"><div className="h-full bg-primary transition-all duration-700" style={{ width: `${service.value}%` }}></div></div>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section>
                            <div className="mb-8 flex items-center gap-4"><h2 className="text-2xl font-headline">Rendimiento del Personal</h2><div className="h-px flex-1 bg-surface-container-highest"></div></div>
                            <div className="overflow-hidden rounded-xl bg-surface-container-lowest">
                                <table className="w-full border-collapse text-left">
                                    <thead>
                                        <tr className="border-b border-surface-container-low">
                                            <th className="px-6 py-4 font-label text-[10px] uppercase tracking-widest text-stone-500">Especialista</th>
                                            <th className="px-6 py-4 font-label text-[10px] uppercase tracking-widest text-stone-500">Área</th>
                                            <th className="px-6 py-4 text-right font-label text-[10px] uppercase tracking-widest text-stone-500">Ingresos</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-surface-container-low">
                                        {currentDataset.staffPerformance.map((row) => (
                                            <tr key={row.employeePublicId} className="group transition-colors hover:bg-surface-container-low">
                                                <td className="px-6 py-4">
                                                    <Link href={`/rendimiento-por-empleado/${row.employeePublicId}`} className="flex items-center gap-3">
                                                        <img alt={row.name} className="h-10 w-10 rounded-full object-cover grayscale transition-all group-hover:grayscale-0" src={row.image} />
                                                        <span className="font-body text-sm font-semibold">{row.name}</span>
                                                    </Link>
                                                </td>
                                                <td className="px-6 py-4 text-xs font-body text-stone-500">{row.department}</td>
                                                <td className="px-6 py-4 text-right text-sm font-headline">{row.revenue}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>

                    <section className="grid grid-cols-1 gap-8 pt-8 md:grid-cols-3">
                        <div className="md:col-span-2 flex flex-col items-center gap-10 rounded-xl bg-inverse-on-surface p-10 md:flex-row">
                            <div className="flex-1">
                                <span className="mb-6 inline-block rounded-full bg-secondary-container px-3 py-1 font-label text-[10px] uppercase tracking-widest text-on-secondary-container">Insight Estratégico</span>
                                <h2 className="mb-4 text-4xl font-headline italic leading-tight text-primary">{currentDataset.insight.title}</h2>
                                <p className="mb-6 font-body text-sm leading-relaxed text-stone-600">{currentDataset.insight.body}</p>
                                <Link href="/reportes-de-ventas-analytics" className="group flex items-center gap-2 font-label text-xs uppercase tracking-widest text-secondary">
                                    Ver Reporte Completo
                                    <span className="material-symbols-outlined text-sm transition-transform group-hover:translate-x-1">arrow_forward</span>
                                </Link>
                            </div>
                            <div className="aspect-[3/4] w-full overflow-hidden rounded-xl shadow-2xl md:w-1/3">
                                <img className="h-full w-full object-cover" alt="Insight visual" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAnBL6EIZcQWcwrVUji0Drgyb9k0jy8B6xVsj5HBQ5YabweTruhJEX0gYlhw82h4m5IJ5NNxkaMpfq-6SLRsORYzBNlk-57FjFTIKHRlzYbfL4glcmi84XrxMnCpYZUNaIcAEWVtlhntEgsRr8n477bXvOLKoVCYFCe4GAD-sq34sVvJHa-fPIYmqXN5caFlcifc2ScIqgTNiez2dO8JFgJXfnGZXt4N0tXjxV4uUvPlGc1cLc58iDDW9CP4GkVAHu_640qQfX5eIsj" />
                            </div>
                        </div>
                        <div className="flex flex-col justify-between rounded-xl bg-surface-container-highest p-8">
                            <div>
                                <h4 className="mb-4 text-xl font-headline">Tasa de Retención</h4>
                                <div className="flex items-baseline gap-2">
                                    <span className="text-5xl font-headline text-on-surface">{currentDataset.retention.value}</span>
                                    <span className={`font-label text-xs ${currentDataset.retention.delta.startsWith('-') ? 'text-rose-600' : 'text-green-600'}`}>{currentDataset.retention.delta}</span>
                                </div>
                            </div>
                            <div className="mt-8 space-y-4">
                                <p className="font-body text-xs italic text-stone-500">&quot;{currentDataset.retention.note}&quot;</p>
                                <div className="flex -space-x-3 overflow-hidden"><div className="h-8 w-8 rounded-full bg-primary-container ring-2 ring-background"></div><div className="h-8 w-8 rounded-full bg-secondary-container ring-2 ring-background"></div><div className="h-8 w-8 rounded-full bg-tertiary-container ring-2 ring-background"></div><div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary-container ring-2 ring-background text-[10px] font-bold text-on-primary-container">+48</div></div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    );
}

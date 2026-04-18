import { Head, Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

import AdminSettingsMenu from '@/Components/admin/AdminSettingsMenu';

type EmployeeProfile = {
    name: string;
    role: string;
    image: string;
    exportUrl: string;
    overviewUrl: string;
    performanceUrl: string;
    earningsUrl: string;
    historyUrl: string;
    teamUrl: string;
    dashboardUrl: string;
};

type Props = PropsWithChildren<{
    title: string;
    employee: EmployeeProfile;
    activeKey: 'performance' | 'earnings';
}>;

export default function EmployeePerformanceShell({ title, employee, activeKey, children }: Props) {
    return (
        <div className="flex min-h-screen bg-background text-on-background">
            <Head title={title} />

            <aside className="sticky left-0 top-0 z-50 flex h-screen w-64 flex-col border-r border-stone-200/20 bg-[#f6f3f2] px-4 py-8">
                <div className="mb-10 px-4">
                    <h1 className="font-serif text-lg italic text-[#1b1c1c]">FERLEM NAILS</h1>
                </div>

                <div className="mb-4 px-4">
                    <p className="text-xs font-bold uppercase tracking-widest text-stone-400">Suite de Empleados</p>
                </div>

                <div className="flex flex-1 flex-col gap-1">
                    <Link href={employee.overviewUrl} className="flex items-center gap-3 px-4 py-3 text-stone-600 transition-all duration-200 hover:bg-[#ffffff]/50">
                        <span className="material-symbols-outlined">analytics</span>
                        <span className="text-sm tracking-tight">Resumen</span>
                    </Link>
                    <Link href={employee.performanceUrl} className={`flex items-center gap-3 rounded-md px-4 py-3 ${activeKey === 'performance' ? 'bg-[#ffffff] font-bold text-[#7d562d] shadow-sm' : 'text-stone-600 hover:bg-[#ffffff]/50'}`}>
                        <span className="material-symbols-outlined">trending_up</span>
                        <span className="text-sm tracking-tight">Rendimiento</span>
                    </Link>
                    <Link href={employee.earningsUrl} className={`flex items-center gap-3 rounded-md px-4 py-3 ${activeKey === 'earnings' ? 'bg-[#ffffff] font-bold text-[#7d562d] shadow-sm' : 'text-stone-600 hover:bg-[#ffffff]/50'}`}>
                        <span className="material-symbols-outlined">payments</span>
                        <span className="text-sm tracking-tight">Ganancias</span>
                    </Link>
                </div>

                <div className="mt-auto space-y-4 border-t border-stone-200/20 px-4 py-6">
                    <Link href={employee.exportUrl} className="block w-full rounded-md bg-gradient-to-br from-primary to-primary-container px-4 py-3 text-center text-sm font-medium text-white transition-opacity active:opacity-70">
                        Exportar Reporte
                    </Link>
                    <AdminSettingsMenu showSettingsLink />
                </div>
            </aside>

            <main className="min-w-0 flex-1">
                <header className="sticky top-0 z-40 mx-auto flex w-full max-w-screen-2xl items-center justify-between bg-[#fcf9f8] px-8 py-4">
                    <div className="hidden items-center gap-6 lg:flex">
                        <Link href={employee.dashboardUrl} className="font-serif text-stone-500 transition-colors duration-300 hover:text-[#7d562d]">Panel</Link>
                        <Link href={employee.teamUrl} className="font-serif text-stone-500 transition-colors duration-300 hover:text-[#7d562d]">Equipo</Link>
                    </div>
                    <div className="ml-auto flex items-center gap-4">
                        <button type="button" className="rounded-full p-2 transition-colors duration-300 hover:bg-[#f6f3f2]"><span className="material-symbols-outlined text-stone-600">notifications</span></button>
                        <AdminSettingsMenu iconOnly showSettingsLink />
                        <img alt={employee.name} className="h-8 w-8 rounded-full border border-outline-variant/20 object-cover" src={employee.image} />
                    </div>
                </header>

                <div className="mx-auto max-w-screen-xl p-8">{children}</div>
            </main>
        </div>
    );
}

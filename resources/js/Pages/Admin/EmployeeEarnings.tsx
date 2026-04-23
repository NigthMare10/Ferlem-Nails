import EmployeePerformanceShell from '@/Components/admin/EmployeePerformanceShell';

type Employee = {
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
    chart: Array<{ label: string; value: string; height: number }>;
    earningsBreakdown: Array<{ label: string; value: string }>;
    metrics: { totalRevenue: string; revenueDelta: string };
};

export default function EmployeeEarnings({ title, employee }: { title: string; employee: Employee }) {
    return (
        <EmployeePerformanceShell title={title} employee={employee} activeKey="earnings">
            <section className="mb-10 flex items-end justify-between">
                <div>
                    <span className="mb-3 block text-xs uppercase tracking-[0.2em] text-primary">Lectura Financiera</span>
                    <h1 className="text-5xl font-serif leading-tight text-on-background">Ganancias de {employee.name}</h1>
                    <p className="mt-3 max-w-2xl text-sm text-stone-500">Vista consolidada para revisar el rendimiento económico del especialista sin salir del lenguaje visual Stitch.</p>
                </div>
                <div className="text-right">
                    <p className="text-xs uppercase tracking-widest text-stone-500">Acumulado del periodo</p>
                    <p className="text-4xl font-serif text-primary">{employee.metrics.totalRevenue}</p>
                    <p className="text-xs font-bold text-green-600">{employee.metrics.revenueDelta}</p>
                </div>
            </section>

            <section className="grid grid-cols-1 gap-8 lg:grid-cols-[1.2fr_0.8fr]">
                <div className="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-8 shadow-sm">
                    <div className="mb-8">
                        <h2 className="text-2xl font-serif text-on-background">Curva Semanal</h2>
                        <p className="mt-2 text-sm text-stone-500">Comparativo visual del ingreso producido por semana en lempiras.</p>
                    </div>
                    <div className="grid h-80 grid-cols-4 items-end gap-4">
                        {employee.chart.map((entry, index) => (
                            <div key={entry.label} className="flex flex-col items-center gap-3">
                                <div className={`w-full rounded-t-xl ${index === 2 ? 'bg-gradient-to-br from-primary to-primary-container' : 'bg-surface-container-high'}`} style={{ height: `${entry.height}%`, minHeight: '5rem' }}></div>
                                <div className="text-center">
                                    <p className="text-[10px] font-bold uppercase tracking-widest text-stone-500">{entry.label}</p>
                                    <p className="mt-1 text-sm font-semibold text-on-background">{entry.value}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="rounded-xl border border-outline-variant/10 bg-surface-container-low p-8 shadow-sm">
                    <div className="mb-8">
                        <h2 className="text-2xl font-serif text-on-background">Desglose de Ganancias</h2>
                        <p className="mt-2 text-sm text-stone-500">Origen principal de facturación del periodo actual.</p>
                    </div>
                    <div className="space-y-4">
                        {employee.earningsBreakdown.length ? employee.earningsBreakdown.map((item, index) => (
                            <div key={item.label} className={`rounded-xl p-5 ${index === 0 ? 'bg-white' : 'bg-white/70'}`}>
                                <p className="text-[10px] uppercase tracking-widest text-stone-500">{item.label}</p>
                                <p className="mt-2 text-2xl font-serif text-primary">{item.value}</p>
                            </div>
                        )) : <div className="rounded-xl bg-white/70 p-5 text-sm text-stone-500">Sin desglose real disponible para este empleado.</div>}
                    </div>
                </div>
            </section>
        </EmployeePerformanceShell>
    );
}

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
    history: Array<{ folio: string; date: string; service: string; client: string; status: string; revenue: string; paymentMethod: string }>;
};

export default function EmployeeHistory({ title, employee }: { title: string; employee: Employee }) {
    return (
        <EmployeePerformanceShell title={title} employee={employee} activeKey="performance">
            <section className="mb-10 flex items-end justify-between">
                <div>
                    <span className="mb-3 block text-xs uppercase tracking-[0.2em] text-primary">Historial Operativo</span>
                    <h1 className="text-5xl font-serif leading-tight text-on-background">Historial Completo de {employee.name}</h1>
                    <p className="mt-3 max-w-2xl text-sm text-stone-500">Registro extendido de servicios, cobros y método de pago del perfil seleccionado.</p>
                </div>
            </section>

            <section className="overflow-hidden rounded-xl border border-outline-variant/10 bg-surface-container-lowest shadow-sm">
                <table className="w-full border-collapse text-left">
                    <thead>
                        <tr className="border-b border-outline-variant/10 bg-surface-container-low/50">
                            <th className="px-6 py-4 text-xs font-bold uppercase tracking-widest text-outline">Folio</th>
                            <th className="px-6 py-4 text-xs font-bold uppercase tracking-widest text-outline">Fecha</th>
                            <th className="px-6 py-4 text-xs font-bold uppercase tracking-widest text-outline">Servicio</th>
                            <th className="px-6 py-4 text-xs font-bold uppercase tracking-widest text-outline">Cliente</th>
                            <th className="px-6 py-4 text-xs font-bold uppercase tracking-widest text-outline">Pago</th>
                            <th className="px-6 py-4 text-xs font-bold uppercase tracking-widest text-outline">Estado</th>
                            <th className="px-6 py-4 text-right text-xs font-bold uppercase tracking-widest text-outline">Monto</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-outline-variant/5">
                        {employee.history.map((entry) => (
                            <tr key={entry.folio} className="transition-colors hover:bg-surface-container-low">
                                <td className="px-6 py-5 text-sm font-semibold text-on-background">{entry.folio}</td>
                                <td className="px-6 py-5 text-sm">{entry.date}</td>
                                <td className="px-6 py-5 text-lg font-serif">{entry.service}</td>
                                <td className="px-6 py-5 text-sm text-on-surface-variant">{entry.client}</td>
                                <td className="px-6 py-5 text-sm text-on-surface-variant">{entry.paymentMethod}</td>
                                <td className="px-6 py-5"><span className="inline-flex items-center rounded-full bg-secondary-container px-2 py-0.5 text-[10px] font-bold text-on-secondary-container">{entry.status}</span></td>
                                <td className="px-6 py-5 text-right text-lg font-serif">{entry.revenue}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </EmployeePerformanceShell>
    );
}

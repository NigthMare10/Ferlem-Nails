import PageHeader from '@/Components/PageHeader';
import AppShell from '@/shells/AppShell';
import { formatDateTime } from '@/shared/formatters';
import { Head, Link } from '@inertiajs/react';

export default function FacturaShow({ factura }: { factura: { data: { number: string; status: string; cliente: string; issued_at: string; total_formatted: string; items: Array<{ description: string; quantity: number; total_formatted: string }> } } }) {
    const current = factura.data;

    return (
        <AppShell title={`Factura ${current.number}`}>
            <Head title={`Factura ${current.number}`} />

            <div className="space-y-8">
                <PageHeader
                    eyebrow="Factura"
                    title={current.number}
                    description={`Emitida para ${current.cliente} el ${formatDateTime(current.issued_at)}.`}
                    actions={<Link href={route('facturas.index')} className="secondary-action">Volver al historial</Link>}
                />

                <div className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                    <div className="card-panel overflow-hidden">
                        <table className="min-w-full divide-y divide-stone-200 text-left text-sm">
                            <thead>
                                <tr className="text-xs uppercase tracking-[0.28em] text-stone-500">
                                    <th className="px-4 py-4">Descripción</th>
                                    <th className="px-4 py-4">Cantidad</th>
                                    <th className="px-4 py-4">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-stone-100">
                                {current.items.map((item, index) => (
                                    <tr key={`${item.description}-${index}`}>
                                        <td className="px-4 py-4 text-stone-900">{item.description}</td>
                                        <td className="px-4 py-4 text-stone-600">{item.quantity}</td>
                                        <td className="px-4 py-4 text-stone-900">{item.total_formatted}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="card-panel">
                        <p className="text-xs uppercase tracking-[0.3em] text-stone-500">Resumen</p>
                        <h2 className="mt-3 font-display text-3xl text-stone-900">{current.cliente}</h2>
                        <p className="mt-2 text-sm text-stone-600">Estado: {current.status}</p>
                        <div className="mt-6 rounded-3xl bg-stone-50 p-4">
                            <p className="text-xs uppercase tracking-[0.3em] text-stone-500">Total facturado</p>
                            <p className="mt-3 font-display text-5xl text-stone-900">{current.total_formatted}</p>
                        </div>
                    </div>
                </div>
            </div>
        </AppShell>
    );
}

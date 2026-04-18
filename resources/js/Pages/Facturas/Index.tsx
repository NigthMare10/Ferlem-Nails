import PageHeader from '@/Components/PageHeader';
import AppShell from '@/shells/AppShell';
import { formatDateTime } from '@/shared/formatters';
import { Head, Link } from '@inertiajs/react';

export default function FacturasIndex({ facturas }: { facturas: { data: Array<{ public_id: string; number: string; cliente: string; status: string; issued_at: string; total_formatted: string }> } }) {
    return (
        <AppShell title="Historial de facturas">
            <Head title="Facturas" />

            <div className="space-y-8">
                <PageHeader
                    eyebrow="Facturación"
                    title="Historial de comprobantes emitidos"
                    description="Las facturas del MVP quedan asociadas obligatoriamente al cliente y a la orden que originó el cobro."
                />

                <div className="card-panel overflow-hidden">
                    <table className="min-w-full divide-y divide-stone-200 text-left text-sm">
                        <thead>
                            <tr className="text-xs uppercase tracking-[0.28em] text-stone-500">
                                <th className="px-4 py-4">Número</th>
                                <th className="px-4 py-4">Cliente</th>
                                <th className="px-4 py-4">Fecha</th>
                                <th className="px-4 py-4">Estado</th>
                                <th className="px-4 py-4">Total</th>
                                <th className="px-4 py-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-stone-100">
                            {facturas.data.map((invoice) => (
                                <tr key={invoice.public_id}>
                                    <td className="px-4 py-4 font-medium text-stone-900">{invoice.number}</td>
                                    <td className="px-4 py-4 text-stone-600">{invoice.cliente}</td>
                                    <td className="px-4 py-4 text-stone-600">{formatDateTime(invoice.issued_at)}</td>
                                    <td className="px-4 py-4 text-stone-600">{invoice.status}</td>
                                    <td className="px-4 py-4 text-stone-900">{invoice.total_formatted}</td>
                                    <td className="px-4 py-4 text-right"><Link href={route('facturas.show', invoice.public_id)} className="text-sm font-semibold text-brand-copper hover:underline">Ver detalle</Link></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppShell>
    );
}

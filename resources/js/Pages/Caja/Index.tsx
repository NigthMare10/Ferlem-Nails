import PageHeader from '@/Components/PageHeader';
import AppShell from '@/shells/AppShell';
import { formatMoney, formatDateTime } from '@/shared/formatters';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function CajaIndex({ sesionActiva, sesionesRecientes }: { sesionActiva?: { public_id: string; opening_amount: number; expected_amount: number; opened_at: string } | null; sesionesRecientes: Array<{ public_id: string; status: string; opening_amount: number; expected_amount: number; opened_at: string; closed_at?: string | null }> }) {
    const openForm = useForm({ opening_amount: 0, notes: '' });
    const closeForm = useForm({ counted_amount: sesionActiva?.expected_amount ?? 0, notes: '' });

    return (
        <AppShell title="Caja base">
            <Head title="Caja" />

            <div className="space-y-8">
                <PageHeader
                    eyebrow="Caja"
                    title="Apertura, sesión y cierre"
                    description="El cierre de caja está protegido con reautenticación administrativa y registra auditoría de la acción sensible."
                />

                <div className="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                    <div className="card-panel space-y-5">
                        {!sesionActiva ? (
                            <form onSubmit={(event: FormEvent<HTMLFormElement>) => { event.preventDefault(); openForm.post(route('caja.open')); }} className="space-y-4">
                                <h2 className="font-display text-3xl">Abrir caja</h2>
                                <input className="field" type="number" min={0} value={openForm.data.opening_amount} onChange={(event) => openForm.setData('opening_amount', Number(event.target.value))} />
                                <textarea className="field min-h-24" placeholder="Observaciones de apertura" value={openForm.data.notes} onChange={(event) => openForm.setData('notes', event.target.value)} />
                                <button className="primary-action" disabled={openForm.processing}>Abrir sesión</button>
                            </form>
                        ) : (
                            <form onSubmit={(event: FormEvent<HTMLFormElement>) => { event.preventDefault(); closeForm.post(route('caja.close', sesionActiva.public_id)); }} className="space-y-4">
                                <h2 className="font-display text-3xl">Cerrar caja activa</h2>
                                <p className="text-sm text-stone-600">Apertura: {formatMoney(sesionActiva.opening_amount)} · Esperado: {formatMoney(sesionActiva.expected_amount)}</p>
                                <input className="field" type="number" min={0} value={closeForm.data.counted_amount} onChange={(event) => closeForm.setData('counted_amount', Number(event.target.value))} />
                                <textarea className="field min-h-24" placeholder="Observaciones del cierre" value={closeForm.data.notes} onChange={(event) => closeForm.setData('notes', event.target.value)} />
                                <button className="primary-action" disabled={closeForm.processing}>Cerrar con confirmación administrativa</button>
                            </form>
                        )}
                    </div>

                    <div className="card-panel overflow-hidden">
                        <h2 className="font-display text-3xl text-stone-900">Sesiones recientes</h2>
                        <table className="mt-6 min-w-full divide-y divide-stone-200 text-left text-sm">
                            <thead>
                                <tr className="text-xs uppercase tracking-[0.28em] text-stone-500">
                                    <th className="px-4 py-4">Estado</th>
                                    <th className="px-4 py-4">Apertura</th>
                                    <th className="px-4 py-4">Esperado</th>
                                    <th className="px-4 py-4">Fecha</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-stone-100">
                                {sesionesRecientes.map((session) => (
                                    <tr key={session.public_id}>
                                        <td className="px-4 py-4 font-medium text-stone-900">{session.status}</td>
                                        <td className="px-4 py-4 text-stone-600">{formatMoney(session.opening_amount)}</td>
                                        <td className="px-4 py-4 text-stone-600">{formatMoney(session.expected_amount)}</td>
                                        <td className="px-4 py-4 text-stone-600">{formatDateTime(session.opened_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppShell>
    );
}

import PageHeader from '@/Components/PageHeader';
import AppShell from '@/shells/AppShell';
import { formatDateTime } from '@/shared/formatters';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function AgendaIndex({ citas, clientes, servicios, empleados }: { citas: { data: Array<{ public_id: string; cliente: string; servicio: string; empleado?: string; scheduled_start: string; status: string }> }; clientes: Array<{ public_id: string; name: string }>; servicios: Array<{ public_id: string; name: string }>; empleados: Array<{ public_id: string; name: string }> }) {
    const { data, setData, post, processing, errors } = useForm({
        cliente_public_id: '',
        servicio_public_id: '',
        empleado_public_id: '',
        scheduled_start: '',
        scheduled_end: '',
        notes: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(route('agenda.store'));
    };

    return (
        <AppShell title="Agenda base">
            <Head title="Agenda" />

            <div className="space-y-8">
                <PageHeader
                    eyebrow="Agenda"
                    title="Programación de citas"
                    description="Base operativa para registrar citas vinculadas a cliente, servicio, especialista y sucursal activa."
                />

                <div className="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                    <form onSubmit={submit} className="card-panel space-y-4">
                        <h2 className="font-display text-3xl text-stone-900">Nueva cita</h2>
                        <select className="field" value={data.cliente_public_id} onChange={(event) => setData('cliente_public_id', event.target.value)}>
                            <option value="">Selecciona un cliente</option>
                            {clientes.map((client) => <option key={client.public_id} value={client.public_id}>{client.name}</option>)}
                        </select>
                        {errors.cliente_public_id ? <p className="text-sm text-rose-600">{errors.cliente_public_id}</p> : null}
                        <select className="field" value={data.servicio_public_id} onChange={(event) => setData('servicio_public_id', event.target.value)}>
                            <option value="">Selecciona un servicio</option>
                            {servicios.map((service) => <option key={service.public_id} value={service.public_id}>{service.name}</option>)}
                        </select>
                        <select className="field" value={data.empleado_public_id} onChange={(event) => setData('empleado_public_id', event.target.value)}>
                            <option value="">Especialista opcional</option>
                            {empleados.map((employee) => <option key={employee.public_id} value={employee.public_id}>{employee.name}</option>)}
                        </select>
                        <div className="grid gap-4 md:grid-cols-2">
                            <input className="field" type="datetime-local" value={data.scheduled_start} onChange={(event) => setData('scheduled_start', event.target.value)} />
                            <input className="field" type="datetime-local" value={data.scheduled_end} onChange={(event) => setData('scheduled_end', event.target.value)} />
                        </div>
                        <textarea className="field min-h-24" placeholder="Notas de la cita" value={data.notes} onChange={(event) => setData('notes', event.target.value)} />
                        <button className="primary-action" disabled={processing}>{processing ? 'Guardando...' : 'Registrar cita'}</button>
                    </form>

                    <div className="card-panel overflow-hidden">
                        <h2 className="font-display text-3xl text-stone-900">Citas recientes</h2>
                        <table className="mt-6 min-w-full divide-y divide-stone-200 text-left text-sm">
                            <thead>
                                <tr className="text-xs uppercase tracking-[0.28em] text-stone-500">
                                    <th className="px-4 py-4">Cliente</th>
                                    <th className="px-4 py-4">Servicio</th>
                                    <th className="px-4 py-4">Fecha</th>
                                    <th className="px-4 py-4">Estado</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-stone-100">
                                {citas.data.map((appointment) => (
                                    <tr key={appointment.public_id}>
                                        <td className="px-4 py-4 font-medium text-stone-900">{appointment.cliente}</td>
                                        <td className="px-4 py-4 text-stone-600">{appointment.servicio}</td>
                                        <td className="px-4 py-4 text-stone-600">{formatDateTime(appointment.scheduled_start)}</td>
                                        <td className="px-4 py-4 text-stone-600">{appointment.status}</td>
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

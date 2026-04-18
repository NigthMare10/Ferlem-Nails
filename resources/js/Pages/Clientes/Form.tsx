import PageHeader from '@/Components/PageHeader';
import AppShell from '@/shells/AppShell';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Client = {
    data?: {
        public_id: string;
        name: string;
        phone?: string;
        email?: string;
        rtn?: string;
        notes?: string;
        is_active: boolean;
        perfil?: {
            alias?: string;
            alertas?: string;
            preferencias?: string;
        } | null;
    };
};

export default function ClienteForm({ cliente, modo }: { cliente: Client | null; modo: 'crear' | 'editar' }) {
    const current = cliente?.data;
    const { data, setData, post, put, processing, errors } = useForm({
        name: current?.name ?? '',
        phone: current?.phone ?? '',
        email: current?.email ?? '',
        rtn: current?.rtn ?? '',
        notes: current?.notes ?? '',
        alias: current?.perfil?.alias ?? '',
        alertas: current?.perfil?.alertas ?? '',
        preferencias: current?.perfil?.preferencias ?? '',
        is_active: current?.is_active ?? true,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (modo === 'crear') {
            post(route('clientes.store'));
            return;
        }

        put(route('clientes.update', current?.public_id));
    };

    return (
        <AppShell title={modo === 'crear' ? 'Nuevo cliente' : 'Editar cliente'}>
            <Head title={modo === 'crear' ? 'Nuevo cliente' : 'Editar cliente'} />

            <div className="space-y-8">
                <PageHeader
                    eyebrow="Clientes"
                    title={modo === 'crear' ? 'Registrar perfil de cliente' : 'Actualizar perfil de cliente'}
                    description="Captura la información mínima útil para agenda, POS y facturación, manteniendo el flujo ágil para caja y recepción."
                />

                <form onSubmit={submit} className="card-panel space-y-5">
                    <div className="grid gap-5 md:grid-cols-2">
                        <div>
                            <label className="text-sm font-medium text-stone-700">Nombre completo</label>
                            <input className="field" value={data.name} onChange={(event) => setData('name', event.target.value)} />
                            {errors.name ? <p className="mt-2 text-sm text-rose-600">{errors.name}</p> : null}
                        </div>
                        <div>
                            <label className="text-sm font-medium text-stone-700">Teléfono</label>
                            <input className="field" value={data.phone} onChange={(event) => setData('phone', event.target.value)} />
                        </div>
                        <div>
                            <label className="text-sm font-medium text-stone-700">Correo</label>
                            <input className="field" type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} />
                        </div>
                        <div>
                            <label className="text-sm font-medium text-stone-700">RTN</label>
                            <input className="field" value={data.rtn} onChange={(event) => setData('rtn', event.target.value)} />
                        </div>
                        <div>
                            <label className="text-sm font-medium text-stone-700">Alias operativo</label>
                            <input className="field" value={data.alias} onChange={(event) => setData('alias', event.target.value)} />
                        </div>
                        <div>
                            <label className="text-sm font-medium text-stone-700">Alertas</label>
                            <input className="field" value={data.alertas} onChange={(event) => setData('alertas', event.target.value)} />
                        </div>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-stone-700">Preferencias</label>
                        <textarea className="field min-h-28" value={data.preferencias} onChange={(event) => setData('preferencias', event.target.value)} />
                    </div>
                    <div>
                        <label className="text-sm font-medium text-stone-700">Notas internas</label>
                        <textarea className="field min-h-28" value={data.notes} onChange={(event) => setData('notes', event.target.value)} />
                    </div>
                    <label className="flex items-center gap-2 text-sm text-stone-700">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} className="rounded border-stone-300 text-brand-copper focus:ring-brand-copper" />
                        Cliente activo
                    </label>

                    <div className="flex justify-end">
                        <button className="primary-action" disabled={processing}>{processing ? 'Guardando...' : 'Guardar cliente'}</button>
                    </div>
                </form>
            </div>
        </AppShell>
    );
}

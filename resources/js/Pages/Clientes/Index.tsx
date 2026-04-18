import PageHeader from '@/Components/PageHeader';
import AppShell from '@/shells/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function ClientesIndex({ clientes, filters }: { clientes: { data: Array<{ public_id: string; name: string; phone?: string; email?: string; perfil?: { alias?: string; alertas?: string } | null }> }; filters: { search?: string } }) {
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const target = event.currentTarget as HTMLFormElement;
        const formData = new FormData(target);
        router.get(route('clientes.index'), { search: formData.get('search') }, { preserveState: true, replace: true });
    };

    return (
        <AppShell title="Clientes">
            <Head title="Clientes" />

            <div className="space-y-8">
                <PageHeader
                    eyebrow="Clientes"
                    title="Perfiles operativos antes del cobro"
                    description="Toda venta y factura de FERLEM NAILS debe quedar asociada a un cliente. Este módulo permite buscar, crear y editar perfiles rápidamente."
                    actions={<Link href={route('clientes.create')} className="primary-action">Nuevo cliente</Link>}
                />

                <div className="card-panel">
                    <form onSubmit={submit} className="flex flex-col gap-3 md:flex-row">
                        <input name="search" defaultValue={filters.search} placeholder="Buscar por nombre, teléfono o correo" className="field flex-1" />
                        <button className="secondary-action">Filtrar</button>
                    </form>
                </div>

                <div className="card-panel overflow-hidden">
                    <table className="min-w-full divide-y divide-stone-200 text-left text-sm">
                        <thead>
                            <tr className="text-xs uppercase tracking-[0.28em] text-stone-500">
                                <th className="px-4 py-4">Cliente</th>
                                <th className="px-4 py-4">Contacto</th>
                                <th className="px-4 py-4">Perfil</th>
                                <th className="px-4 py-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-stone-100">
                            {clientes.data.map((client) => (
                                <tr key={client.public_id}>
                                    <td className="px-4 py-4 font-medium text-stone-900">{client.name}</td>
                                    <td className="px-4 py-4 text-stone-600">{client.phone ?? client.email ?? 'Sin dato de contacto'}</td>
                                    <td className="px-4 py-4 text-stone-600">{client.perfil?.alias ?? client.perfil?.alertas ?? 'Perfil básico'}</td>
                                    <td className="px-4 py-4 text-right">
                                        <Link href={route('clientes.edit', client.public_id)} className="text-sm font-semibold text-brand-copper hover:underline">
                                            Editar
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppShell>
    );
}

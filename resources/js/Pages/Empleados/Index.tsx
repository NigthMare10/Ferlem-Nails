import PageHeader from '@/Components/PageHeader';
import AppShell from '@/shells/AppShell';
import { Head } from '@inertiajs/react';

export default function EmpleadosIndex({ empleados }: { empleados: Array<{ public_id: string; name: string; role_title?: string; is_active: boolean }> }) {
    return (
        <AppShell title="Equipo y especialidades">
            <Head title="Empleados" />

            <div className="space-y-8">
                <PageHeader
                    eyebrow="Equipo"
                    title="Empleados activos por sucursal"
                    description="Base operativa para asignar especialistas a servicios, agenda y rendimiento futuro."
                />

                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    {empleados.map((employee) => (
                        <article key={employee.public_id} className="card-panel">
                            <p className="text-xs uppercase tracking-[0.3em] text-stone-500">{employee.is_active ? 'Activo' : 'Inactivo'}</p>
                            <h2 className="mt-3 font-display text-3xl text-stone-900">{employee.name}</h2>
                            <p className="mt-2 text-sm text-stone-600">{employee.role_title ?? 'Sin rol operativo asignado'}</p>
                        </article>
                    ))}
                </div>
            </div>
        </AppShell>
    );
}

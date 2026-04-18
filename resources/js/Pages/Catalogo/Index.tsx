import PageHeader from '@/Components/PageHeader';
import AppShell from '@/shells/AppShell';
import { Head } from '@inertiajs/react';

export default function CatalogoIndex({ servicios }: { servicios: { data: Array<{ public_id: string; name: string; description: string; duration_minutes: number; category: string; price_formatted: string }> } }) {
    return (
        <AppShell title="Catálogo de servicios">
            <Head title="Catálogo de servicios" />

            <div className="space-y-8">
                <PageHeader
                    eyebrow="Catálogo"
                    title="Servicios vigentes para la sucursal"
                    description="Este catálogo base prioriza uñas, pestañas y servicios relacionados preparados para operación real en FERLEM NAILS."
                />

                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    {servicios.data.map((service) => (
                        <article key={service.public_id} className="card-panel">
                            <p className="text-xs uppercase tracking-[0.3em] text-brand-rose">{service.category}</p>
                            <h2 className="mt-3 font-display text-3xl text-stone-900">{service.name}</h2>
                            <p className="mt-3 text-sm leading-7 text-stone-600">{service.description}</p>
                            <div className="mt-6 flex items-center justify-between text-sm">
                                <span className="rounded-full bg-stone-100 px-3 py-1 text-stone-600">{service.duration_minutes} min</span>
                                <span className="font-semibold text-brand-copper">{service.price_formatted}</span>
                            </div>
                        </article>
                    ))}
                </div>
            </div>
        </AppShell>
    );
}

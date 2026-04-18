import { Head, useForm } from '@inertiajs/react';

type Branch = {
    public_id?: string;
    name?: string;
    city?: string;
    data?: {
        public_id: string;
        name: string;
        city?: string;
    };
};

export default function Selector({ sucursales, activa }: { sucursales: { data: Branch[] }; activa?: number | null }) {
    const { data, setData, post, processing } = useForm({
        sucursal_public_id: '',
    });

    return (
        <div className="min-h-screen bg-brand-cream px-4 py-10">
            <Head title="Seleccionar sucursal" />

            <div className="mx-auto max-w-4xl card-panel">
                <p className="text-xs uppercase tracking-[0.35em] text-brand-rose">FERLEM NAILS</p>
                <h1 className="mt-3 font-display text-5xl text-stone-900">Selecciona tu sucursal de trabajo</h1>
                <p className="mt-3 text-sm leading-7 text-stone-600">El sistema necesita un contexto de sucursal activo para limitar clientes, agenda, caja y facturación.</p>

                <div className="mt-8 grid gap-4 md:grid-cols-2">
                    {sucursales.data.map((entry) => {
                        const branch = entry.data ?? entry;
                        const selected = data.sucursal_public_id === branch.public_id;

                        return (
                            <button
                                key={branch.public_id}
                                type="button"
                                onClick={() => setData('sucursal_public_id', branch.public_id ?? '')}
                                className={`rounded-3xl border p-6 text-left transition ${selected ? 'border-brand-copper bg-brand-copper/5 shadow-lg' : 'border-stone-200 bg-white hover:border-brand-gold'}`}
                            >
                                <p className="text-xs uppercase tracking-[0.3em] text-stone-500">Sucursal</p>
                                <h2 className="mt-3 font-display text-3xl text-stone-900">{branch.name}</h2>
                                <p className="mt-2 text-sm text-stone-600">{branch.city ?? 'Ubicación principal'}</p>
                            </button>
                        );
                    })}
                </div>

                <form onSubmit={(event) => { event.preventDefault(); post(route('sucursales.activate')); }} className="mt-8">
                    <button className="primary-action" disabled={processing || !data.sucursal_public_id}>
                        {processing ? 'Activando sucursal...' : 'Entrar a la operación'}
                    </button>
                </form>
            </div>
        </div>
    );
}

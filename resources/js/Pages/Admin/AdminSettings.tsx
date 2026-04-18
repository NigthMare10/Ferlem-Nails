import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo } from 'react';

import AdminSettingsMenu from '@/Components/admin/AdminSettingsMenu';
import { buildAdminPrimaryNav } from '@/lib/adminNavigation';

type Props = {
    title: string;
    branch: {
        name: string;
        code: string;
        currencyCode: string;
        currencySymbol: string;
        taxName: string;
        taxRate: string;
        allowManualPrice: boolean;
        reauthWindowMinutes: number;
    };
};

export default function AdminSettings({ title, branch }: Props) {
    const navItems = useMemo(() => buildAdminPrimaryNav('pricing'), []);
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };

    const { data, setData, put, processing, errors } = useForm({
        taxName: branch.taxName,
        taxRate: branch.taxRate,
        allowManualPrice: branch.allowManualPrice,
        reauthWindowMinutes: String(branch.reauthWindowMinutes),
    });

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        put(route('admin.settings.update'), { preserveScroll: true });
    };

    return (
        <div className="flex min-h-screen bg-[#fcf9f8] text-on-background">
            <Head title={title} />

            <aside className="fixed left-0 top-0 hidden h-full w-64 flex-col bg-[#f6f3f2] px-6 py-8 md:flex">
                <div className="mb-12">
                    <h1 className="font-serif text-xl text-stone-800">FERLEM NAILS</h1>
                    <p className="mt-1 text-[10px] uppercase tracking-widest text-stone-500">Operación Profesional</p>
                </div>
                <nav className="flex-1 space-y-2">
                    {navItems.map((item) => (
                        <Link key={item.label} href={item.href} className={`flex items-center gap-4 px-4 py-3 transition-all ${item.active ? 'border-r-2 border-[#7d562d] font-bold text-[#7d562d]' : 'text-stone-500 hover:text-stone-900'}`}>
                            <span className="material-symbols-outlined">{item.icon}</span>
                            <span className="text-xs font-label uppercase tracking-widest">{item.label}</span>
                        </Link>
                    ))}
                </nav>
                <div className="mt-auto border-t border-outline-variant/20 pt-6">
                    <AdminSettingsMenu showSettingsLink={false} />
                </div>
            </aside>

            <main className="min-h-screen flex-1 px-6 py-10 md:ml-64 md:px-10">
                <header className="mb-10 flex items-center justify-between">
                    <div>
                        <span className="mb-3 block text-xs uppercase tracking-[0.2em] text-primary">Panel Interno</span>
                        <h1 className="text-4xl font-serif italic text-on-background">Configuración Admin</h1>
                        <p className="mt-2 text-sm text-stone-500">Ajustes mínimos del entorno operativo manteniendo moneda HNL y el estilo del sistema.</p>
                    </div>
                    <AdminSettingsMenu iconOnly showSettingsLink={false} />
                </header>

                {flash?.success ? <div className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-700">{flash.success}</div> : null}
                {flash?.error ? <div className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">{flash.error}</div> : null}

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1.25fr_0.75fr]">
                    <form onSubmit={submit} className="rounded-2xl bg-white p-8 shadow-sm">
                        <div className="mb-8 border-b border-outline-variant/20 pb-6">
                            <h2 className="text-2xl font-serif text-on-background">Parámetros de Caja y Catálogo</h2>
                        </div>

                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-[10px] uppercase tracking-widest text-stone-500">Sucursal</label>
                                <div className="rounded-xl border border-outline-variant/20 bg-[#f6f3f2] px-4 py-3 text-sm text-stone-700">{branch.name} ({branch.code})</div>
                            </div>
                            <div className="space-y-2">
                                <label className="text-[10px] uppercase tracking-widest text-stone-500">Moneda</label>
                                <div className="rounded-xl border border-outline-variant/20 bg-[#f6f3f2] px-4 py-3 text-sm text-stone-700">{branch.currencySymbol} / {branch.currencyCode}</div>
                            </div>
                            <div className="space-y-2">
                                <label className="text-[10px] uppercase tracking-widest text-stone-500">Nombre del Impuesto</label>
                                <input value={data.taxName} onChange={(event) => setData('taxName', event.target.value)} className="w-full rounded-xl border border-outline-variant/20 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-0" />
                                {errors.taxName ? <p className="text-xs text-error">{errors.taxName}</p> : null}
                            </div>
                            <div className="space-y-2">
                                <label className="text-[10px] uppercase tracking-widest text-stone-500">Tasa de Impuesto (%)</label>
                                <input value={data.taxRate} onChange={(event) => setData('taxRate', event.target.value)} type="number" min="0" max="100" step="0.01" className="w-full rounded-xl border border-outline-variant/20 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-0" />
                                {errors.taxRate ? <p className="text-xs text-error">{errors.taxRate}</p> : null}
                            </div>
                            <div className="space-y-2">
                                <label className="text-[10px] uppercase tracking-widest text-stone-500">Ventana de Reautenticación (min)</label>
                                <input value={data.reauthWindowMinutes} onChange={(event) => setData('reauthWindowMinutes', event.target.value)} type="number" min="5" max="120" step="1" className="w-full rounded-xl border border-outline-variant/20 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-0" />
                                {errors.reauthWindowMinutes ? <p className="text-xs text-error">{errors.reauthWindowMinutes}</p> : null}
                            </div>
                            <label className="flex items-center justify-between rounded-xl border border-outline-variant/20 bg-[#f6f3f2] px-4 py-4 md:col-span-2">
                                <div>
                                    <span className="block text-sm font-semibold text-on-background">Permitir precio manual</span>
                                    <span className="block text-xs text-stone-500">Controla si el POS puede ajustar precios fuera del catálogo.</span>
                                </div>
                                <input checked={data.allowManualPrice} onChange={(event) => setData('allowManualPrice', event.target.checked)} type="checkbox" className="h-5 w-5 rounded border-outline-variant text-primary focus:ring-primary" />
                            </label>
                        </div>

                        <div className="mt-8 flex justify-end">
                            <button type="submit" disabled={processing} className="rounded-md bg-gradient-to-br from-primary to-primary-container px-6 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-on-primary disabled:opacity-70">
                                {processing ? 'Guardando...' : 'Guardar Configuración'}
                            </button>
                        </div>
                    </form>

                    <section className="space-y-6 rounded-2xl bg-white p-8 shadow-sm">
                        <div>
                            <h2 className="text-2xl font-serif text-on-background">Estado Actual</h2>
                            <p className="mt-2 text-sm text-stone-500">Referencia rápida para la operación administrativa sin romper la estética Stitch.</p>
                        </div>
                        <div className="rounded-xl bg-[#f6f3f2] p-5">
                            <p className="text-[10px] uppercase tracking-widest text-stone-500">Moneda operativa</p>
                            <p className="mt-2 text-3xl font-serif text-primary">{branch.currencySymbol} / {branch.currencyCode}</p>
                        </div>
                        <div className="rounded-xl bg-[#f6f3f2] p-5">
                            <p className="text-[10px] uppercase tracking-widest text-stone-500">Impuesto activo</p>
                            <p className="mt-2 text-3xl font-serif text-primary">{data.taxRate}%</p>
                        </div>
                        <div className="rounded-xl bg-[#f6f3f2] p-5">
                            <p className="text-[10px] uppercase tracking-widest text-stone-500">Precio manual</p>
                            <p className="mt-2 text-lg font-semibold text-on-background">{data.allowManualPrice ? 'Habilitado' : 'Bloqueado'}</p>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    );
}

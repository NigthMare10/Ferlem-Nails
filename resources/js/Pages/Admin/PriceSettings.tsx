import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AdminSettingsMenu from '@/Components/admin/AdminSettingsMenu';
import { buildAdminPrimaryNav } from '@/lib/adminNavigation';

type ServiceCard = {
    id: string;
    name: string;
    description: string;
    durationMinutes: number;
    durationLabel: string;
    price: string;
    priceAmount: string;
    image: string;
    categoryPublicId?: string | null;
    categoryName?: string | null;
};

type CategoryOption = {
    publicId: string;
    name: string;
};

type Props = {
    title: string;
    services: ServiceCard[];
    categoryOptions: CategoryOption[];
    summaryCards: Array<{ label: string; value: string }>;
    settingsUrl: string;
};

export default function PriceSettings({ title, services, categoryOptions, summaryCards, settingsUrl }: Props) {
    const [open, setOpen] = useState(false);
    const [editingService, setEditingService] = useState<ServiceCard | null>(null);
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };
    const navItems = useMemo(() => buildAdminPrimaryNav('pricing'), []);
    const categoryDefault = categoryOptions[0]?.publicId ?? '';

    const { data, setData, post, put, processing, reset, errors } = useForm({
        name: '',
        description: '',
        durationMinutes: '90',
        price: '',
        categoryPublicId: categoryDefault,
    });

    const openCreate = () => {
        setEditingService(null);
        reset();
        setData('categoryPublicId', categoryDefault);
        setOpen(true);
    };

    const openEdit = (service: ServiceCard) => {
        setEditingService(service);
        setData({
            name: service.name,
            description: service.description,
            durationMinutes: String(service.durationMinutes),
            price: service.priceAmount,
            categoryPublicId: service.categoryPublicId ?? categoryDefault,
        });
        setOpen(true);
    };

    const closeEditor = () => {
        setOpen(false);
        setEditingService(null);
        reset();
        setData('categoryPublicId', categoryDefault);
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const handlers = {
            preserveScroll: true,
            onSuccess: () => closeEditor(),
        };

        if (editingService) {
            put(route('catalogo.admin.update', editingService.id), handlers);
            return;
        }

        post(route('catalogo.admin.store'), handlers);
    };

    return (
        <div className="min-h-screen bg-surface text-on-surface">
            <Head title={title} />

            <header className="fixed top-0 z-50 flex w-full items-center justify-between bg-[#fcf9f8] px-8 py-4">
                <div className="text-2xl font-serif italic text-stone-900">FERLEM NAILS</div>
                <div className="flex items-center space-x-6">
                    <button type="button" className="rounded-full p-2 text-stone-500 transition-colors hover:bg-[#f6f3f2]">
                        <span className="material-symbols-outlined">notifications</span>
                    </button>
                    <AdminSettingsMenu iconOnly showSettingsLink settingsHref={settingsUrl} />
                    <div className="flex items-center space-x-2">
                        <span className="material-symbols-outlined text-[#7d562d]">account_circle</span>
                        <span className="text-sm font-semibold text-stone-700">Admin</span>
                    </div>
                </div>
            </header>

            <aside className="fixed left-0 top-0 hidden h-screen w-64 flex-col bg-[#f6f3f2] px-6 py-8 md:flex">
                <div className="mb-12 mt-12">
                    <h2 className="font-serif text-xl text-stone-800">FERLEM NAILS</h2>
                    <p className="mt-1 text-xs uppercase tracking-widest text-stone-500">Operación Profesional</p>
                </div>

                <nav className="flex-1 space-y-2">
                    {navItems.map((item) => (
                        <Link
                            key={item.label}
                            href={item.href}
                            className={`flex items-center space-x-3 px-4 py-3 transition-all duration-300 ${item.active ? 'border-r-2 border-[#7d562d] bg-surface-container-lowest font-bold text-[#7d562d]' : 'text-stone-500 hover:text-stone-900'}`}
                        >
                            <span className="material-symbols-outlined">{item.icon}</span>
                            <span className="text-xs font-label uppercase tracking-widest">{item.label}</span>
                        </Link>
                    ))}
                </nav>

                <div className="mt-auto space-y-2 border-t border-outline-variant/20 pt-6">
                    <AdminSettingsMenu showSettingsLink settingsHref={settingsUrl} />
                </div>
            </aside>

            <main className="min-h-screen px-8 pb-16 pt-24 md:ml-64">
                <div className="mx-auto mb-8 max-w-6xl">
                    {flash?.success ? <div className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-700">{flash.success}</div> : null}
                    {flash?.error ? <div className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">{flash.error}</div> : null}
                    <div className="grid grid-cols-1 items-end gap-12 md:grid-cols-2">
                        <div>
                            <span className="mb-4 block text-xs uppercase tracking-[0.2em] text-primary">Portal Administrativo</span>
                            <h1 className="text-5xl font-serif font-light leading-tight text-on-background">Catálogo de Precios <br />&amp; Servicios</h1>
                        </div>
                        <div className="text-right">
                            <button type="button" onClick={openCreate} className="rounded-md bg-gradient-to-br from-primary to-primary-container px-8 py-4 text-sm font-semibold tracking-wide text-on-primary transition-transform duration-200 active:scale-95">
                                Nuevo Servicio
                            </button>
                        </div>
                    </div>
                </div>

                <div className="mx-auto max-w-6xl">
                    <div className="rounded-xl bg-surface-container-low p-1">
                        <div className="grid grid-cols-12 gap-4 rounded-t-xl bg-surface-container-low px-8 py-6">
                            <div className="col-span-6"><span className="text-xs uppercase tracking-widest text-stone-500">Descripción del Servicio</span></div>
                            <div className="col-span-2 text-center"><span className="text-xs uppercase tracking-widest text-stone-500">Duración</span></div>
                            <div className="col-span-2 text-right"><span className="text-xs uppercase tracking-widest text-stone-500">Precio Actual</span></div>
                            <div className="col-span-2 text-right"><span className="text-xs uppercase tracking-widest text-stone-500">Acciones</span></div>
                        </div>

                        <div className="space-y-4 p-4">
                            {services.map((service) => (
                                <div key={service.id} className="group rounded-xl bg-surface-container-lowest p-6 transition-all duration-300 hover:translate-x-1">
                                    <div className="grid grid-cols-12 items-center gap-4">
                                        <div className="col-span-6 flex items-center gap-6">
                                            <div className="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-surface-container-highest">
                                                <img alt={service.name} className="h-full w-full object-cover grayscale transition-all duration-500 group-hover:grayscale-0" src={service.image} />
                                            </div>
                                            <div>
                                                <h3 className="text-xl font-serif text-on-background">{service.name}</h3>
                                                <p className="mt-1 text-sm text-stone-500">{service.description}</p>
                                            </div>
                                        </div>
                                        <div className="col-span-2 text-center"><span className="font-medium text-stone-700">{service.durationLabel}</span></div>
                                        <div className="col-span-2 text-right"><span className="text-2xl font-serif text-primary">{service.price}</span></div>
                                        <div className="col-span-2 text-right">
                                            <button type="button" onClick={() => openEdit(service)} className="rounded-md px-4 py-2 text-xs font-bold uppercase tracking-widest text-primary transition-colors hover:bg-primary-container/10">
                                                Editar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="mx-auto mt-16 grid max-w-6xl grid-cols-1 gap-8 md:grid-cols-3">
                    {summaryCards.map((card, index) => (
                        <div key={card.label} className={`rounded-xl p-8 ${index === 0 ? 'border-l-4 border-primary bg-surface-container-high/50' : 'bg-surface-container-high/50'}`}>
                            <span className="mb-2 block text-xs uppercase tracking-widest text-stone-500">{card.label}</span>
                            <div className="text-4xl font-serif text-on-background">{card.value}</div>
                        </div>
                    ))}
                    <div className="relative min-h-[120px] overflow-hidden rounded-xl">
                        <img alt="Experiencia FERLEM NAILS" className="absolute inset-0 h-full w-full object-cover brightness-50" src="https://lh3.googleusercontent.com/aida-public/AB6AXuA_q5RE7f4-fg8aH8S30uI1KBL1WPVG5oDmMsBrCwKUuuEFxGx4ETM7RAtWsIZyPl0PkA4nL8WWLJeVc5zVXtLD8qGP7c3MLeu7BAHeo3DNZ2mI_KiQTwkChJvW_DJFkoZQ2og5KzU7_2a0FAzcaQw60nL7W7YP0RdAiWsErvwc3mpOXJmgVjohbOW4eyTcXlneUW5b3mfXS9I46gyhSdnt1lLvGRf0pH0oUUXiCUjjH-QSvnHsZ_2QxeZw8SsaGckn_027jJ-3O2kj" />
                        <div className="absolute inset-0 flex items-center justify-center"><span className="text-xs font-bold uppercase tracking-widest text-white">Experiencia FERLEM NAILS</span></div>
                    </div>
                </div>
            </main>

            {open ? (
                <div className="fixed inset-0 z-[60] flex items-center justify-center bg-stone-950/35 px-4 py-6">
                    <form onSubmit={submit} className="w-full max-w-2xl rounded-2xl bg-[#fcf9f8] p-8 shadow-2xl">
                        <div className="mb-8 flex items-start justify-between gap-6">
                            <div>
                                <span className="mb-3 block text-xs uppercase tracking-[0.2em] text-primary">Portal Administrativo</span>
                                <h2 className="text-3xl font-serif text-on-background">{editingService ? 'Editar Servicio' : 'Nuevo Servicio'}</h2>
                            </div>
                            <button type="button" onClick={closeEditor} className="material-symbols-outlined rounded-full p-2 text-stone-500 transition-colors hover:bg-[#f6f3f2]">close</button>
                        </div>

                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div className="space-y-2 md:col-span-2">
                                <label className="text-[10px] uppercase tracking-widest text-stone-500">Servicio</label>
                                <input type="text" value={data.name} onChange={(event) => setData('name', event.target.value)} className="w-full rounded-xl border border-[#d4c2c3] bg-white px-4 py-3 text-sm text-on-surface focus:border-primary focus:ring-0" />
                                {errors.name ? <p className="text-xs text-error">{errors.name}</p> : null}
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <label className="text-[10px] uppercase tracking-widest text-stone-500">Descripción</label>
                                <textarea value={data.description} onChange={(event) => setData('description', event.target.value)} rows={3} className="w-full rounded-xl border border-[#d4c2c3] bg-white px-4 py-3 text-sm text-on-surface focus:border-primary focus:ring-0"></textarea>
                                {errors.description ? <p className="text-xs text-error">{errors.description}</p> : null}
                            </div>
                            <div className="space-y-2">
                                <label className="text-[10px] uppercase tracking-widest text-stone-500">Categoría</label>
                                <select value={data.categoryPublicId} onChange={(event) => setData('categoryPublicId', event.target.value)} className="w-full rounded-xl border border-[#d4c2c3] bg-white px-4 py-3 text-sm text-on-surface focus:border-primary focus:ring-0">
                                    {categoryOptions.map((category) => <option key={category.publicId} value={category.publicId}>{category.name}</option>)}
                                </select>
                                {errors.categoryPublicId ? <p className="text-xs text-error">{errors.categoryPublicId}</p> : null}
                            </div>
                            <div className="space-y-2">
                                <label className="text-[10px] uppercase tracking-widest text-stone-500">Duración</label>
                                <input type="number" min="5" step="5" value={data.durationMinutes} onChange={(event) => setData('durationMinutes', event.target.value)} className="w-full rounded-xl border border-[#d4c2c3] bg-white px-4 py-3 text-sm text-on-surface focus:border-primary focus:ring-0" />
                                {errors.durationMinutes ? <p className="text-xs text-error">{errors.durationMinutes}</p> : null}
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <label className="text-[10px] uppercase tracking-widest text-stone-500">Precio en Lempiras</label>
                                <input type="number" min="0.01" step="0.01" value={data.price} onChange={(event) => setData('price', event.target.value)} className="w-full rounded-xl border border-[#d4c2c3] bg-white px-4 py-3 text-sm text-on-surface focus:border-primary focus:ring-0" />
                                {errors.price ? <p className="text-xs text-error">{errors.price}</p> : null}
                            </div>
                        </div>

                        <div className="mt-8 flex justify-end gap-4">
                            <button type="button" onClick={closeEditor} className="px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-[#504444]">Cancelar</button>
                            <button type="submit" disabled={processing} className="rounded-md bg-gradient-to-br from-primary to-primary-container px-6 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-on-primary disabled:opacity-70">
                                {processing ? 'Guardando...' : editingService ? 'Guardar Cambios' : 'Crear Servicio'}
                            </button>
                        </div>
                    </form>
                </div>
            ) : null}
        </div>
    );
}

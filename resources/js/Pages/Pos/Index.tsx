import { formatMoney } from '@/shared/formatters';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, useMemo } from 'react';

type Service = {
    public_id: string;
    name: string;
    description?: string | null;
    duration_minutes: number;
    price_amount: number;
    category_name?: string | null;
    category_slug?: string | null;
};

type Branch = {
    name: string;
    code: string;
    terminal_id: string;
};

type Profile = {
    name: string;
    role_title?: string | null;
};

const categoryContent = {
    unas: {
        label: 'Uñas',
        icon: 'brush',
        image:
            'https://lh3.googleusercontent.com/aida-public/AB6AXuA1oZLylfQc9Tw5BSG9FH-uybVESbsBrUcYyOZM13QnLECSVlwzcdScKtHKMCblvKuJ3vZa3zLQbyhTWqlj6xM9PUg5oorrpKrOOMS6F7j81F_K5i-7XkSoixf2_93tmED99n2PcaZzKtfYiD_ACHHQ3Ye4D9TX53pu7Eut_fNQaExU0BWglBwLT_dlaRLxC7mGIZM6NATHCSjHAwYq8InfYXUULskR2dmZDvB4MqWMaNvqD6WVAErMUYWhO7RElKaBespQ8efYWwMa',
        title: 'Uñas & Manicura',
        description: 'Selecciona los tratamientos para agregarlos a la sesion operativa.',
        serviceImages: [
            'https://lh3.googleusercontent.com/aida-public/AB6AXuBXZhNckCdy7I7xLh41ylS_uYKADYBDJgfcJQZcWWPcPB19O7OQR15JslZODHj4jucDStmnQlSZY2vPwqcTG06wD69ggMcy4sNUI3e5k1T3jsZA-PEbB2HT32og8uyN1bFHuQDIFtld_T5sGSJWN4tigksWgE74p92SKZdFSoscLBoBSwNrMmHIwSAq5AzW5gF-PndLmjeqpwwKvXkyjII0GFkPSjBXB7mTE3V75yj6MC6zeJhDaAR6hpPhp984cpRf0ohcA7v7r7jV',
            'https://lh3.googleusercontent.com/aida-public/AB6AXuDclGkAIxC02jdKQXrzI4VzmbpaJgdDPyyjxSJDLYJISCXz3508CNTOwwukdh6DFcFiDsjrCRcV3TKWS18hDJiiOL4iqwNBCqwf8qGzYiM6Mbryno8TCfrIkDurUXZ_Y8ZkbrglAPo5VvaTzrGZ_LsvyTZ8DPJzgWuVpTFFvJvb6ANgF6IVWS_y1v_75VfyqS3LrtW9bWr2cJHm0xsx9ZPIqV5GnOXR86r_ltXvQMH5rEkm3Gl609Mmayu1Y32kcj3Car9jd9-F4hIk',
            'https://lh3.googleusercontent.com/aida-public/AB6AXuAmXUZX3Lap18i75hh-ky_mMM_5vUPpnyZbqAI9lNCxDwTOPwna7x1L-xDQLwIEvShUMMDW7PkpI7SprqxrDHanPr0w7P_0ANZUSWSiUql96DIMUHyVev2ijgZC7SZbWmZE17PYa0jr09Wgk0MH2bctrIi2_MRWaU5PXdjRvaMqY2tThyHtjFq3kL0lu9cKfxfbspxalaCTzZsJ67KHT4KTcj8jjDvwR2eyFCJcd0xx4TC4HJYrJFEYKf7dJAB7vjLLHVyPHKVFTEUw',
        ],
    },
    pestanas: {
        label: 'Pestañas',
        icon: 'visibility',
        image:
            'https://lh3.googleusercontent.com/aida-public/AB6AXuDWMNzSAjSHFa9i0tMCLz2ZRz_oO8Uh4AGBFZSFByjdr3aJjGNolSssb8eAx0MJhfp0WltSpDe7pANbNFWj9dMBiraFAHxMntEDMrA_vCP4pqPSwr5No5_2zdgjYBNcnWAIytov813T-FjD53D-X8DDO6DFVd4YCH9fOJ4hGL4YmrhS-2mZ5jBt_QeCmomH0So24mdxlJdmvxyD3vLHmcZQDCJfp0y_nfd43Dr6ObSD6nXak3kukNykRag0GIBApS5oxakoO7rWiory',
        title: 'Pestañas',
        description: 'Selecciona los tratamientos para agregarlos a la sesion operativa.',
        serviceImages: [
            'https://lh3.googleusercontent.com/aida-public/AB6AXuDWMNzSAjSHFa9i0tMCLz2ZRz_oO8Uh4AGBFZSFByjdr3aJjGNolSssb8eAx0MJhfp0WltSpDe7pANbNFWj9dMBiraFAHxMntEDMrA_vCP4pqPSwr5No5_2zdgjYBNcnWAIytov813T-FjD53D-X8DDO6DFVd4YCH9fOJ4hGL4YmrhS-2mZ5jBt_QeCmomH0So24mdxlJdmvxyD3vLHmcZQDCJfp0y_nfd43Dr6ObSD6nXak3kukNykRag0GIBApS5oxakoO7rWiory',
        ],
    },
} as const;

function Icon({ name, className = 'h-6 w-6' }: { name: string; className?: string }) {
    const common = { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, className };

    if (name === 'notifications') {
        return <svg {...common}><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5" /><path d="M10 20a2 2 0 0 0 4 0" /></svg>;
    }
    if (name === 'account') {
        return <svg {...common}><circle cx="12" cy="8" r="4" /><path d="M5 20a7 7 0 0 1 14 0" /></svg>;
    }
    if (name === 'back') {
        return <svg {...common}><path d="M15 18l-6-6 6-6" /></svg>;
    }
    if (name === 'brush') {
        return <svg {...common}><path d="M3 21c3 0 5-2 5-5 0-1 .4-2 1.2-2.8L18 4.4a2 2 0 0 1 2.8 2.8l-8.8 8.8A4 4 0 0 0 9.2 17c0 2.2-1.8 4-4 4H3Z" /></svg>;
    }
    if (name === 'visibility') {
        return <svg {...common}><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>;
    }
    if (name === 'schedule') {
        return <svg {...common}><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>;
    }
    if (name === 'add') {
        return <svg {...common}><path d="M12 5v14M5 12h14" /></svg>;
    }
    if (name === 'check') {
        return <svg {...common}><path d="m5 13 4 4L19 7" /></svg>;
    }
    if (name === 'lock') {
        return <svg {...common}><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 1 1 8 0v3" /></svg>;
    }

    return null;
}

export default function PosIndex({
    sucursal,
    servicios,
    perfilOperativo,
    initialCategory,
}: {
    sucursal: Branch;
    servicios: Service[];
    perfilOperativo: Profile;
    initialCategory?: 'unas' | 'pestanas' | null;
}) {
    const selectedCategory = initialCategory ?? null;

    const { data, setData, post, processing, errors, transform } = useForm({
        discount_amount: 0,
        notes: '',
        payment_method: 'tarjeta_manual',
        payment_reference: '',
        items: [] as Array<{ servicio_public_id: string; quantity: number }>,
    });

    const groupedServices = useMemo(() => ({
        unas: servicios.filter((service) => service.category_slug === 'unas'),
        pestanas: servicios.filter((service) => service.category_slug === 'pestanas'),
    }), [servicios]);

    const currentCategory = selectedCategory ? categoryContent[selectedCategory] : null;
    const visibleServices = selectedCategory ? groupedServices[selectedCategory] : [];

    const selectedServices = useMemo(
        () => data.items
            .map((item) => {
                const service = servicios.find((entry) => entry.public_id === item.servicio_public_id);

                return service ? { ...service, quantity: item.quantity } : null;
            })
            .filter(Boolean) as Array<Service & { quantity: number }>,
        [data.items, servicios],
    );

    const totals = selectedServices.reduce((acc, service) => {
        const subtotal = service.price_amount * service.quantity;
        const tax = Math.round(subtotal * 0.15);
        acc.subtotal += subtotal;
        acc.tax += tax;
        return acc;
    }, { subtotal: 0, tax: 0 });

    const total = Math.max(0, totals.subtotal + totals.tax - Number(data.discount_amount || 0));

    const toggleService = (service: Service) => {
        const existing = data.items.find((item) => item.servicio_public_id === service.public_id);

        if (existing) {
            setData('items', data.items.map((item) => item.servicio_public_id === service.public_id
                ? { ...item, quantity: item.quantity + 1 }
                : item));
            return;
        }

        setData('items', [...data.items, { servicio_public_id: service.public_id, quantity: 1 }]);
    };

    const decrementService = (service: Service) => {
        const existing = data.items.find((item) => item.servicio_public_id === service.public_id);

        if (!existing) {
            return;
        }

        if (existing.quantity <= 1) {
            setData('items', data.items.filter((item) => item.servicio_public_id !== service.public_id));
            return;
        }

        setData('items', data.items.map((item) => item.servicio_public_id === service.public_id
            ? { ...item, quantity: item.quantity - 1 }
            : item));
    };

    const submitCheckout = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        transform((current) => ({
            ...current,
            payment_reference: 'PAGO OPERATIVO',
        }));

        post(route('pos.checkout'), {
            preserveScroll: true,
        });
    };

    return (
        <div className="min-h-screen bg-[#fcf9f8] text-[#1b1c1c]">
            <Head title="Inicio de Cobro" />

            {selectedCategory === null ? (
                <>
                    <header className="z-50 flex w-full items-center justify-between bg-[#fcf9f8] px-8 py-4">
                        <div className="text-2xl font-serif italic text-stone-900">FERLEM NAILS</div>
                        <div className="flex items-center gap-6">
                            <button className="rounded-full p-2 text-[#7d562d] transition hover:bg-[#f6f3f2]">
                                <Icon name="notifications" />
                            </button>
                            <button className="rounded-full p-2 text-[#7d562d] transition hover:bg-[#f6f3f2]">
                                <Icon name="account" />
                            </button>
                        </div>
                    </header>

                    <main className="relative flex min-h-[calc(100vh-152px)] flex-col items-center justify-center px-6 md:px-12">
                        <div className="absolute left-[-80px] top-1/4 -z-10 h-96 w-96 rounded-full bg-[#eab786] opacity-10 blur-3xl" />
                        <div className="absolute bottom-1/4 right-[-80px] -z-10 h-80 w-80 rounded-full bg-[#fdc7cb] opacity-10 blur-3xl" />

                        <div className="w-full max-w-7xl">
                            <div className="mb-16 text-center">
                                <p className="mb-2 text-sm uppercase tracking-[0.2em] text-[#7c5357]">Bienvenue</p>
                                <h1 className="font-serif text-5xl italic leading-tight text-[#1b1c1c] md:text-6xl">Seleccione el Servicio</h1>
                            </div>

                            <div className="grid h-[500px] grid-cols-1 gap-12 md:grid-cols-2">
                                {(['unas', 'pestanas'] as const).map((key) => {
                                    const category = categoryContent[key];

                                    return (
                                        <Link
                                            key={key}
                                            href={route('pos.detail', key)}
                                            className="group relative flex items-center justify-center overflow-hidden rounded-xl bg-[#f6f3f2] shadow-sm transition-all duration-500 hover:-translate-y-2"
                                        >
                                            <img src={category.image} alt={category.label} className="absolute inset-0 h-full w-full object-cover opacity-40 grayscale-[20%] transition-transform duration-700 group-hover:scale-110 group-hover:grayscale-0" />
                                            <div className="absolute inset-0 bg-gradient-to-t from-[#fcf9f8] via-transparent to-transparent opacity-60" />
                                            <div className="relative z-10 flex flex-col items-center p-8 text-center">
                                                <div className="mb-6 text-[#7d562d] transition-transform duration-300 group-hover:scale-110">
                                                    <Icon name={category.icon} className="h-16 w-16" />
                                                </div>
                                                <h2 className="font-serif text-4xl text-[#1b1c1c]">{category.label}</h2>
                                                <div className="mt-4 opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                                                    <span className="border-b border-[#7d562d] pb-1 text-xs uppercase tracking-[0.25em] text-[#7d562d]">Comenzar el cobro</span>
                                                </div>
                                            </div>
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    </main>

                    <footer className="flex w-full items-center justify-between bg-[#f6f3f2] px-8 py-6">
                        <div className="text-xs uppercase tracking-[0.25em] text-stone-500">Terminal ID: {sucursal.terminal_id}</div>
                        <div className="flex gap-8 text-xs uppercase tracking-[0.25em] text-stone-500">
                            <span>{perfilOperativo.name}</span>
                            <Link href={route('logout')} method="post" as="button" className="transition hover:text-[#7d562d]">Salir</Link>
                        </div>
                    </footer>

                    <div className="fixed bottom-12 right-12">
                        <button className="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-[#7d562d] to-[#eab786] text-white shadow-lg transition hover:scale-105">
                            <Icon name="account" className="h-6 w-6" />
                        </button>
                    </div>
                </>
            ) : (
                <>
                    <header className="fixed left-0 right-0 top-0 z-50 flex items-center justify-between bg-[#fcf9f8] px-8 py-4">
                        <div className="flex items-center gap-4">
                            <Link href={route('pos.index')} className="rounded-full p-2 transition hover:bg-[#f6f3f2]">
                                <Icon name="back" className="h-6 w-6 text-[#7d562d]" />
                            </Link>
                            <h1 className="font-serif text-2xl italic text-stone-900">FERLEM NAILS</h1>
                        </div>
                        <div className="flex items-center gap-6">
                            <div className="hidden items-center gap-8 text-xs uppercase tracking-[0.25em] text-stone-500 md:flex">
                                <span className="font-bold text-[#7d562d]">Checkout</span>
                                <span>Confirmacion</span>
                            </div>
                            <div className="rounded-full p-2 text-[#7d562d]">
                                <Icon name="account" />
                            </div>
                        </div>
                    </header>

                    <main className="mx-auto grid max-w-7xl grid-cols-1 gap-12 px-6 pb-12 pt-24 md:px-12 lg:grid-cols-12">
                        <section className="lg:col-span-7">
                            <header className="mb-12">
                                <p className="mb-2 text-xs uppercase tracking-[0.2em] text-[#7c5357]">Category Selection</p>
                                <h2 className="font-serif text-4xl leading-tight text-[#1b1c1c] md:text-5xl">{currentCategory?.title}</h2>
                                <p className="mt-4 max-w-md text-[#504444]">{currentCategory?.description}</p>
                            </header>

                            <div className="space-y-6">
                                {visibleServices.map((service, index) => {
                                    const selectedItem = data.items.find((item) => item.servicio_public_id === service.public_id);
                                    const selected = Boolean(selectedItem);
                                    const image = currentCategory?.serviceImages[index % currentCategory.serviceImages.length] ?? currentCategory?.image;

                                    return (
                                        <button
                                            key={service.public_id}
                                            type="button"
                                            onClick={() => toggleService(service)}
                                            className={`group flex w-full items-center gap-6 rounded-xl p-6 text-left transition-all ${selected ? 'bg-[#f0eded]' : 'bg-white hover:bg-[#f6f3f2]'}`}
                                        >
                                            <div className="h-24 w-24 flex-shrink-0 overflow-hidden rounded-xl">
                                                <img src={image} alt={service.name} className="h-full w-full object-cover" />
                                            </div>
                                            <div className="flex-grow">
                                                <div className="flex items-start justify-between gap-4">
                                                    <h3 className="font-serif text-xl text-[#1b1c1c]">{service.name}</h3>
                                                    <span className="font-serif text-lg text-[#7d562d]">{formatMoney(service.price_amount)}</span>
                                                </div>
                                                <p className="mb-4 mt-1 text-sm text-[#504444]">{service.description || 'Servicio operativo disponible en el sistema.'}</p>
                                                <div className="flex items-center gap-2 text-[#7c5357]">
                                                    <Icon name="schedule" className="h-4 w-4" />
                                                    <span className="text-xs uppercase tracking-[0.2em]">{service.duration_minutes} min</span>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {selected ? (
                                                    <button
                                                        type="button"
                                                        onClick={(event) => {
                                                            event.preventDefault();
                                                            event.stopPropagation();
                                                            decrementService(service);
                                                        }}
                                                        className="flex h-9 w-9 items-center justify-center rounded-full border border-[#d4c2c3] bg-white text-[#1b1c1c] transition hover:border-[#7d562d] hover:text-[#7d562d]"
                                                    >
                                                        −
                                                    </button>
                                                ) : null}
                                                <div className={`flex h-10 w-10 items-center justify-center rounded-full border text-xs font-semibold transition-colors ${selected ? 'border-[#7d562d] bg-[#7d562d] text-white' : 'border-[#d4c2c3] text-[#1b1c1c] group-hover:border-[#7d562d] group-hover:bg-[#7d562d] group-hover:text-white'}`}>
                                                    {selected ? selectedItem?.quantity : <Icon name="add" className="h-5 w-5" />}
                                                </div>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </section>

                        <aside className="lg:col-span-5">
                            <form onSubmit={submitCheckout} className="sticky top-28 rounded-xl bg-[#f0eded] p-8">
                                <h3 className="mb-8 font-serif text-2xl text-[#1b1c1c]">Resumen del Cobro</h3>

                                <div className="mb-10 space-y-6">
                                    {selectedServices.length > 0 ? selectedServices.map((service) => (
                                        <div key={service.public_id} className="flex items-center justify-between gap-4">
                                            <div>
                                                <p className="font-bold text-[#1b1c1c]">{service.name}</p>
                                                <p className="text-xs text-[#504444]">Cantidad: {service.quantity} • Operado por: {perfilOperativo.name}</p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <button
                                                    type="button"
                                                    onClick={() => decrementService(service)}
                                                    className="flex h-8 w-8 items-center justify-center rounded-full border border-[#d4c2c3] bg-white text-sm text-[#1b1c1c] transition hover:border-[#7d562d] hover:text-[#7d562d]"
                                                >
                                                    −
                                                </button>
                                                <p className="font-serif text-[#1b1c1c]">{formatMoney(service.price_amount * service.quantity)}</p>
                                            </div>
                                        </div>
                                    )) : (
                                        <div className="text-sm text-[#504444]">Selecciona servicios para completar el resumen.</div>
                                    )}
                                </div>

                                <div className="mb-8 border-t border-[#d4c2c3]/30 pt-6">
                                    <div className="mb-2 flex items-center justify-between">
                                        <span className="text-[#504444]">Subtotal</span>
                                        <span className="font-serif">{formatMoney(totals.subtotal)}</span>
                                    </div>
                                    <div className="mb-6 flex items-center justify-between">
                                        <span className="text-[#504444]">Impuesto base</span>
                                        <span className="font-serif">{formatMoney(totals.tax)}</span>
                                    </div>
                                    <div className="flex items-center justify-between border-t border-[#d4c2c3]/40 pt-4">
                                        <span className="text-sm font-bold uppercase tracking-[0.25em]">Total</span>
                                        <span className="font-serif text-3xl text-[#7d562d]">{formatMoney(total)}</span>
                                    </div>
                                </div>

                                <div className="mb-10 rounded-xl border border-[#d4c2c3]/30 bg-[#fcf9f8]/70 px-5 py-4 text-sm text-[#504444]">
                                    El pago se procesa de forma directa en esta fase minima. No se capturan datos de tarjeta ni informacion sensible.
                                </div>

                                {errors.items ? <p className="mb-4 text-sm text-[#ba1a1a]">{errors.items}</p> : null}
                                {errors.discount_amount ? <p className="mb-4 text-sm text-[#ba1a1a]">{errors.discount_amount}</p> : null}
                                <button
                                    className="flex w-full items-center justify-center gap-3 rounded-md bg-gradient-to-br from-[#7d562d] to-[#eab786] py-5 text-sm uppercase tracking-[0.25em] text-white shadow-xl shadow-[#7c5357]/10 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                                    disabled={processing || selectedServices.length === 0}
                                >
                                    Pagar
                                    <Icon name="lock" className="h-4 w-4" />
                                </button>
                                <p className="mt-6 text-center text-[10px] uppercase tracking-[0.2em] text-[#605e5c]">Transaccion interna simplificada</p>
                            </form>
                        </aside>
                    </main>

                    <footer className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-8 border-t border-[#d4c2c3]/10 px-6 py-12 md:flex-row">
                        <div className="text-center md:text-left">
                            <span className="font-serif text-xl italic text-stone-800">FERLEM NAILS</span>
                            <p className="mt-1 text-xs uppercase tracking-[0.3em] text-stone-500">Operación profesional</p>
                        </div>
                        <div className="flex gap-8 text-xs uppercase tracking-[0.25em] text-stone-400">
                            <Link href={route('pos.index')} className="transition hover:text-[#7d562d]">Back</Link>
                            <Link href={route('logout')} method="post" as="button" className="transition hover:text-[#7d562d]">Logout</Link>
                        </div>
                    </footer>
                </>
            )}
        </div>
    );
}

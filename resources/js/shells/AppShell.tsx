import FlashBanner from '@/Components/FlashBanner';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

type NavItem = {
    label: string;
    href: string;
    permission?: string;
};

const navigation: NavItem[] = [
    { label: 'Panel', href: '/dashboard' },
    { label: 'Clientes', href: '/clientes', permission: 'clientes.ver' },
    { label: 'Catálogo', href: '/catalogo/servicios', permission: 'catalogo.ver' },
    { label: 'Agenda', href: '/agenda', permission: 'agenda.ver' },
    { label: 'POS', href: '/pos', permission: 'pos.usar' },
    { label: 'Caja', href: '/caja', permission: 'caja.ver' },
    { label: 'Facturas', href: '/facturas', permission: 'facturas.ver' },
    { label: 'Empleados', href: '/empleados', permission: 'empleados.ver' },
    { label: 'Reportes', href: '/reportes', permission: 'reportes.ver_sucursal' },
];

type Props = PropsWithChildren<{
    title: string;
    section?: 'admin' | 'pos' | 'reportes';
}>;

export default function AppShell({ children, title, section = 'admin' }: Props) {
    const page = usePage().props as {
        auth: {
            user: { name: string; email: string; permissions: string[] } | null;
            activeBranch?: { data?: { name: string } } | { name: string } | null;
        };
    };
    const currentUrl = usePage().url;

    const permissions = page.auth.user?.permissions ?? [];
    const accent = section === 'pos' ? 'from-brand-copper to-brand-gold' : section === 'reportes' ? 'from-brand-rose to-brand-copper' : 'from-stone-900 to-brand-copper';
    const activeBranch = (page.auth.activeBranch as { name?: string; data?: { name?: string } } | null)?.name
        ?? (page.auth.activeBranch as { data?: { name?: string } } | null)?.data?.name
        ?? 'Sin sucursal';

    return (
        <div className="min-h-screen bg-brand-cream text-stone-900">
            <div className="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(183,127,102,0.20),_transparent_40%),radial-gradient(circle_at_bottom_right,_rgba(210,174,128,0.18),_transparent_35%)]" />
            <div className="relative mx-auto grid min-h-screen max-w-[1600px] grid-cols-1 lg:grid-cols-[280px_1fr]">
                <aside className="border-b border-white/60 bg-white/75 p-6 backdrop-blur lg:border-b-0 lg:border-r">
                    <div className={`rounded-3xl bg-gradient-to-br ${accent} p-6 text-white shadow-xl`}>
                        <p className="text-xs uppercase tracking-[0.4em] text-white/75">FERLEM NAILS</p>
                        <h2 className="mt-3 font-display text-4xl italic">Gestión</h2>
                        <p className="mt-3 text-sm text-white/80">Sistema operativo para sucursal, agenda, POS, caja y facturación en HNL.</p>
                    </div>

                    <div className="mt-8 rounded-3xl bg-white/90 p-4 shadow-sm">
                        <p className="text-xs uppercase tracking-[0.3em] text-stone-500">Sucursal activa</p>
                        <p className="mt-2 font-semibold text-stone-900">{activeBranch}</p>
                        <Link href="/sucursales/seleccionar" className="mt-3 inline-flex text-sm text-brand-copper underline-offset-4 hover:underline">
                            Cambiar sucursal
                        </Link>
                    </div>

                    <nav className="mt-8 space-y-2">
                        {navigation
                            .filter((item) => !item.permission || permissions.includes(item.permission))
                            .map((item) => {
                                const active = currentUrl.startsWith(item.href);

                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className={`flex items-center rounded-2xl px-4 py-3 text-sm font-medium transition ${
                                            active
                                                ? 'bg-stone-900 text-white shadow-lg'
                                                : 'text-stone-700 hover:bg-white hover:text-stone-950'
                                        }`}
                                    >
                                        {item.label}
                                    </Link>
                                );
                            })}
                    </nav>
                </aside>

                <div className="flex min-h-screen flex-col">
                    <header className="flex flex-col gap-4 border-b border-white/60 bg-white/65 px-6 py-5 backdrop-blur md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-xs uppercase tracking-[0.35em] text-stone-500">Operación</p>
                            <h1 className="mt-2 font-display text-3xl">{title}</h1>
                        </div>
                        <div className="flex items-center gap-4">
                            <div className="text-right text-sm">
                                <p className="font-semibold text-stone-900">{page.auth.user?.name}</p>
                                <p className="text-stone-500">{page.auth.user?.email}</p>
                            </div>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="rounded-full border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-900 hover:text-stone-950"
                            >
                                Cerrar sesión
                            </Link>
                        </div>
                    </header>

                    <main className="flex-1 px-6 py-8">
                        <FlashBanner />
                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
}

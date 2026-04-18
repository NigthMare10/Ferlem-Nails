import { PropsWithChildren } from 'react';

type Props = PropsWithChildren<{
    title: string;
    subtitle: string;
}>;

export default function AuthShell({ title, subtitle, children }: Props) {
    return (
        <div className="min-h-screen bg-brand-cream px-4 py-10">
            <div className="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[1.1fr_0.9fr]">
                <div className="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-brand-ink via-stone-900 to-brand-copper p-10 text-white shadow-[0_35px_90px_rgba(32,24,22,0.25)]">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(255,255,255,0.16),_transparent_35%),radial-gradient(circle_at_bottom_right,_rgba(200,166,112,0.28),_transparent_28%)]" />
                    <div className="relative">
                        <p className="text-xs uppercase tracking-[0.45em] text-white/70">FERLEM NAILS</p>
                        <h1 className="mt-6 max-w-md font-display text-6xl italic leading-tight">Operación segura para agenda, POS y caja.</h1>
                        <p className="mt-6 max-w-lg text-base leading-8 text-white/80">
                            Plataforma interna en español, preparada para facturación en lempiras, control por sucursal y acciones sensibles con reautenticación administrativa.
                        </p>
                        <div className="mt-10 grid grid-cols-2 gap-4 text-sm text-white/85">
                            <div className="rounded-2xl border border-white/15 bg-white/10 p-4">Flujo obligatorio de cliente antes del cobro.</div>
                            <div className="rounded-2xl border border-white/15 bg-white/10 p-4">Caja, facturas y auditoría desde la base.</div>
                        </div>
                    </div>
                </div>

                <div className="card-panel flex items-center justify-center p-8 lg:p-12">
                    <div className="w-full max-w-md">
                        <p className="text-xs uppercase tracking-[0.35em] text-brand-rose">Acceso</p>
                        <h2 className="mt-3 font-display text-4xl text-stone-900">{title}</h2>
                        <p className="mt-3 text-sm leading-7 text-stone-600">{subtitle}</p>
                        <div className="mt-8">{children}</div>
                    </div>
                </div>
            </div>
        </div>
    );
}

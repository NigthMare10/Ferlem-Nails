import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Props = {
    iconOnly?: boolean;
    showSettingsLink?: boolean;
    settingsHref?: string;
    align?: 'left' | 'right';
};

export default function AdminSettingsMenu({
    iconOnly = false,
    showSettingsLink = true,
    settingsHref = '/configuracion-admin',
    align = 'right',
}: Props) {
    const [open, setOpen] = useState(false);
    const logout = useForm({});

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className={iconOnly
                    ? 'material-symbols-outlined rounded-full p-2 text-stone-500 transition-colors hover:bg-[#f6f3f2] hover:text-stone-900'
                    : 'flex w-full items-center gap-3 px-4 py-3 text-stone-500 transition-colors hover:text-stone-900'}
            >
                <span className="material-symbols-outlined text-xl">settings</span>
                {iconOnly ? null : <span className="font-label text-xs uppercase tracking-widest">Configuración</span>}
            </button>

            {open ? (
                <div className={`absolute ${align === 'right' ? 'right-0' : 'left-0'} mt-2 w-48 rounded-xl border border-[#d4c2c3] bg-white p-2 shadow-xl`}>
                    {showSettingsLink ? (
                        <Link
                            href={settingsHref}
                            className="block w-full rounded-lg px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#504444] hover:bg-[#f6f3f2]"
                        >
                            Configuración
                        </Link>
                    ) : null}
                    <button
                        type="button"
                        onClick={() => logout.post(route('logout'))}
                        className="w-full rounded-lg px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-[#504444] hover:bg-[#f6f3f2]"
                    >
                        Salir
                    </button>
                </div>
            ) : null}
        </div>
    );
}

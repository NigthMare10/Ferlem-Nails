import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';

type AccessProfile = {
    public_id: string;
    display_name: string;
    role_key?: string | null;
    role_label: string;
    branch_names: string[];
    branch_count: number;
    initials: string;
    is_active: boolean;
    status_label: string;
};

const profileImages = [
    'https://lh3.googleusercontent.com/aida-public/AB6AXuBm-oMotRAJP7h_LbbVpc9t9m4EMFHjSbDd5yyYR0-zAWmclAXuBv2Fhic3padCyG-TZU0Blk5Hzjt-BN19p2qw9u6s81BwWhWpeSKVJdG-LZW0f261ohhzoaoAnzz6ghW_Z3qAcQf0rZT7erZ6sgE9Pi3xtYhxmYmRWgmItEXw9f0DfXhQsSrS4WoTbnjxvNamyIjwe2aHk09kp0SigSd3Imt3KR7fEC2uuMwuEiAYJJUz7UF8biPubRMx_1p0pgbqYYm8USwxr-2r',
    'https://lh3.googleusercontent.com/aida-public/AB6AXuBmuZyLTSTZEzHZvoB2Wlm2uHLLtlqvYDzxR2M7RSLEGWq84i4O1yhBAO3frG_9bxwaIEYsa5UUrGUFZlYZmOqq6PaOxgAqEXWrHyCGdyfCETKYWX6cT_KESsynKgbGp2J5BNziNjELkUdj1l3bHRQWRUzAT7ggehnZkJrSnxlLnyQizITs5lmq6zUsfuPEjOjsCyHZsZdnWtMlrtRH11ic89L-tiJsLTpa3JnGrSNMg6-CZdrZ6eJo9d30f_jkIihMUKYrhJIMrN-9',
    'https://lh3.googleusercontent.com/aida-public/AB6AXuBDplYkpjx9hjh5FztR7v0OZ8o0Xneew4Hp9F_ORXnzi4J4qDDBtR7gWUeY4Bz1J7_Ylvsbskat16GfDviL48sKJgTKIAyz98mBNkH3Rv3P_IrxG_ZFB4ohC-iGKu4a1sYdNYJNdGdYpT95nKB4SY36FBKr5654LkhZ1mi5IGHtqPadZ3TbD8EUCSHeV9r7OUUMkAO-K70g23RINmkz-XOzsr5ViaLtQBGDgRnUMD09i83PO1ZBD9G0EQEc2Dv5-ILrckEKTUNMfGHs',
];

const keypad = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', 'backspace'] as const;

export default function Login({ profiles, status }: { profiles: AccessProfile[]; status?: string }) {
    const [step, setStep] = useState<'seleccion' | 'contrasena'>('seleccion');
    const [selectedProfile, setSelectedProfile] = useState<AccessProfile | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        profile_public_id: '',
        password: '',
        remember: false,
    });

    const profileImageMap = useMemo(() => {
        return profiles.reduce<Record<string, string>>((acc, profile, index) => {
            acc[profile.public_id] = profileImages[index % profileImages.length];
            return acc;
        }, {});
    }, [profiles]);

    const activeDots = Math.min(data.password.length, 4);

    const selectProfile = (profile: AccessProfile) => {
        setSelectedProfile(profile);
        setData('profile_public_id', profile.public_id);
        setData('password', '');
        setStep('contrasena');
    };

    const appendDigit = (digit: string) => {
        setData('password', `${data.password}${digit}`);
    };

    const removeLastDigit = () => {
        setData('password', data.password.slice(0, -1));
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return step === 'seleccion' ? (
        <div className="min-h-screen overflow-x-hidden bg-[#fcf9f8] font-body text-[#1b1c1c] antialiased">
            <Head title="Seleccion de perfil" />

            <header className="fixed top-0 z-50 w-full bg-[#fcf9f8]/80 backdrop-blur-md">
                <div className="mx-auto flex w-full max-w-7xl items-center justify-between px-8 py-8">
                    <h1 className="font-headline text-2xl font-bold italic tracking-tight text-[#1b1c1c]">FERLEM NAILS</h1>
                    <div className="flex items-center gap-6">
                        <span className="material-symbols-outlined text-[#504444] transition-colors hover:text-[#7d562d]">help</span>
                    </div>
                </div>
            </header>

            <main className="flex min-h-screen flex-col items-center justify-center px-6 pb-12 pt-24">
                <div className="mb-16 max-w-2xl text-center">
                    <h2 className="font-headline text-5xl font-bold tracking-tighter text-[#1b1c1c] md:text-6xl">Seleccione su Perfil</h2>
                    <p className="font-body text-xs uppercase tracking-[0.2em] text-[#504444]">Bienvenido al santuario de gestion</p>
                </div>

                <div className="grid w-full max-w-5xl grid-cols-2 gap-8 md:grid-cols-3 lg:grid-cols-4 md:gap-12">
                    {profiles.map((profile) => (
                        <button key={profile.public_id} type="button" onClick={() => selectProfile(profile)} className="group flex flex-col items-center space-y-4 focus:outline-none">
                            <div className="relative h-32 w-32 overflow-hidden rounded-xl bg-[#f6f3f2] transition-transform duration-500 group-hover:scale-105 md:h-40 md:w-40">
                                <img
                                    className="h-full w-full object-cover grayscale transition-all duration-700 group-hover:grayscale-0"
                                    alt={profile.display_name}
                                    src={profileImageMap[profile.public_id]}
                                />
                                <div className="pointer-events-none absolute inset-0 rounded-xl border border-[#504444]/10"></div>
                            </div>
                            <div className="text-center">
                                <p className="font-headline text-lg font-bold text-[#1b1c1c]">{profile.display_name}</p>
                                <p className="font-label text-[10px] uppercase tracking-[0.15em] text-[#504444]">{profile.role_label}</p>
                            </div>
                        </button>
                    ))}
                </div>

                {profiles.length === 0 ? (
                    <div className="mt-8 rounded-xl border border-[#d4c2c3] bg-[#f6f3f2] px-6 py-4 text-sm text-[#504444]">
                        No hay perfiles activos disponibles para iniciar sesion.
                    </div>
                ) : null}

                <div className="mt-24 text-center">
                    <p className="mb-6 font-label text-[10px] uppercase tracking-[0.3em] text-[#504444]">FERLEM NAILS</p>
                    <div className="flex items-center justify-center gap-1">
                        <div className="h-px w-8 bg-[#d4c2c3]"></div>
                        <div className="h-1.5 w-1.5 rounded-full bg-[#eab786]"></div>
                        <div className="h-px w-8 bg-[#d4c2c3]"></div>
                    </div>
                </div>
            </main>

            <div className="pointer-events-none fixed bottom-0 right-0 p-12 opacity-20">
                <p className="font-headline text-8xl italic text-[#e4e2e1] select-none">F</p>
            </div>
        </div>
    ) : (
        <div className="flex min-h-screen items-center justify-center bg-[#fcf9f8] font-body text-[#1b1c1c] selection:bg-[#eab786] selection:text-[#6c471f]">
            <Head title="Ingreso de contrasena" />

            <main className="flex w-full max-w-md flex-col items-center px-6 py-12">
                <div className="mb-12 text-center">
                    <h1 className="font-headline text-2xl italic tracking-tight text-[#7d562d]">FERLEM NAILS</h1>
                </div>

                <section className="mb-10 flex flex-col items-center">
                    <div className="group relative mb-6 h-28 w-28">
                        <div className="absolute inset-0 scale-105 rounded-full bg-[#eab786] opacity-20 blur-xl"></div>
                        <img
                            alt={selectedProfile?.display_name ?? 'Perfil'}
                            className="relative z-10 h-full w-full rounded-full border-2 border-[#eab786]/30 object-cover"
                            src={selectedProfile ? profileImageMap[selectedProfile.public_id] : profileImages[0]}
                        />
                    </div>
                    <h2 className="font-headline text-3xl text-[#1b1c1c]">{selectedProfile?.display_name}</h2>
                    <p className="font-label text-sm uppercase tracking-[0.2em] text-[#504444]/70">{selectedProfile?.role_label}</p>
                </section>

                <div className="mb-12 flex gap-4">
                    {[0, 1, 2, 3].map((index) => (
                        <div key={index} className={`h-3 w-3 rounded-full border border-[#7d562d] transition-all ${index < activeDots ? 'bg-[#7d562d]' : 'bg-transparent'}`}></div>
                    ))}
                </div>

                <form onSubmit={submit} className="w-full max-w-[280px]">
                    <input
                        type="password"
                        name="password"
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                        autoFocus
                        className="sr-only"
                        autoComplete="current-password"
                    />

                    <div className="mb-12 grid grid-cols-3 gap-x-8 gap-y-6">
                        {keypad.map((key, index) => {
                            if (key === '') {
                                return <div key={`empty-${index}`} className="h-16 w-16"></div>;
                            }

                            if (key === 'backspace') {
                                return (
                                    <button key={key} type="button" onClick={removeLastDigit} className="flex h-16 w-16 items-center justify-center text-[#504444]/40 transition-colors hover:text-[#1b1c1c]">
                                        <span className="material-symbols-outlined">backspace</span>
                                    </button>
                                );
                            }

                            return (
                                <button key={key} type="button" onClick={() => appendDigit(key)} className="group flex h-16 w-16 items-center justify-center rounded-full text-xl text-[#1b1c1c] transition-all duration-300 hover:bg-[#f6f3f2]">
                                    <span className="font-headline transition-transform group-active:scale-90">{key}</span>
                                </button>
                            );
                        })}
                    </div>

                    {status ? <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{status}</div> : null}
                    {errors.password ? <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{errors.password}</div> : null}

                    <div className="flex flex-col gap-4">
                        <button className="w-full rounded-xl bg-gradient-to-br from-[#7d562d] to-[#eab786] py-4 text-xs font-semibold uppercase tracking-[0.2em] text-white shadow-sm transition-all duration-200 hover:opacity-90" disabled={processing}>
                            {processing ? 'Validando' : 'Ingresar'}
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setStep('seleccion');
                                setSelectedProfile(null);
                                reset('password');
                            }}
                            className="w-full rounded-xl border border-[#d4c2c3]/30 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-[#504444] transition-colors hover:bg-[#f6f3f2]"
                        >
                            Cancelar
                        </button>
                    </div>
                </form>

                <footer className="mt-20 flex gap-8">
                    <button type="button" className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.2em] text-[#504444]/50 transition-colors hover:text-[#7d562d]">
                        <span className="material-symbols-outlined text-sm">lock</span>
                        Terminal bloqueada
                    </button>
                    <button type="button" className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.2em] text-[#504444]/50 transition-colors hover:text-[#7d562d]">
                        <span className="material-symbols-outlined text-sm">help_outline</span>
                        Soporte
                    </button>
                </footer>
            </main>

            <div className="pointer-events-none fixed right-0 top-0 p-12 opacity-5">
                <span className="font-headline text-[20vw] italic select-none">F</span>
            </div>
        </div>
    );
}

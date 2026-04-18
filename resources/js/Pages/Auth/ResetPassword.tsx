import AuthShell from '@/shells/AuthShell';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthShell title="Restablecer contraseña" subtitle="Define una contraseña nueva para continuar usando FERLEM NAILS.">
            <Head title="Restablecer contraseña" />

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label htmlFor="email" className="text-sm font-medium text-stone-700">Correo electrónico</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="field"
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    {errors.email ? <p className="mt-2 text-sm text-rose-600">{errors.email}</p> : null}
                </div>

                <div>
                    <label htmlFor="password" className="text-sm font-medium text-stone-700">Nueva contraseña</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="field"
                        autoComplete="new-password"
                        autoFocus
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {errors.password ? <p className="mt-2 text-sm text-rose-600">{errors.password}</p> : null}
                </div>

                <div>
                    <label htmlFor="password_confirmation" className="text-sm font-medium text-stone-700">Confirmar contraseña</label>
                    <input
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="field"
                        autoComplete="new-password"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />
                    {errors.password_confirmation ? <p className="mt-2 text-sm text-rose-600">{errors.password_confirmation}</p> : null}
                </div>

                <button className="primary-action w-full justify-center" disabled={processing}>
                    {processing ? 'Guardando...' : 'Restablecer contraseña'}
                </button>
            </form>
        </AuthShell>
    );
}

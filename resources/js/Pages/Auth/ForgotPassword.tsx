import AuthShell from '@/shells/AuthShell';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <AuthShell title="Recuperar acceso" subtitle="Te enviaremos un enlace para restablecer tu contraseña administrativa o operativa.">
            <Head title="Recuperar acceso" />

            <div className="mb-4 text-sm leading-7 text-stone-600">
                Ingresa tu correo electrónico y te enviaremos un enlace para definir una nueva contraseña.
            </div>

            {status && (
                <div className="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <input
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    className="field"
                    autoFocus
                    onChange={(e) => setData('email', e.target.value)}
                />

                {errors.email ? <p className="text-sm text-rose-600">{errors.email}</p> : null}

                <button className="primary-action w-full justify-center" disabled={processing}>
                    {processing ? 'Enviando...' : 'Enviar enlace de recuperación'}
                </button>
            </form>
        </AuthShell>
    );
}

import AuthShell from '@/shells/AuthShell';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function ReauthenticateAdmin() {
    const { data, setData, post, processing, errors } = useForm({
        password: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(route('auth.reautenticacion.store'));
    };

    return (
        <AuthShell title="Confirmación administrativa" subtitle="Por seguridad, confirma tu contraseña antes de ejecutar acciones sensibles en FERLEM NAILS.">
            <Head title="Confirmación administrativa" />

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label htmlFor="password" className="text-sm font-medium text-stone-700">
                        Contraseña actual
                    </label>
                    <input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                        className="field"
                    />
                    {errors.password ? <p className="mt-2 text-sm text-rose-600">{errors.password}</p> : null}
                </div>

                <button className="primary-action w-full" disabled={processing}>
                    {processing ? 'Validando...' : 'Confirmar acceso'}
                </button>
            </form>
        </AuthShell>
    );
}

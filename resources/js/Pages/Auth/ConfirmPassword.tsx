import AuthShell from '@/shells/AuthShell';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthShell title="Confirmación de seguridad" subtitle="Confirma tu contraseña antes de continuar en esta zona protegida.">
            <Head title="Confirmación de seguridad" />

            <div className="mb-4 text-sm text-stone-600">
                Esta sección requiere una validación adicional de contraseña.
            </div>

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label htmlFor="password" className="text-sm font-medium text-stone-700">Contraseña</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="field"
                        autoFocus
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {errors.password ? <p className="mt-2 text-sm text-rose-600">{errors.password}</p> : null}
                </div>

                <button className="primary-action w-full justify-center" disabled={processing}>
                    {processing ? 'Validando...' : 'Confirmar'}
                </button>
            </form>
        </AuthShell>
    );
}

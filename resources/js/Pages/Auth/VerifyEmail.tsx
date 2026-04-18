import AuthShell from '@/shells/AuthShell';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <AuthShell title="Verificación de correo" subtitle="Confirma la dirección asociada a la cuenta antes de continuar.">
            <Head title="Verificación de correo" />

            <div className="mb-4 text-sm text-stone-600">
                Revisa tu bandeja de entrada y confirma tu correo usando el enlace enviado. Si no recibiste el mensaje, puedes solicitar uno nuevo.
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    Enviamos un nuevo enlace de verificación al correo registrado.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="mt-4 flex items-center justify-between">
                    <button className="primary-action" disabled={processing}>Reenviar verificación</button>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-sm text-stone-600 underline-offset-4 hover:text-brand-copper hover:underline"
                    >
                        Cerrar sesión
                    </Link>
                </div>
            </form>
        </AuthShell>
    );
}

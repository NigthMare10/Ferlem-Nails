import { usePage } from '@inertiajs/react';

export default function FlashBanner() {
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };

    if (!flash?.success && !flash?.error) {
        return null;
    }

    const isError = Boolean(flash.error);

    return (
        <div
            className={`mb-6 rounded-2xl border px-4 py-3 text-sm ${
                isError
                    ? 'border-rose-200 bg-rose-50 text-rose-800'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-800'
            }`}
        >
            {flash.error ?? flash.success}
        </div>
    );
}

import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
    const permissions = () => ((usePage().props.auth as any)?.permissions ?? []) as string[];
    return { can: (permission: string) => permissions().includes(permission), canAny: (items: string[]) => items.some(item => permissions().includes(item)) };
}

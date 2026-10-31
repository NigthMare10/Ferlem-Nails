import { onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/** Reconcile expirations and staff actions only while the customer is looking. */
export function useLoyaltyRefresh(redemptions: () => Array<{ status: string; expires_at: string }>) {
    let timer: ReturnType<typeof setTimeout> | undefined;
    let busy = false;
    let disposed = false;
    let lastAttempt = 0;
    function schedule() {
        clearTimeout(timer);
        if (disposed || document.hidden) return;
        const pending = redemptions().filter((r) => r.status === 'reserved');
        const nextExpiry = pending.length ? Math.min(...pending.map((r) => new Date(r.expires_at).getTime())) : Date.now() + 60000;
        const delay = Math.max(500, lastAttempt + 15000 - Date.now(), Math.min(60000, nextExpiry - Date.now() + 250));
        timer = setTimeout(refresh, delay);
    }
    function refresh() {
        if (disposed || busy || document.hidden) return;
        if (Date.now() - lastAttempt < 15000) {
            schedule();
            return;
        }
        busy = true;
        lastAttempt = Date.now();
        router.reload({
            only: ['balance', 'qualifying', 'visits', 'rewards', 'redemptions', 'redemption_history', 'customer', 'gifts'],
            onFinish: () => {
                busy = false;
                schedule();
            },
        });
    }
    function visibility() {
        if (document.hidden) clearTimeout(timer);
        else refresh();
    }
    onMounted(() => {
        schedule();
        document.addEventListener('visibilitychange', visibility);
        window.addEventListener('focus', refresh);
    });
    watch(redemptions, schedule);
    onUnmounted(() => {
        disposed = true;
        clearTimeout(timer);
        document.removeEventListener('visibilitychange', visibility);
        window.removeEventListener('focus', refresh);
    });
}

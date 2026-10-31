<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<{ gifts: any }>();
const labels: Record<string, string> = {
    credited: 'Puntos acreditados',
    available: 'Disponible para tu visita',
    confirmed: 'Servicio entregado',
    cancelled: 'Regalo cancelado',
    expired: 'Regalo vencido',
};
const date = (v: string) => new Intl.DateTimeFormat('es-HN', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'America/Tegucigalpa' }).format(new Date(v));
</script>
<template>
    <section v-if="gifts?.data?.length" class="member-gifts" aria-labelledby="gift-heading">
        <h2 id="gift-heading">Regalos para ti</h2>
        <p>Atenciones de Studio Lemus. No necesitas gastar puntos.</p>
        <article v-for="g in gifts.data" :key="g.id" data-motion-reveal>
            <div>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path d="M3 8h18v4H3zM5 12v9h14v-9M12 8v13M12 8H8a3 3 0 1 1 3-3l1 3Zm0 0h4a3 3 0 1 0-3-3l-1 3Z" />
                </svg>
                <strong>{{ g.batch.type === 'points' ? `+${g.batch.points} puntos de regalo` : g.batch.service_snapshot?.name }}</strong>
            </div>
            <span class="gift-status">{{ labels[g.status] }}</span>
            <p>{{ g.batch.message || g.batch.reason }}</p>
            <p v-if="g.batch.type === 'points'">Ya están en tu saldo disponible. Tu nivel no cambia.</p>
            <template v-else
                ><p v-if="g.status === 'available'">
                    Indica el código <strong>RG-{{ g.id }}</strong> al personal para recibir tu servicio.
                </p>
                <p>{{ g.batch.expires_at ? `Vigencia: ${date(g.batch.expires_at)} (Honduras)` : 'Sin fecha de vencimiento' }}</p></template
            >
        </article>
        <nav v-if="gifts.last_page > 1" aria-label="Páginas de regalos" class="gift-pagination">
            <Link v-if="gifts.prev_page_url" :href="gifts.prev_page_url" preserve-scroll>← Anteriores</Link
            ><span>{{ gifts.current_page }} / {{ gifts.last_page }}</span
            ><Link v-if="gifts.next_page_url" :href="gifts.next_page_url" preserve-scroll>Siguientes →</Link>
        </nav>
    </section>
</template>
<style scoped>
.member-gifts {
    margin: 24px 0;
    color: #f5daf6;
}
.member-gifts h2 {
    font:
        600 24px/32px 'Syne',
        sans-serif;
}
.member-gifts p {
    font-size: 12px;
    line-height: 1.7;
    color: #d6c1ca;
    margin: 8px 0;
    overflow-wrap: anywhere;
}
.member-gifts article {
    margin-top: 16px;
    padding: 20px;
    border: 1px solid #ffaedc4d;
    border-radius: 24px;
    background: #411a4e8c;
}
.member-gifts article > div {
    display: flex;
    gap: 10px;
    align-items: center;
    font-size: 16px;
    overflow-wrap: anywhere;
}
.member-gifts .material-symbols-outlined {
    color: #ffaedc;
}
.gift-status {
    display: inline-block;
    color: #ffaedc;
    font-size: 11px;
    margin-top: 12px;
}
.gift-pagination {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    color: #ffaedc;
    font-size: 12px;
}
.gift-pagination a {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
}
</style>

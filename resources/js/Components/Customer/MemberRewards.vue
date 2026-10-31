<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { redemptionLabels } from '../../utils/loyalty';
import AnimatedPoints from './AnimatedPoints.vue';
const props = defineProps<{ customer: any; balance: number; rewards: any[]; redemptions: any[]; historyPagination?: any }>();
const filter = ref('');
const filters = [
    { value: '', label: 'Todas' },
    { value: 'service', label: 'Servicios' },
    { value: 'discount', label: 'Descuentos' },
    { value: 'gift', label: 'Regalos' },
];
const visible = computed(() => props.rewards.filter((r) => !filter.value || r.benefit_type === filter.value));
const active = computed(() => props.redemptions.filter((r) => r.status === 'reserved'));
const history = computed(() => props.redemptions.filter((r) => r.status !== 'reserved'));
const form = useForm<{ operation_token: string }>({ operation_token: crypto.randomUUID() });
const operationTokens = new Map<number, string>();
const recentlyReserved = ref<number | null>(null);
let feedbackTimer: ReturnType<typeof setTimeout> | undefined;
onUnmounted(() => clearTimeout(feedbackTimer));
const number = (n: number) => new Intl.NumberFormat('en-US').format(n);
const category = (r: any) => filters.find((f) => f.value === r.benefit_type)?.label ?? 'Recompensa';
const imageFor = (r: any) =>
    `/images/stitch/rewards-${
        r.benefit_type === 'gift'
            ? 4
            : r.benefit_type === 'discount'
              ? 3
              : 2 +
                (Math.max(
                    0,
                    props.rewards.findIndex((item) => item.id === r.id),
                ) %
                    4)
    }.webp`;
const redemptionImage = (r: any) => {
    const reward = props.rewards.find((item) => item.id === r.loyalty_reward_id);
    return reward
        ? imageFor(reward)
        : `/images/stitch/rewards-${r.reward_snapshot.benefit_type === 'service' ? 2 : r.reward_snapshot.benefit_type === 'discount' ? 3 : 1}.webp`;
};
const dayFormatter = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Tegucigalpa' });
const today = ref(dayFormatter.format(new Date()));
watch(
    () => props.rewards,
    () => {
        today.value = dayFormatter.format(new Date());
    },
);
const expiry = (v: string) => new Intl.DateTimeFormat('es-HN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Tegucigalpa' }).format(new Date(v));
function reason(r: any) {
    if (!props.customer.email_verified_at) return 'Verifica tu correo para reservar';
    if (r.per_customer_limit && r.used_by_customer >= r.per_customer_limit) return 'Ya alcanzaste el límite de canjes';
    if (r.stock !== null && r.stock < 1) return 'Sin existencias disponibles';
    if (r.starts_on && r.starts_on.slice(0, 10) > today.value) return 'Disponible próximamente';
    if (r.ends_on && r.ends_on.slice(0, 10) < today.value) return 'La disponibilidad ha finalizado';
    if (r.birthday_only && (!props.customer.birthday || props.customer.birthday.slice(5, 7) !== today.value.slice(5, 7))) return 'Solo en tu mes de cumpleaños';
    if (props.balance < r.points_cost) return `Te faltan ${number(r.points_cost - props.balance)} pts para desbloquear`;
    return '';
}
function reserve(r: any) {
    if (form.processing || recentlyReserved.value === r.id || reason(r)) return;
    // Keep the same token after a lost response; retrying must not reserve twice.
    if (!operationTokens.has(r.id)) operationTokens.set(r.id, crypto.randomUUID());
    form.operation_token = operationTokens.get(r.id)!;
    form.clearErrors();
    form.post(`/clientes/recompensas/${r.id}/canjes`, {
        preserveScroll: true,
        onSuccess: () => {
            operationTokens.delete(r.id);
            recentlyReserved.value = r.id;
            clearTimeout(feedbackTimer);
            feedbackTimer = setTimeout(() => (recentlyReserved.value = null), 700);
        },
        onNetworkError: () => {
            form.setError('operation_token', 'No pudimos comprobar la reserva. Revisa tu conexión y vuelve a intentarlo; no se duplicará el canje.');
            return false;
        },
        onHttpException: () => {
            form.setError('operation_token', 'No pudimos comprobar la reserva. Recarga tus canjes o vuelve a intentarlo.');
            return false;
        },
    });
}
</script>
<template>
    <div class="space-y-6">
        <section class="liquid-glass-card specular-rim rounded-lg p-5 relative overflow-hidden" data-motion-reveal>
            <span data-motion-sheen aria-hidden="true" />
            <div
                class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-gradient-to-br from-primary/20 via-primary-container/10 to-transparent blur-2xl pointer-events-none"
                aria-hidden="true"
            />
            <div class="flex items-start justify-between relative z-10 mb-3 reward-summary-heading">
                <div>
                    <p class="text-label-sm font-label-sm text-secondary tracking-widest">MI PRIVILEGIO LEMUS</p>
                    <h1 class="text-headline-xl-mobile font-headline-xl-mobile text-on-surface font-semibold tracking-tight mt-0.5">Saldo Canjeable</h1>
                </div>
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary-container/40 border border-primary/30 backdrop-blur-md">
                    <span class="material-symbols-outlined text-primary text-[14px]">arrow_back_ios_new</span
                    ><span class="text-label-sm font-label-sm text-primary tracking-wide">Disponibles para canje</span>
                </div>
            </div>
            <div class="flex items-baseline gap-2 relative z-10 my-1 reward-summary-amount">
                <span class="text-display-lg-mobile font-display-lg-mobile text-on-surface font-bold tracking-tight"><AnimatedPoints :value="balance" /></span
                ><span class="text-label-lg font-label-lg text-primary tracking-wider font-semibold">PTS ACUMULADOS</span>
            </div>
            <div class="mt-4 pt-3.5 border-t border-outline-variant/30 flex items-start gap-2.5 relative z-10">
                <span class="material-symbols-outlined text-secondary text-[18px] shrink-0 mt-0.5">info</span>
                <p class="text-label-md font-label-md text-on-surface-variant leading-relaxed">
                    Las reservas tienen vigencia de 24 horas y el personal confirma la entrega física en tu visita al atelier.
                </p>
            </div>
        </section>
        <p v-if="form.hasErrors" class="feedback feedback-error" role="alert">{{ Object.values(form.errors).join(' ') }}</p>
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-1.5 h-3.5 rounded-full bg-primary" />
                    <h2 class="text-label-lg font-label-lg text-on-surface font-semibold tracking-wide">Mis Canjes Activos</h2>
                </div>
                <span class="text-label-sm font-label-sm text-primary tracking-wider bg-surface-container px-2 py-0.5 rounded-full"
                    >{{ active.length }} {{ active.length === 1 ? 'ACTIVO' : 'ACTIVOS' }}</span
                >
            </div>
            <div v-for="r in active" :key="r.id" class="liquid-glass-card-interactive specular-rim rounded-lg p-4 relative overflow-hidden" data-motion-reveal>
                <div class="flex gap-3.5">
                    <div class="w-20 h-20 rounded-DEFAULT overflow-hidden shrink-0 bg-surface-container-high border border-outline-variant/40 relative">
                        <img class="w-full h-full object-cover" :src="redemptionImage(r)" alt="" />
                    </div>
                    <div class="flex-1 flex flex-col justify-between">
                        <div>
                            <span
                                class="text-label-sm font-label-sm px-2 py-0.5 rounded-full bg-primary-container/50 text-on-primary-container border border-primary/20"
                                >Reservado - Pendiente de entrega</span
                            >
                            <h3 class="text-body-lg font-body-lg font-semibold text-on-surface mt-1 tracking-tight">{{ r.reward_snapshot.name }}</h3>
                            <span class="text-label-md font-label-md text-primary font-medium"
                                >{{ number(r.points_cost ?? r.reward_snapshot.points_cost ?? 0) }} pts canjeados</span
                            >
                        </div>
                    </div>
                </div>
                <div class="mt-3.5 pt-3 border-t border-outline-variant/25 flex items-center justify-between bg-surface-container-low/40 -mx-4 px-4 py-2.5">
                    <div class="flex items-center gap-1.5 text-secondary">
                        <span class="material-symbols-outlined text-[16px] text-primary">schedule</span
                        ><span class="text-label-sm font-label-sm font-medium"
                            >Vence: <strong class="text-on-surface font-semibold">{{ expiry(r.expires_at) }}</strong></span
                        >
                    </div>
                </div>
                <p class="mt-3 text-label-sm font-label-sm text-on-surface-variant flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[14px] text-outline">verified_user</span>Indica el código {{ r.id }} al personal para confirmar
                    la entrega.
                </p>
            </div>
            <p v-if="!active.length" class="empty-rewards">Tus recompensas reservadas aparecerán aquí.</p>
        </section>
        <section class="space-y-4">
            <div class="flex items-center justify-between pt-2">
                <div class="flex items-center gap-2">
                    <span class="w-1.5 h-3.5 rounded-full bg-secondary" />
                    <h2 class="text-headline-md font-headline-md text-on-surface tracking-tight">Catálogo de Recompensas</h2>
                </div>
                <span class="text-label-sm font-label-sm text-on-surface-variant tracking-wider reward-count">{{ rewards.length }} DISPONIBLES</span>
            </div>
            <div class="reward-filters" aria-label="Categorías de recompensas">
                <button
                    v-for="f in filters"
                    :key="f.value"
                    type="button"
                    :aria-pressed="filter === f.value"
                    :class="[
                        'shrink-0 px-4 py-1.5 rounded-full font-label-md text-label-md specular-rim',
                        filter === f.value ? 'bg-primary-container text-on-primary-container font-semibold' : 'bg-surface-container/60 text-on-surface-variant',
                    ]"
                    @click="filter = f.value"
                >
                    {{ f.label }}
                </button>
            </div>
            <TransitionGroup name="reward-collection" tag="div" class="space-y-4 reward-collection">
                <article v-for="r in visible" :key="r.id" class="liquid-glass-card specular-rim rounded-lg p-4 relative overflow-hidden" data-motion-reveal>
                    <div class="flex gap-4">
                        <div class="w-24 h-28 rounded-DEFAULT overflow-hidden shrink-0 relative bg-surface-container-high border border-outline-variant/40">
                            <img class="w-full h-full object-cover" :src="imageFor(r)" alt="" loading="lazy" decoding="async" width="96" height="112" />
                            <div v-if="reason(r)" class="absolute inset-0 bg-surface-container-lowest/50 flex items-center justify-center">
                                <span class="material-symbols-outlined text-on-surface-variant text-[22px]">lock</span>
                            </div>
                        </div>
                        <div class="flex-1 flex flex-col justify-between reward-content">
                            <div>
                                <div class="flex items-center justify-between gap-1 mb-1">
                                    <span class="text-label-sm font-label-sm text-secondary tracking-widest uppercase">{{ category(r) }}</span
                                    ><span
                                        v-if="!reason(r)"
                                        data-motion-reveal
                                        data-motion-kind="unlock"
                                        class="inline-flex items-center gap-1 text-label-sm font-label-sm px-2 py-0.5 rounded-full bg-primary/20 text-primary border border-primary/30"
                                        ><span class="material-symbols-outlined text-[12px]">check_circle</span>Listo para canjear</span
                                    ><span v-else class="text-label-sm font-label-sm text-on-surface-variant bg-surface-container px-2 py-0.5 rounded-full"
                                        >Bloqueada</span
                                    >
                                </div>
                                <h3 class="text-body-lg font-body-lg font-semibold text-on-surface tracking-tight leading-snug">{{ r.name }}</h3>
                            </div>
                            <div class="mt-2">
                                <div class="flex items-baseline justify-between mb-1.5">
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-headline-md font-headline-md text-primary font-bold">{{ number(r.points_cost) }}</span
                                        ><span class="text-label-sm font-label-sm text-secondary">pts</span>
                                    </div>
                                    <span class="text-label-sm font-label-sm text-on-surface-variant font-medium">{{
                                        balance >= r.points_cost
                                            ? 'Te quedan ' + number(balance - r.points_cost) + ' pts'
                                            : Math.round((Math.max(0, balance) / r.points_cost) * 100) + '%'
                                    }}</span>
                                </div>
                                <div v-if="balance < r.points_cost" class="w-full h-1.5 rounded-full bg-surface-container-highest overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-primary-container to-primary"
                                        :style="{ width: Math.min(100, (Math.max(0, balance) / r.points_cost) * 100) + '%' }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    <button
                        :class="[
                            'mt-3.5 w-full py-2.5 px-4 rounded-full flex items-center justify-center gap-2 specular-rim',
                            reason(r)
                                ? 'bg-surface-container/60 text-on-surface-variant/80 font-label-md text-label-md'
                                : 'bg-gradient-to-r from-primary-container via-[#9e447e] to-primary text-on-surface font-label-lg text-label-lg font-semibold',
                        ]"
                        type="button"
                        :disabled="!!reason(r) || form.processing || recentlyReserved === r.id"
                        @click="reserve(r)"
                    >
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ reason(r) ? 'lock' : 'lock_open' }}</span
                        ><span>{{
                            form.processing
                                ? 'Reservando…'
                                : recentlyReserved === r.id
                                  ? 'Reservada ✓'
                                  : reason(r)
                                    ? 'No disponible: ' + reason(r)
                                    : 'Reservar recompensa (24h)'
                        }}</span>
                    </button>
                </article>
            </TransitionGroup>
            <p v-if="!visible.length" class="empty-rewards">No hay recompensas disponibles en esta categoría.</p>
        </section>
        <section v-if="history.length" class="space-y-3">
            <h2 class="text-headline-md font-headline-md">Mis canjes anteriores</h2>
            <div v-for="r in history" :key="r.id" class="liquid-glass-card rounded-lg p-4">
                <strong class="text-body-md font-body-md">{{ r.reward_snapshot.name }}</strong>
                <p class="text-label-md font-label-md text-secondary">{{ redemptionLabels[r.status] }} · Código {{ r.id }}</p>
            </div>
            <nav v-if="historyPagination?.last_page > 1" aria-label="Páginas de canjes anteriores" class="history-pagination">
                <Link v-if="historyPagination.prev_page_url" :href="historyPagination.prev_page_url" preserve-scroll>← Anteriores</Link
                ><span>{{ historyPagination.current_page }} / {{ historyPagination.last_page }}</span
                ><Link v-if="historyPagination.next_page_url" :href="historyPagination.next_page_url" preserve-scroll>Siguientes →</Link>
            </nav>
        </section>
    </div>
</template>
<style scoped>
.empty-rewards {
    padding: 16px;
    font-size: 12px;
    line-height: 20px;
    color: #d6c1ca;
    border: 1px solid #52434a4d;
    border-radius: 16px;
}
.reward-collection {
    position: relative;
}
.history-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    font-size: 12px;
    padding: 12px 0;
}
.history-pagination a {
    padding: 10px;
    border: 1px solid #52434a;
    border-radius: 12px;
    color: #ffaedc;
}
.reward-content {
    min-width: 0;
}
.reward-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.reward-summary-heading {
    flex-direction: column;
    gap: 12px;
}
.reward-summary-heading h1 {
    overflow-wrap: normal;
}
.reward-summary-amount {
    flex-wrap: wrap;
}
.reward-count {
    flex-shrink: 0;
    white-space: nowrap;
}
.reward-count::first-letter {
    color: #ffaedc;
}
.reward-filters button {
    min-height: 44px;
}
.reward-content > div > .flex {
    flex-wrap: wrap;
    gap: 6px;
}
.reward-collection article button {
    min-height: 48px;
}
.reward-collection article h3 {
    overflow-wrap: anywhere;
}
@media (max-width: 360px) {
    .reward-collection article > .flex {
        flex-direction: column;
    }
    .reward-collection article > .flex > div:first-child {
        width: 100%;
        height: 136px;
    }
}
</style>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AnimatedPoints from './AnimatedPoints.vue';
defineProps<{
    customer: any;
    balance: number;
    qualifying: number;
    visits: number;
    level: any;
    nextLevel: any;
    progress: number;
    reward: any;
    qrBusy: boolean;
}>();
defineEmits<{ qr: [] }>();
const number = (n: number) => new Intl.NumberFormat('en-US').format(n);
</script>
<template>
    <div class="space-y-5">
        <section class="space-y-1 pt-1" data-motion-reveal>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-variant/40 border border-outline-variant/40 backdrop-blur-md">
                <span class="material-symbols-outlined text-[14px] text-primary">arrow_back_ios_new</span
                ><span class="text-label-sm font-label-sm text-secondary">{{ level?.name ?? 'Miembro Lemus' }}</span>
            </div>
            <h1 class="text-headline-xl-mobile font-headline-xl-mobile text-on-surface font-semibold tracking-tight">
                Hola, {{ customer.name.split(' ')[0] }}
            </h1>
            <p class="text-body-md font-body-md text-on-surface-variant">Bienvenida a tu espacio de cuidado y arquitectura de uñas.</p>
        </section>
        <section class="liquid-glass rounded-lg p-5 relative overflow-hidden" data-motion-reveal>
            <span data-motion-sheen aria-hidden="true" />
            <div class="absolute -right-8 -top-8 w-32 h-32 bg-primary/20 rounded-full blur-2xl pointer-events-none" aria-hidden="true" />
            <div class="relative z-10 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-label-md font-label-md text-secondary tracking-wider">SALDO CANJEABLE</span>
                    <div class="flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-primary-container/40 border border-primary/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-primary" /><span class="text-label-sm font-label-sm text-primary">Disponibles hoy</span>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-display-lg-mobile font-display-lg-mobile text-on-surface tracking-tight" data-testid="loyalty-balance"
                        ><AnimatedPoints :value="balance" /></span
                    ><span class="text-headline-md font-headline-md text-primary font-medium">pts</span>
                </div>
                <p class="mt-2 text-body-md font-body-md text-on-surface-variant leading-relaxed">
                    Puntos listos para canjear de inmediato por servicios especializados, acabados couture o detalles en cabina.
                </p>
            </div>
        </section>
        <section>
            <button
                class="w-full min-h-[58px] specular-pill rounded-lg p-4 flex items-center justify-between gap-3 text-left transition-all duration-200 active:scale-[0.98] border border-white/20 group cursor-pointer home-qr-trigger"
                type="button"
                :disabled="qrBusy"
                @click="$emit('qr')"
            >
                <span data-motion-sheen aria-hidden="true" />
                <div class="flex items-center gap-3.5">
                    <div
                        class="w-11 h-11 rounded-full bg-surface-container-lowest/40 backdrop-blur-md flex items-center justify-center border border-white/20 text-on-primary-container"
                    >
                        <span class="material-symbols-outlined text-[24px]">qr_code_scanner</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-label-lg font-label-lg text-white font-semibold tracking-wide">{{ qrBusy ? 'Generando…' : 'Mostrar mi QR' }}</span
                            ><span class="text-[10px] font-label-sm px-2 py-0.5 rounded-full bg-white/20 text-white">Táctil</span>
                        </div>
                        <p class="text-label-sm font-label-sm text-on-primary-container/90 mt-0.5">
                            Código temporal para identificarte y sumar puntos en tu visita
                        </p>
                    </div>
                </div>
                <span class="material-symbols-outlined text-white/80 text-[20px]">arrow_forward_ios</span>
            </button>
        </section>
        <section class="liquid-glass rounded-lg p-5 space-y-4" data-motion-reveal>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full bg-primary-container/50 border border-primary/40 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1">diamond</span>
                    </div>
                    <div>
                        <span class="text-label-sm font-label-sm text-on-surface-variant block">Categoría de Membresía</span
                        ><span class="text-headline-md font-headline-md text-on-surface font-semibold text-[18px]">{{ level?.name }}</span>
                    </div>
                </div>
                <Link
                    href="/clientes/progreso"
                    class="text-label-sm font-label-sm text-secondary bg-surface-container-high px-2.5 py-1 rounded-full border border-outline-variant/30 membership-caption"
                    >{{ visits > 1 ? 'Frecuente' : 'Miembro' }}</Link
                >
            </div>
            <div class="space-y-2 pt-1">
                <div class="flex justify-between items-baseline text-label-md font-label-md">
                    <span class="text-on-surface-variant font-medium"
                        >Próxima meta: <span class="text-primary font-semibold">{{ nextLevel?.name ?? 'Nivel máximo' }}</span></span
                    ><span class="text-on-surface font-semibold"
                        >{{ number(qualifying) }}
                        <span class="text-secondary font-normal">{{ nextLevel ? '/ ' + number(nextLevel.points) : '' }} pts</span></span
                    >
                </div>
                <div
                    class="w-full h-3 shimmer-track rounded-full p-0.5 overflow-hidden border border-outline-variant/30"
                    role="progressbar"
                    :aria-valuenow="Math.round(progress)"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="Progreso de nivel"
                >
                    <div
                        data-motion-progress
                        class="h-full rounded-full bg-gradient-to-r from-primary-container via-primary to-primary-fixed"
                        :style="{ width: progress + '%' }"
                    />
                </div>
                <div class="flex justify-between text-label-sm font-label-sm text-secondary pt-0.5">
                    <span>{{ Math.round(progress) }}% alcanzado</span
                    ><span>{{ nextLevel ? 'Faltan ' + number(Math.max(0, nextLevel.points - qualifying)) + ' pts' : 'Meta alcanzada' }}</span>
                </div>
            </div>
            <div class="rounded-DEFAULT bg-surface-container-lowest/60 border border-outline-variant/40 p-3 flex gap-2.5 items-start">
                <span class="material-symbols-outlined text-primary text-[18px] shrink-0 mt-0.5">info</span>
                <p class="text-label-sm font-label-sm text-on-surface-variant leading-relaxed">
                    <strong class="text-on-surface font-semibold">Nota didáctica:</strong> Los puntos calificables determinan tu nivel y se calculan sobre tus
                    visitas; son independientes del saldo disponible para canjear.
                </p>
            </div>
        </section>
        <section v-if="reward" class="liquid-glass-elevated rounded-lg p-5 relative overflow-hidden space-y-3.5" data-motion-reveal>
            <div class="flex items-center justify-between">
                <div class="inline-flex items-center gap-1.5 text-primary">
                    <span class="material-symbols-outlined text-[16px]">lock_open</span
                    ><span class="text-label-sm font-label-sm font-semibold tracking-wider">PRÓXIMA RECOMPENSA DESBLOQUEABLE</span>
                </div>
                <span class="text-label-sm font-label-sm text-primary bg-primary/10 border border-primary/25 px-2 py-0.5 rounded-full">{{
                    reward.points_cost > balance ? 'A ' + number(reward.points_cost - balance) + ' pts' : 'Disponible'
                }}</span>
            </div>
            <div class="flex items-center gap-3.5">
                <div class="w-14 h-14 rounded-DEFAULT overflow-hidden shrink-0 border border-outline-variant/50 bg-surface-container-high">
                    <img v-if="reward.image_url" class="w-full h-full object-cover" :src="reward.image_url" alt="" /><span
                        v-else
                        class="material-symbols-outlined"
                        aria-hidden="true"
                        >card_giftcard</span
                    >
                </div>
                <div class="space-y-0.5">
                    <h3 class="text-label-lg font-label-lg text-on-surface font-semibold">{{ reward.name }}</h3>
                    <p class="text-label-sm font-label-sm text-on-surface-variant">{{ reward.description }}</p>
                </div>
            </div>
            <Link
                class="w-full min-h-[48px] rounded-full bg-surface-variant/40 hover:bg-surface-variant/60 border border-outline-variant/50 flex items-center justify-center gap-2 text-label-md font-label-md text-on-surface font-semibold"
                href="/clientes/recompensas"
                ><span>Explorar catálogo de recompensas</span><span class="material-symbols-outlined text-[18px] text-primary">arrow_forward</span></Link
            >
        </section>
        <section v-else class="liquid-glass rounded-lg p-5 text-body-md font-body-md text-on-surface-variant">
            Pronto encontrarás nuevas recompensas aquí.
        </section>
    </div>
</template>
<style scoped>
.membership-caption {
    white-space: nowrap;
    flex-shrink: 0;
}
.home-qr-trigger {
    position: relative;
    overflow: hidden;
}
.home-qr-trigger > .flex {
    position: relative;
    min-width: 0;
}
@media (max-width: 360px) {
    section[data-motion-reveal] > .flex {
        flex-wrap: wrap;
        gap: 10px;
    }
    section[data-motion-reveal] > .space-y-2 > .flex {
        flex-wrap: wrap;
        gap: 8px;
    }
}
</style>

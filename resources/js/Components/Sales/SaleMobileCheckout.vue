<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatHnl } from '../../utils/money';

const props = defineProps<{
    servicesCount: number;
    chargeCents: number;
    checkoutLabel: string;
    processing?: boolean;
    checkoutDisabled?: boolean;
}>();

const emit = defineEmits<{
    checkout: [];
    height: [value: number];
}>();

const open = ref(false);
const bar = ref<HTMLElement | null>(null);
let observer: ResizeObserver | undefined;

function reportHeight(): void {
    emit('height', bar.value?.getBoundingClientRect().height ?? 0);
}

function close(): void {
    open.value = false;
}

function checkout(): void {
    if (props.processing || props.checkoutDisabled) return;
    close();
    emit('checkout');
}

onMounted(() => {
    observer = new ResizeObserver(reportHeight);
    if (bar.value) observer.observe(bar.value);
    void nextTick(reportHeight);
});

watch(() => [props.servicesCount, props.chargeCents, props.checkoutDisabled], () => void nextTick(reportHeight));
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div ref="bar" class="sale-mobile-checkout" data-testid="sale-mobile-checkout">
        <VBtn variant="text" class="sale-mobile-checkout__summary" data-testid="sale-mobile-summary" @click="open = true">
            <span class="text-left">
                <strong>{{ servicesCount }} {{ servicesCount === 1 ? 'servicio' : 'servicios' }}</strong>
                <small>Ver resumen</small>
            </span>
        </VBtn>
        <VBtn color="primary" :loading="processing" :disabled="processing || checkoutDisabled" data-testid="sale-mobile-checkout-button" @click="checkout">
            {{ checkoutLabel }} {{ formatHnl(chargeCents) }}
        </VBtn>
    </div>

    <VBottomSheet v-model="open" :disabled="processing" max-height="88dvh" class="sale-mobile-checkout-sheet">
        <VCard class="sale-mobile-checkout-sheet__card">
            <VCardActions class="justify-end px-4 pt-3 pb-0">
                <VBtn variant="text" prepend-icon="mdi-close" :disabled="processing" @click="close">Cerrar resumen</VBtn>
            </VCardActions>
            <div class="sale-mobile-checkout-sheet__content">
                <slot />
            </div>
        </VCard>
    </VBottomSheet>
</template>

<style scoped>
.sale-mobile-checkout {
    position: fixed;
    z-index: 5;
    right: 0;
    bottom: 0;
    left: 0;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    padding: 12px calc(16px + env(safe-area-inset-right, 0px)) calc(12px + env(safe-area-inset-bottom, 0px)) calc(16px + env(safe-area-inset-left, 0px));
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.1);
    background: rgb(var(--v-theme-surface));
    box-shadow: 0 -10px 35px rgba(55, 38, 44, 0.12);
}

.sale-mobile-checkout__summary { min-width: 0; justify-content: flex-start; }
.sale-mobile-checkout__summary span { display: grid; min-width: 0; }
.sale-mobile-checkout__summary small { color: rgb(var(--v-theme-on-surface-variant)); }
.sale-mobile-checkout-sheet__card { max-height: 88vh; max-height: 88dvh; }
.sale-mobile-checkout-sheet__content { max-height: calc(88dvh - 64px); overflow-y: auto; padding-bottom: env(safe-area-inset-bottom, 0px); }

@media (max-width: 420px) {
    .sale-mobile-checkout { grid-template-columns: 1fr; }
    .sale-mobile-checkout__summary { justify-content: center; }
}

@media (orientation: landscape) and (max-height: 500px) {
    .sale-mobile-checkout-sheet__card { max-height: 100vh; max-height: 100dvh; }
    .sale-mobile-checkout-sheet__content { max-height: calc(100dvh - 64px); }
}
</style>

<script setup lang="ts">
import type { PaymentMethod, SaleCartItem } from '../../types/sales';

defineProps<{
    items: SaleCartItem[];
    totalCents: number;
    totalServices: number;
    paymentMethod: PaymentMethod;
    cardFeeCents: number;
    netAmountCents: number;
    processing?: boolean;
}>();

defineEmits<{
    increase: [id: number];
    decrease: [id: number];
    remove: [id: number];
    paymentMethod: [value: PaymentMethod];
    checkout: [];
}>();

const cents = (value: string) => {
    const [whole, fraction = ''] = value.split('.');
    return (Number(whole) * 100) + Number(fraction.padEnd(2, '0').slice(0, 2));
};
const money = (value: number) => new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
}).format(value / 100);
</script>

<template>
    <VCard class="sale-cart surface-card">
        <VCardItem class="pa-5 pb-3">
            <VCardTitle class="text-h6 font-weight-bold">Resumen</VCardTitle>
            <VCardSubtitle>{{ totalServices }} {{ totalServices === 1 ? 'servicio' : 'servicios' }}</VCardSubtitle>
        </VCardItem>

        <VDivider />

        <VCardText v-if="!items.length" class="pa-7 text-center">
            <VAvatar color="surface-variant" size="58" class="mb-4">
                <VIcon icon="mdi-receipt-text-plus-outline" color="primary" size="28" />
            </VAvatar>
            <h3 class="text-body-1 font-weight-bold mb-2">Aún no hay servicios</h3>
            <p class="text-body-2 text-medium-emphasis mb-0">Agrega los servicios realizados para preparar la venta.</p>
        </VCardText>

        <VList v-else class="sale-cart__items pa-3" lines="three">
            <VListItem v-for="item in items" :key="item.id" class="sale-cart__item mb-2" rounded="lg">
                <VListItemTitle class="font-weight-bold text-wrap">{{ item.name }}</VListItemTitle>
                <VListItemSubtitle class="mt-1">
                    {{ money(cents(item.price)) }} c/u · {{ money(cents(item.price) * item.quantity) }}
                </VListItemSubtitle>
                <div class="d-flex align-center justify-space-between ga-2 mt-3">
                    <div class="d-flex align-center ga-1">
                        <VBtn
                            icon="mdi-minus"
                            size="small"
                            variant="outlined"
                            :disabled="processing"
                            :aria-label="`Disminuir ${item.name}`"
                            @click="$emit('decrease', item.id)"
                        />
                        <span class="sale-cart__quantity" :aria-label="`Cantidad ${item.quantity}`">{{ item.quantity }}</span>
                        <VBtn
                            icon="mdi-plus"
                            size="small"
                            variant="outlined"
                            :disabled="processing || item.quantity >= 50"
                            :aria-label="`Aumentar ${item.name}`"
                            @click="$emit('increase', item.id)"
                        />
                    </div>
                    <VBtn
                        icon="mdi-delete-outline"
                        size="small"
                        variant="text"
                        color="error"
                        :disabled="processing"
                        :aria-label="`Eliminar ${item.name}`"
                        @click="$emit('remove', item.id)"
                    />
                </div>
            </VListItem>
        </VList>

        <template v-if="items.length">
            <VDivider />
            <VCardText class="pa-5">
                <div class="d-flex justify-space-between text-body-2 text-medium-emphasis mb-2">
                    <span>Total de servicios</span>
                    <strong class="text-high-emphasis">{{ totalServices }}</strong>
                </div>
                <VSwitch
                    :model-value="paymentMethod === 'card'"
                    label="Pago con tarjeta"
                    color="primary"
                    inset
                    hide-details
                    class="mb-3"
                    :disabled="processing"
                    @update:model-value="$emit('paymentMethod', $event ? 'card' : 'cash')"
                />
                <div class="d-flex justify-space-between text-body-2 mb-2">
                    <span>Método</span>
                    <strong>{{ paymentMethod === 'card' ? 'Tarjeta' : 'Efectivo' }}</strong>
                </div>
                <template v-if="paymentMethod === 'card'">
                    <div class="d-flex justify-space-between text-body-2 mb-2">
                        <span>Comisión POS 4%</span>
                        <strong>{{ money(cardFeeCents) }}</strong>
                    </div>
                    <div class="d-flex justify-space-between text-body-2 mb-4">
                        <span>Ingreso neto estimado</span>
                        <strong>{{ money(netAmountCents) }}</strong>
                    </div>
                </template>
                <div class="d-flex justify-space-between align-end mb-5">
                    <span class="text-body-1 font-weight-bold">Total a cobrar</span>
                    <span class="text-h5 font-weight-bold text-primary">{{ money(totalCents) }}</span>
                </div>
                <VBtn
                    block
                    color="primary"
                    size="large"
                    prepend-icon="mdi-check-circle-outline"
                    :loading="processing"
                    :disabled="processing"
                    @click="$emit('checkout')"
                >
                    Cobrar {{ money(totalCents) }}
                </VBtn>
            </VCardText>
        </template>
    </VCard>
</template>

<style scoped>
.sale-cart__items {
    max-height: min(48dvh, 470px);
    overflow-y: auto;
}

.sale-cart__item {
    border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
    background: rgba(var(--v-theme-surface-variant), 0.35);
}

.sale-cart__quantity {
    min-width: 34px;
    text-align: center;
    font-weight: 800;
}
</style>

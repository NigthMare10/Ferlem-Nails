<script setup lang="ts">
import type { PaymentMethod, SaleCartItem } from '../../types/sales';
import { decimalToCents, formatHnl } from '../../utils/money';
import SaleCheckoutSummary from './SaleCheckoutSummary.vue';
import SaleLineItem from './SaleLineItem.vue';
import SalePaymentMethod from './SalePaymentMethod.vue';

defineProps<{
    items: SaleCartItem[];
    totalCents: number;
    totalServices: number;
    paymentMethod: PaymentMethod;
    cardFeeCents: number;
    netAmountCents: number;
    processing?: boolean;
    paymentProof?: File | null;
    proofError?: string;
}>();

defineEmits<{
    increase: [id: number];
    decrease: [id: number];
    remove: [id: number];
    paymentMethod: [value: PaymentMethod];
    paymentProof: [value: File | null];
    checkout: [];
}>();

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

        <div v-else class="sale-cart__items pa-3">
            <SaleLineItem v-for="item in items" :key="item.id" :item-key="item.id" :name="item.name" :price="item.price" :quantity="item.quantity" :duration-minutes="item.duration_minutes" :processing="processing" @increase="$emit('increase', item.id)" @decrease="$emit('decrease', item.id)" @remove="$emit('remove', item.id)" />
        </div>

        <template v-if="items.length">
            <VDivider />
            <VCardText class="pa-5">
                <SalePaymentMethod :model-value="paymentMethod" :amount-cents="totalCents" :processing="processing" :payment-proof="paymentProof" :proof-error="proofError" @update:model-value="$emit('paymentMethod', $event)" @update:payment-proof="$emit('paymentProof', $event)" />
                <SaleCheckoutSummary :total-cents="totalCents" :total-services="totalServices" :payment-method="paymentMethod" :balance-cents="totalCents" :balance-fee-cents="cardFeeCents" :total-fee-cents="cardFeeCents" :net-amount-cents="netAmountCents" />
                <div class="d-flex justify-space-between align-end mb-5">
                    <span class="text-body-1 font-weight-bold">Total a cobrar</span>
                    <span class="text-h5 font-weight-bold text-primary">{{ formatHnl(totalCents) }}</span>
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
                    Cobrar {{ formatHnl(totalCents) }}
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

</style>

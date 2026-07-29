<script setup lang="ts">
import { useDisplay } from 'vuetify';
import type { PaymentMethod, SaleCartItem } from '../../types/sales';
import { decimalToCents, formatHnl } from '../../utils/money';
import SaleCheckoutSummary from './SaleCheckoutSummary.vue';
import SalePaymentMethod from './SalePaymentMethod.vue';

defineProps<{
    modelValue: boolean;
    items: SaleCartItem[];
    totalCents: number;
    totalServices: number;
    paymentMethod: PaymentMethod;
    cardFeeCents: number;
    netAmountCents: number;
    processing: boolean;
    error?: string;
    appointmentMode?: boolean;
    depositCents?: number;
    depositFeeCents?: number;
    balanceCents?: number;
    balanceFeeCents?: number;
    paymentProof?: File | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    'update:paymentMethod': [value: PaymentMethod];
    'update:paymentProof': [value: File | null];
    confirm: [];
}>();
const { xs } = useDisplay();

</script>

<template>
    <VDialog
        :model-value="modelValue"
        :fullscreen="xs"
        :persistent="processing"
        max-width="620"
        @update:model-value="!processing && emit('update:modelValue', $event)"
    >
        <VCard class="confirm-sale-dialog">
            <VCardItem class="pa-5 pa-sm-6 pb-3">
                <template #prepend>
                    <VAvatar color="primary" variant="tonal" size="48">
                        <VIcon icon="mdi-receipt-text-check-outline" size="25" />
                    </VAvatar>
                </template>
                <VCardTitle class="text-h6 font-weight-bold">{{ appointmentMode ? 'Completar cita y cobrar' : 'Confirmar venta' }}</VCardTitle>
                <VCardSubtitle>Revisa los servicios antes de generar el recibo.</VCardSubtitle>
            </VCardItem>

            <VCardText class="pa-5 pa-sm-6 pt-3">
                <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
                <VList class="confirm-sale-dialog__list pa-0" lines="two">
                    <VListItem v-for="item in items" :key="item.id" class="px-0">
                        <VListItemTitle class="font-weight-bold">{{ item.name }}</VListItemTitle>
                        <VListItemSubtitle>{{ item.quantity }} × {{ formatHnl(decimalToCents(item.price)) }}</VListItemSubtitle>
                        <template #append>
                            <span class="font-weight-bold">{{ formatHnl(decimalToCents(item.price) * item.quantity) }}</span>
                        </template>
                    </VListItem>
                </VList>
                <VDivider class="my-4" />
                <SalePaymentMethod :model-value="paymentMethod" :amount-cents="appointmentMode ? (balanceCents ?? totalCents) : totalCents" :processing="processing" :balance-payment="appointmentMode && Boolean(depositCents)" :payment-proof="paymentProof" class="mb-4" @update:model-value="emit('update:paymentMethod', $event)" @update:payment-proof="emit('update:paymentProof', $event)" />
                <SaleCheckoutSummary :total-cents="totalCents" :total-services="totalServices" :payment-method="paymentMethod" :deposit-cents="depositCents" :deposit-fee-cents="depositFeeCents" :balance-cents="appointmentMode ? (balanceCents ?? totalCents) : totalCents" :balance-fee-cents="balanceFeeCents ?? cardFeeCents" :total-fee-cents="cardFeeCents" :net-amount-cents="netAmountCents" />
                <div class="d-flex justify-space-between align-end">
                    <span class="text-body-1 font-weight-bold">{{ appointmentMode ? 'Saldo final a cobrar' : 'Total a cobrar' }}</span>
                    <span class="text-h5 font-weight-bold text-primary">{{ formatHnl(appointmentMode ? (balanceCents ?? totalCents) : totalCents) }}</span>
                </div>
            </VCardText>

            <VCardActions class="confirm-sale-dialog__actions pa-5 pa-sm-6 pt-0">
                <VBtn variant="outlined" :disabled="processing" @click="emit('update:modelValue', false)">Cancelar</VBtn>
                <VBtn
                    color="primary"
                    prepend-icon="mdi-check"
                    :loading="processing"
                    :disabled="processing"
                    type="button"
                    @click="emit('confirm')"
                >
                    Confirmar y generar recibo
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>

<style scoped>
.confirm-sale-dialog__list {
    max-height: 320px;
    overflow-y: auto;
}

.confirm-sale-dialog__actions {
    justify-content: flex-end;
    gap: 12px;
}

@media (max-width: 599px) {
    .confirm-sale-dialog {
        min-height: 100dvh;
        border-radius: 0 !important;
    }

    .confirm-sale-dialog__actions {
        flex-direction: column-reverse;
        margin-top: auto;
    }

    .confirm-sale-dialog__actions .v-btn {
        width: 100%;
    }
}
</style>

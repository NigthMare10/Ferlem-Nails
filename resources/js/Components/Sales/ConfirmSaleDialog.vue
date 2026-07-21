<script setup lang="ts">
import { useDisplay } from 'vuetify';
import type { PaymentMethod, SaleCartItem } from '../../types/sales';

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
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    confirm: [];
}>();
const { xs } = useDisplay();

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
                <VCardTitle class="text-h6 font-weight-bold">Confirmar venta</VCardTitle>
                <VCardSubtitle>Revisa los servicios antes de generar el recibo.</VCardSubtitle>
            </VCardItem>

            <VCardText class="pa-5 pa-sm-6 pt-3">
                <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
                <VList class="confirm-sale-dialog__list pa-0" lines="two">
                    <VListItem v-for="item in items" :key="item.id" class="px-0">
                        <VListItemTitle class="font-weight-bold">{{ item.name }}</VListItemTitle>
                        <VListItemSubtitle>{{ item.quantity }} × {{ money(cents(item.price)) }}</VListItemSubtitle>
                        <template #append>
                            <span class="font-weight-bold">{{ money(cents(item.price) * item.quantity) }}</span>
                        </template>
                    </VListItem>
                </VList>
                <VDivider class="my-4" />
                <div class="d-flex justify-space-between text-body-2 mb-2">
                    <span>Total de servicios</span>
                    <strong>{{ totalServices }}</strong>
                </div>
                <div class="d-flex justify-space-between text-body-2 mb-2">
                    <span>Método de pago</span>
                    <strong>{{ paymentMethod === 'card' ? 'Tarjeta' : 'Efectivo' }}</strong>
                </div>
                <template v-if="paymentMethod === 'card'">
                    <div class="d-flex justify-space-between text-body-2 mb-2">
                        <span>Comisión POS 4%</span>
                        <strong>{{ money(cardFeeCents) }}</strong>
                    </div>
                    <div class="d-flex justify-space-between text-body-2 mb-3">
                        <span>Ingreso neto</span>
                        <strong>{{ money(netAmountCents) }}</strong>
                    </div>
                </template>
                <div class="d-flex justify-space-between align-end">
                    <span class="text-body-1 font-weight-bold">Total a cobrar</span>
                    <span class="text-h5 font-weight-bold text-primary">{{ money(totalCents) }}</span>
                </div>
            </VCardText>

            <VCardActions class="confirm-sale-dialog__actions pa-5 pa-sm-6 pt-0">
                <VBtn variant="outlined" :disabled="processing" @click="emit('update:modelValue', false)">Cancelar</VBtn>
                <VBtn
                    color="primary"
                    prepend-icon="mdi-check"
                    :loading="processing"
                    :disabled="processing"
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

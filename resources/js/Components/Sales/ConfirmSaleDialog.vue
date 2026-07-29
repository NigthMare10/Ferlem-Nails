<script setup lang="ts">
import { useDisplay } from 'vuetify';
import type { PaymentMethod, SaleAdditionalCharge, SaleCartItem } from '../../types/sales';
import { decimalToCents, formatHnl } from '../../utils/money';

const props = defineProps<{
    modelValue: boolean;
    items: readonly SaleCartItem[];
    additionalCharges: readonly SaleAdditionalCharge[];
    serviceSubtotalCents: number;
    additionalChargesCents: number;
    subtotalCents: number;
    discountPercent: string;
    discountCents: number;
    totalCents: number;
    totalServices: number;
    paymentMethod: PaymentMethod;
    cardFeeCents: number;
    netAmountCents: number;
    processing: boolean;
    error?: string;
    appointmentMode?: boolean;
    depositCents?: number;
    balanceCents?: number;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    confirm: [];
}>();
const { xs } = useDisplay();

function paymentMethodLabel(): string {
    if (props.balanceCents === 0) return 'Cubierto por adelanto';

    return props.paymentMethod === 'card' ? 'Tarjeta' : props.paymentMethod === 'transfer' ? 'Transferencia' : 'Efectivo';
}
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
                <VCardSubtitle>Revisa el cobro final antes de generar el recibo.</VCardSubtitle>
            </VCardItem>

            <VCardText class="confirm-sale-dialog__body pa-5 pa-sm-6 pt-3">
                <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>

                <div class="confirmation-row mb-4"><span>Método de pago</span><strong>{{ paymentMethodLabel() }}</strong></div>

                <div class="d-flex justify-space-between align-center mb-2"><span class="text-overline text-primary">Servicios realizados</span><strong>{{ totalServices }}</strong></div>
                <VList class="confirm-sale-dialog__list pa-0" lines="two">
                    <VListItem v-for="item in items" :key="item.id" class="px-0">
                        <VListItemTitle class="font-weight-bold">{{ item.name }}</VListItemTitle>
                        <VListItemSubtitle>{{ item.quantity }} × {{ formatHnl(decimalToCents(item.price)) }}</VListItemSubtitle>
                        <template #append><span class="font-weight-bold">{{ formatHnl(decimalToCents(item.price) * item.quantity) }}</span></template>
                    </VListItem>
                </VList>

                <template v-if="additionalCharges.length">
                    <div class="text-overline text-primary mt-4 mb-2">Cargos adicionales</div>
                    <div v-for="(charge, index) in additionalCharges" :key="`${charge.name}-${index}`" class="confirmation-row">
                        <span>{{ charge.name }}</span><strong>{{ formatHnl(decimalToCents(String(charge.amount))) }}</strong>
                    </div>
                </template>

                <VDivider class="my-4" />
                <div class="confirmation-row"><span>Servicios</span><strong>{{ formatHnl(serviceSubtotalCents) }}</strong></div>
                <div v-if="additionalCharges.length" class="confirmation-row"><span>Cargos adicionales</span><strong>{{ formatHnl(additionalChargesCents) }}</strong></div>
                <div class="confirmation-row"><span>Subtotal</span><strong>{{ formatHnl(subtotalCents) }}</strong></div>
                <template v-if="discountCents > 0">
                    <div class="confirmation-row"><span>Descuento</span><strong>{{ discountPercent }} %</strong></div>
                    <div class="confirmation-row"><span>Monto descontado</span><strong>− {{ formatHnl(discountCents) }}</strong></div>
                </template>
                <div v-if="depositCents" class="confirmation-row"><span>Adelanto aplicado</span><strong>− {{ formatHnl(depositCents) }}</strong></div>
                <div class="confirmation-row confirmation-row--total"><span>{{ depositCents ? 'Saldo final a cobrar' : 'Total' }}</span><strong>{{ formatHnl(balanceCents ?? totalCents) }}</strong></div>
                <VDivider class="my-4" />
                <div class="confirmation-row text-body-2"><span>Comisión POS interna</span><strong>{{ formatHnl(cardFeeCents) }}</strong></div>
                <div class="confirmation-row text-body-2"><span>Ingreso neto interno</span><strong>{{ formatHnl(netAmountCents) }}</strong></div>
            </VCardText>

            <VCardActions class="confirm-sale-dialog__actions pa-5 pa-sm-6 pt-0">
                <VBtn variant="outlined" :disabled="processing" @click="emit('update:modelValue', false)">Cancelar</VBtn>
                <VBtn color="primary" prepend-icon="mdi-check" :loading="processing" :disabled="processing" type="button" @click="emit('confirm')">
                    Confirmar y generar recibo
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>

<style scoped>
.confirm-sale-dialog__list { max-height: 320px; overflow-y: auto; }
.confirm-sale-dialog__body { max-height: 70dvh; overflow-y: auto; }
.confirmation-row { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 10px; font-variant-numeric: tabular-nums; }
.confirmation-row strong { text-align: right; }
.confirmation-row--total { margin-top: 16px; font-size: 1rem; font-weight: 700; }
.confirm-sale-dialog__actions { justify-content: flex-end; gap: 12px; }

@media (max-width: 599px) {
    .confirm-sale-dialog { min-height: 100dvh; border-radius: 0 !important; }
    .confirm-sale-dialog__body { max-height: none; }
    .confirm-sale-dialog__actions { flex-direction: column-reverse; margin-top: auto; }
    .confirm-sale-dialog__actions .v-btn { width: 100%; }
}
</style>

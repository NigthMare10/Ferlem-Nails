<script setup lang="ts">
import type { PaymentMethod, SaleAdditionalCharge } from '../../types/sales';
import { formatHnl } from '../../utils/money';
import SaleCheckoutAdjustments from './SaleCheckoutAdjustments.vue';

defineProps<{
    serviceSubtotalCents: number;
    additionalChargesCents: number;
    subtotalCents: number;
    discountCents: number;
    totalCents: number;
    totalServices: number;
    paymentMethod: PaymentMethod;
    balanceCents: number;
    canApplyDiscount: boolean;
    processing?: boolean;
    depositCents?: number;
    depositFeeCents?: number;
    balanceFeeCents: number;
    totalFeeCents: number;
    netAmountCents: number;
    chargeAssignees: Array<{ id: number; name: string }>;
    defaultChargePerformerId: number | null;
    showChargePerformer: boolean;
}>();

const frequentClient = defineModel<boolean>('frequentClient', { required: true });
const discountPercent = defineModel<string>('discountPercent', { required: true });
const additionalChargesEnabled = defineModel<boolean>('additionalChargesEnabled', { required: true });
const additionalCharges = defineModel<SaleAdditionalCharge[]>('additionalCharges', { required: true });
</script>

<template>
    <div class="sale-checkout-summary">
        <SaleCheckoutAdjustments
            v-model:frequent-client="frequentClient"
            v-model:discount-percent="discountPercent"
            v-model:additional-charges-enabled="additionalChargesEnabled"
            v-model:additional-charges="additionalCharges"
            :can-apply-discount="canApplyDiscount"
            :processing="processing"
            :charge-assignees="chargeAssignees"
            :default-charge-performer-id="defaultChargePerformerId"
            :show-charge-performer="showChargePerformer"
        />

        <div class="summary-row"><span>Servicios realizados</span><strong>{{ totalServices }}</strong></div>
        <slot name="services" />
        <div class="summary-row"><span>Total de servicios</span><strong>{{ formatHnl(serviceSubtotalCents) }}</strong></div>
        <div v-if="additionalChargesEnabled" class="summary-row"><span>Cargos adicionales</span><strong>{{ formatHnl(additionalChargesCents) }}</strong></div>
        <div class="summary-row"><span>Subtotal</span><strong>{{ formatHnl(subtotalCents) }}</strong></div>
        <template v-if="frequentClient">
            <div class="summary-row"><span>Descuento</span><strong>{{ discountPercent || '0' }} %</strong></div>
            <div class="summary-row"><span>Monto descontado</span><strong>− {{ formatHnl(discountCents) }}</strong></div>
            <div class="summary-row"><span>Total después del descuento</span><strong>{{ formatHnl(totalCents) }}</strong></div>
        </template>
        <div v-if="depositCents" class="summary-row"><span>Adelanto aplicado</span><strong>− {{ formatHnl(depositCents) }}</strong></div>
        <div class="summary-row summary-row--balance"><span>{{ depositCents ? 'Saldo pendiente' : 'Total a cobrar' }}</span><strong>{{ formatHnl(balanceCents) }}</strong></div>
        <VDivider class="my-3" />
        <div v-if="depositFeeCents" class="summary-row text-body-2"><span>Comisión POS del adelanto</span><strong>{{ formatHnl(depositFeeCents) }}</strong></div>
        <div v-if="balanceFeeCents" class="summary-row text-body-2"><span>Comisión POS del saldo</span><strong>{{ formatHnl(balanceFeeCents) }}</strong></div>
        <div class="summary-row text-body-2"><span>Comisión POS total interna</span><strong>{{ formatHnl(totalFeeCents) }}</strong></div>
        <div class="summary-row text-body-2 mb-0"><span>Ingreso neto interno</span><strong>{{ formatHnl(netAmountCents) }}</strong></div>
    </div>
</template>

<style scoped>
.summary-row { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 10px; font-variant-numeric: tabular-nums; }
.summary-row strong { text-align: right; }
.summary-row--balance { align-items: baseline; margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); font-size: 1rem; font-weight: 700; }
</style>

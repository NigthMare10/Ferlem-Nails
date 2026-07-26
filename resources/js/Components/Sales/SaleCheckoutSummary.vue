<script setup lang="ts">
import type { PaymentMethod } from '../../types/sales';
import { formatHnl } from '../../utils/money';

defineProps<{
    totalCents: number;
    totalServices: number;
    paymentMethod: PaymentMethod;
    balanceCents: number;
    depositCents?: number;
    depositFeeCents?: number;
    balanceFeeCents: number;
    totalFeeCents: number;
    netAmountCents: number;
}>();
</script>

<template>
    <div class="sale-checkout-summary">
        <div class="summary-row"><span>Servicios realizados</span><strong>{{ totalServices }}</strong></div>
        <div class="summary-row"><span>Total de servicios</span><strong>{{ formatHnl(totalCents) }}</strong></div>
        <div v-if="depositCents" class="summary-row"><span>Adelanto aplicado</span><strong>− {{ formatHnl(depositCents) }}</strong></div>
        <div class="summary-row summary-row--balance"><span>{{ depositCents ? 'Saldo pendiente' : 'Total a cobrar' }}</span><strong>{{ formatHnl(balanceCents) }}</strong></div>
        <div class="summary-row text-body-2"><span>Método del {{ depositCents ? 'saldo' : 'pago' }}</span><strong>{{ balanceCents === 0 ? 'Cubierto por adelanto' : paymentMethod === 'card' ? 'Tarjeta' : paymentMethod === 'transfer' ? 'Transferencia' : 'Efectivo' }}</strong></div>
        <VDivider class="my-3" />
        <div v-if="depositFeeCents" class="summary-row text-body-2"><span>Comisión POS del adelanto</span><strong>{{ formatHnl(depositFeeCents) }}</strong></div>
        <div v-if="balanceFeeCents" class="summary-row text-body-2"><span>Comisión POS del saldo</span><strong>{{ formatHnl(balanceFeeCents) }}</strong></div>
        <div class="summary-row text-body-2"><span>Comisión POS total interna</span><strong>{{ formatHnl(totalFeeCents) }}</strong></div>
        <div class="summary-row text-body-2 mb-0"><span>Ingreso neto interno</span><strong>{{ formatHnl(netAmountCents) }}</strong></div>
    </div>
</template>

<style scoped>
.summary-row { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 10px; }
.summary-row--balance { align-items: baseline; margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); font-size: 1.15rem; }
</style>

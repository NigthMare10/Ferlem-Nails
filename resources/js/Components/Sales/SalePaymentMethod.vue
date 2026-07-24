<script setup lang="ts">
import type { PaymentMethod } from '../../types/sales';

defineProps<{
    modelValue: PaymentMethod;
    amountCents: number;
    processing?: boolean;
    balancePayment?: boolean;
}>();

defineEmits<{ 'update:modelValue': [value: PaymentMethod] }>();
</script>

<template>
    <VSwitch
        v-if="amountCents > 0"
        :model-value="modelValue === 'card'"
        :label="balancePayment ? 'Saldo pagado con tarjeta' : 'Pago con tarjeta'"
        color="primary"
        inset
        hide-details
        :disabled="processing"
        @update:model-value="$emit('update:modelValue', $event ? 'card' : 'cash')"
    />
    <div v-else class="text-body-2 text-medium-emphasis">El saldo ya está cubierto por el adelanto.</div>
</template>

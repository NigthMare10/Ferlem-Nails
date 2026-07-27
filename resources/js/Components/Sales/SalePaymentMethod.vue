<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import type { PaymentMethod } from '../../types/sales';

const props = defineProps<{
    modelValue: PaymentMethod;
    amountCents: number;
    processing?: boolean;
    balancePayment?: boolean;
    paymentProof?: File | null;
    proofError?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: PaymentMethod];
    'update:paymentProof': [value: File | null];
}>();
const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);
const methods: Array<{ value: PaymentMethod; label: string; icon: string }> = [
    { value: 'cash', label: 'Efectivo', icon: 'mdi-cash' },
    { value: 'card', label: 'Tarjeta', icon: 'mdi-credit-card-outline' },
    { value: 'transfer', label: 'Transferencia', icon: 'mdi-bank-transfer' },
];

watch(() => props.paymentProof, (file) => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = file ? URL.createObjectURL(file) : null;
}, { immediate: true });
onBeforeUnmount(() => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});

function selectMethod(method: PaymentMethod): void {
    if (method !== 'transfer' && props.paymentProof
        && !window.confirm('La captura seleccionada se quitará al cambiar el método de pago.')) return;
    if (method !== 'transfer') removeProof();
    emit('update:modelValue', method);
}

function selectProof(event: Event): void {
    emit('update:paymentProof', (event.target as HTMLInputElement).files?.[0] ?? null);
}

function removeProof(): void {
    emit('update:paymentProof', null);
    if (fileInput.value) fileInput.value.value = '';
}
</script>

<template>
    <div v-if="amountCents > 0" class="payment-method">
        <div class="text-body-2 font-weight-medium mb-2">{{ balancePayment ? 'Método del saldo' : 'Método de pago' }}</div>
        <div class="payment-method__options">
            <VBtn v-for="method in methods" :key="method.value" :active="modelValue === method.value" :variant="modelValue === method.value ? 'flat' : 'outlined'" :color="modelValue === method.value ? 'primary' : undefined" :prepend-icon="method.icon" :disabled="processing" @click="selectMethod(method.value)">{{ method.label }}</VBtn>
        </div>
        <div v-if="modelValue === 'transfer'" class="payment-proof mt-4">
            <input ref="fileInput" class="payment-proof__input" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" :disabled="processing" @change="selectProof">
            <template v-if="paymentProof">
                <img v-if="previewUrl" :src="previewUrl" alt="Vista previa de la captura" class="payment-proof__preview">
                <div class="text-body-2 text-truncate mt-2">{{ paymentProof.name }}</div>
                <VBtn size="small" variant="text" color="error" prepend-icon="mdi-close" :disabled="processing" @click="removeProof">Quitar captura</VBtn>
            </template>
            <VBtn v-else variant="tonal" prepend-icon="mdi-image-plus-outline" :disabled="processing" @click="fileInput?.click()">Agregar captura</VBtn>
            <span class="text-caption text-medium-emphasis ml-2">Opcional · JPG, PNG o WEBP · Máx. 5 MB</span>
            <div v-if="proofError" class="text-error text-caption mt-2">{{ proofError }}</div>
        </div>
    </div>
    <div v-else class="text-body-2 text-medium-emphasis">El saldo ya está cubierto por el adelanto.</div>
</template>

<style scoped>
.payment-method { container-type: inline-size; }
.payment-method__options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.payment-method__options :deep(.v-btn:last-child) { grid-column: 1 / -1; }
.payment-method__options :deep(.v-btn__content) { min-width: 0; }
.payment-proof__input { display: none; }
.payment-proof__preview { display: block; width: min(100%, 280px); max-height: 180px; border-radius: 12px; object-fit: contain; background: rgba(var(--v-theme-on-surface), .05); }
@media (max-width: 480px) { .payment-method__options { grid-template-columns: 1fr; } .payment-method__options :deep(.v-btn:last-child) { grid-column: auto; } }
</style>

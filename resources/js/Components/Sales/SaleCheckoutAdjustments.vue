<script setup lang="ts">
import type { SaleAdditionalCharge } from '../../types/sales';

const props = defineProps<{
    canApplyDiscount: boolean;
    processing?: boolean;
    chargeAssignees: Array<{ id: number; name: string }>;
    defaultChargePerformerId: number | null;
    showChargePerformer: boolean;
}>();

const frequentClient = defineModel<boolean>('frequentClient', { required: true });
const discountPercent = defineModel<string>('discountPercent', { required: true });
const additionalChargesEnabled = defineModel<boolean>('additionalChargesEnabled', { required: true });
const additionalCharges = defineModel<SaleAdditionalCharge[]>('additionalCharges', { required: true });

function toggleFrequentClient(value: boolean | null): void {
    frequentClient.value = Boolean(value);
    if (!value) discountPercent.value = '';
}

function updateDiscountPercent(value: string | number | null): void {
    const raw = String(value ?? '').replace(',', '.');
    if (!raw) {
        discountPercent.value = '';
        return;
    }

    const [whole = '', decimals] = raw.replace(/[^\d.]/g, '').split('.');
    const normalized = decimals === undefined ? whole : `${whole}.${decimals.slice(0, 2)}`;
    discountPercent.value = Number(normalized) > 100 ? '100' : normalized;
}

function toggleAdditionalCharges(value: boolean | null): void {
    if (value) {
        additionalChargesEnabled.value = true;
        if (!additionalCharges.value.length) additionalCharges.value.push(blankCharge());
        return;
    }

    const hasInformation = additionalCharges.value.some(charge => charge.name.trim() || charge.amount.trim());
    if (hasInformation && !window.confirm('Se eliminará la información de los cargos adicionales. ¿Deseas continuar?')) return;

    additionalChargesEnabled.value = false;
    additionalCharges.value = [];
}

function addCharge(): void {
    additionalCharges.value.push(blankCharge());
}

function blankCharge(): SaleAdditionalCharge {
    return { name: '', amount: '', performed_by: props.defaultChargePerformerId };
}

function removeCharge(index: number): void {
    additionalCharges.value.splice(index, 1);
    if (!additionalCharges.value.length) additionalChargesEnabled.value = false;
}
</script>

<template>
    <div class="sale-checkout-adjustments">
        <VCheckbox
            v-if="canApplyDiscount"
            :model-value="frequentClient"
            label="Aplicar descuento de clienta frecuente"
            hide-details
            :disabled="processing"
            @update:model-value="toggleFrequentClient"
        />
        <VTextField
            v-if="canApplyDiscount && frequentClient"
            :model-value="discountPercent"
            label="Descuento"
            suffix="%"
            type="number"
            min="0"
            max="100"
            step="0.01"
            inputmode="decimal"
            class="sale-checkout-adjustments__discount"
            :disabled="processing"
            @update:model-value="updateDiscountPercent"
        />

        <VCheckbox
            :model-value="additionalChargesEnabled"
            label="Agregar cargo adicional"
            hide-details
            :disabled="processing"
            @update:model-value="toggleAdditionalCharges"
        />

        <div v-if="additionalChargesEnabled" class="sale-checkout-adjustments__charges">
            <div v-for="(charge, index) in additionalCharges" :key="index" class="sale-checkout-adjustments__charge">
                <VTextField v-model="charge.name" label="Nombre del cargo" maxlength="120" :disabled="processing" hide-details />
                <VTextField v-model="charge.amount" label="Total" prefix="L" type="number" min="0.01" step="0.01" inputmode="decimal" :disabled="processing" hide-details />
                <VSelect v-if="showChargePerformer" v-model="charge.performed_by" label="Corresponde a" :items="chargeAssignees" item-title="name" item-value="id" :disabled="processing" hide-details />
                <VBtn icon="mdi-close" variant="text" :disabled="processing" :aria-label="`Eliminar ${charge.name || 'cargo adicional'}`" @click="removeCharge(index)" />
            </div>
            <VBtn variant="text" prepend-icon="mdi-plus" :disabled="processing" class="sale-checkout-adjustments__add" @click="addCharge">Agregar otro cargo</VBtn>
        </div>
    </div>
</template>

<style scoped>
.sale-checkout-adjustments {
    display: grid;
    gap: 12px;
    margin-bottom: 20px;
}

.sale-checkout-adjustments__discount { width: 100%; }
.sale-checkout-adjustments__charges { display: grid; gap: 12px; }
.sale-checkout-adjustments__charge { display: grid; grid-template-columns: minmax(0, 1fr) minmax(120px, 0.55fr) minmax(150px, 0.75fr) 44px; gap: 8px; align-items: center; }
.sale-checkout-adjustments__add { justify-self: start; }

@media (max-width: 599px) {
    .sale-checkout-adjustments__charge { grid-template-columns: minmax(0, 1fr) 44px; }
    .sale-checkout-adjustments__charge .v-input:not(:first-child) { grid-column: 1 / -1; }
    .sale-checkout-adjustments__charge .v-btn { grid-column: 2; grid-row: 1; }
    .sale-checkout-adjustments__add { width: 100%; }
}
</style>

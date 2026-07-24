<script setup lang="ts">
import { decimalToCents, formatHnl } from '../../utils/money';

defineProps<{
    itemKey: string | number;
    name: string;
    price: string;
    quantity: number;
    durationMinutes?: number;
    reserved?: boolean;
    processing?: boolean;
}>();

defineEmits<{
    increase: [];
    decrease: [];
    remove: [];
}>();
</script>

<template>
    <VCard variant="outlined" rounded="lg" class="sale-line-item mb-3">
        <VCardText class="pa-4">
            <div class="d-flex justify-space-between ga-3">
                <div class="min-width-0">
                    <strong class="text-wrap">{{ name }}</strong>
                    <VChip v-if="reserved" size="x-small" color="primary" variant="tonal" class="ml-2">Reservado</VChip>
                    <div class="text-caption text-medium-emphasis mt-1">
                        {{ formatHnl(decimalToCents(price)) }} cada uno<span v-if="durationMinutes"> · {{ durationMinutes }} min</span>
                    </div>
                </div>
                <strong class="text-no-wrap">{{ formatHnl(decimalToCents(price) * quantity) }}</strong>
            </div>
            <slot name="notice" />
            <div class="sale-line-item__controls mt-3">
                <div class="d-flex align-center ga-1">
                    <VBtn icon="mdi-minus" size="small" variant="outlined" :disabled="processing" :aria-label="`Disminuir ${name}`" @click="$emit('decrease')" />
                    <strong class="sale-line-item__quantity">{{ quantity }}</strong>
                    <VBtn icon="mdi-plus" size="small" variant="outlined" :disabled="processing || quantity >= 50" :aria-label="`Aumentar ${name}`" @click="$emit('increase')" />
                </div>
                <div class="sale-line-item__extra"><slot name="extra" /></div>
                <VBtn icon="mdi-delete-outline" size="small" color="error" variant="text" :disabled="processing" :aria-label="`Quitar ${name}`" @click="$emit('remove')" />
            </div>
        </VCardText>
    </VCard>
</template>

<style scoped>
.sale-line-item { background: rgba(var(--v-theme-surface-variant), 0.22); }
.sale-line-item__controls { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 14px; }
.sale-line-item__quantity { min-width: 34px; text-align: center; }
.min-width-0 { min-width: 0; }
@media (max-width: 599px) { .sale-line-item__controls { grid-template-columns: 1fr auto; } .sale-line-item__extra { grid-column: 1 / -1; grid-row: 2; } }
</style>

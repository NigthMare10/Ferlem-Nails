<script setup lang="ts">
defineProps<{
    line: { discount_type?: 'percent' | 'fixed' | null; discount_value?: string; discount_reason?: string };
    disabled?: boolean;
}>();
</script>
<template>
    <div class="item-discount">
        <VSelect
            v-model="line.discount_type"
            label="Descuento de este servicio"
            :items="[
                { title: 'Sin descuento', value: null },
                { title: 'Porcentaje', value: 'percent' },
                { title: 'Monto fijo por línea', value: 'fixed' },
            ]"
            :disabled="disabled"
        /><template v-if="line.discount_type"
            ><VTextField
                v-model="line.discount_value"
                :label="line.discount_type === 'percent' ? 'Porcentaje' : 'Monto L'"
                type="number"
                min="0"
                step="0.01"
                :max="line.discount_type === 'percent' ? 100 : undefined"
                :disabled="disabled" /><VTextField
                v-model="line.discount_reason"
                label="Motivo obligatorio"
                maxlength="250"
                :disabled="disabled"
        /></template>
    </div>
</template>
<style scoped>
.item-discount {
    margin-top: 16px;
    display: grid;
    gap: 8px;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr));
}
</style>

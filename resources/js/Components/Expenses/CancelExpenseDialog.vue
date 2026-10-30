<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import type { ExpenseListItem } from '../../types/expenses';

const props = defineProps<{ modelValue: boolean; expense: ExpenseListItem | null }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
const { xs } = useDisplay();
const form = useForm({ cancellation_reason: '' });
const formErrors = computed(() => form.errors as Record<string, string | undefined>);
const money = (value: string) => new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));

watch(() => [props.modelValue, props.expense?.id], ([open]) => {
    if (open) {
        form.reset();
        form.clearErrors();
    }
});

function close(): void { if (!form.processing) emit('update:modelValue', false); }
function submit(): void {
    if (!props.expense || form.processing) return;
    form.post(`/expenses/${props.expense.id}/cancel`, { preserveScroll: true, onSuccess: () => { form.reset(); close(); } });
}
</script>

<template>
    <VDialog :model-value="modelValue" :fullscreen="xs" max-width="540" :persistent="form.processing" @update:model-value="$event ? emit('update:modelValue', true) : close()">
        <VCard v-if="expense" rounded="xl">
            <VCardTitle class="pa-5">Anular {{ expense.expense_number }}</VCardTitle>
            <VCardText><VAlert type="warning" variant="tonal" class="mb-4">El gasto por {{ money(expense.amount) }} permanecerá en el historial y su comprobante se conservará.</VAlert><VTextarea v-model="form.cancellation_reason" label="Motivo obligatorio" counter="500" :error-messages="formErrors.cancellation_reason || formErrors.expense" :disabled="form.processing" /></VCardText>
            <VCardActions class="pa-4 flex-wrap"><VSpacer /><VBtn :block="xs" :disabled="form.processing" @click="close">Cancelar</VBtn><VBtn :block="xs" color="error" :loading="form.processing" @click="submit">Confirmar anulación</VBtn></VCardActions>
        </VCard>
    </VDialog>
</template>

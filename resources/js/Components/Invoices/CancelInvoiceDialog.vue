<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    modelValue: boolean;
    invoice: { id: number; sale_number: string; total: string } | null;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
const form = useForm({ cancellation_reason: '' });
const formErrors = computed(() => form.errors as Record<string, string | undefined>);
const money = (value: string) => new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));

function close(): void {
    if (!form.processing) emit('update:modelValue', false);
}

function submit(): void {
    if (!props.invoice || form.processing) return;
    form.post(`/invoices/${props.invoice.id}/cancel`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            close();
        },
    });
}
</script>

<template>
    <VDialog :model-value="modelValue" max-width="520" :persistent="form.processing" @update:model-value="$event ? emit('update:modelValue', true) : close()">
        <VCard v-if="invoice" rounded="xl">
            <VCardTitle class="pa-5">Anular factura {{ invoice.sale_number }}</VCardTitle>
            <VCardText>
                <VAlert type="warning" variant="tonal" class="mb-4">La factura por {{ money(invoice.total) }} permanecerá visible como Anulada. Sus servicios, pagos y capturas no se eliminarán.</VAlert>
                <VTextarea v-model="form.cancellation_reason" label="Motivo obligatorio" counter="500" :error-messages="formErrors.cancellation_reason || formErrors.sale" :disabled="form.processing" />
            </VCardText>
            <VCardActions class="pa-4"><VSpacer /><VBtn :disabled="form.processing" @click="close">Cancelar</VBtn><VBtn color="error" :loading="form.processing" @click="submit">Confirmar anulación</VBtn></VCardActions>
        </VCard>
    </VDialog>
</template>

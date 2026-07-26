<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    modelValue: boolean;
    invoice: { id: number; sale_number: string; client_name: string; sold_at_display: string; payment_id: number; amount: string } | null;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
const input = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(null);
const form = useForm({ payment_proof: null as File | null });
const money = (value: string) => new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));

watch(() => form.payment_proof, (file) => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = file ? URL.createObjectURL(file) : null;
});
onBeforeUnmount(() => { if (preview.value) URL.revokeObjectURL(preview.value); });

function choose(event: Event): void {
    form.payment_proof = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.clearErrors();
}

function clear(): void {
    form.payment_proof = null;
    if (input.value) input.value.value = '';
}

function close(): void {
    if (form.processing) return;
    clear();
    form.clearErrors();
    emit('update:modelValue', false);
}

function submit(): void {
    if (!props.invoice || !form.payment_proof || form.processing) return;
    form.post(`/invoices/${props.invoice.id}/payments/${props.invoice.payment_id}/proof`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: close,
    });
}
</script>

<template>
    <VDialog :model-value="modelValue" max-width="560" :persistent="form.processing" @update:model-value="$event ? emit('update:modelValue', true) : close()">
        <VCard v-if="invoice" rounded="xl">
            <VCardTitle class="pa-5">Agregar comprobante de transferencia</VCardTitle>
            <VCardText>
                <div class="proof-context mb-4"><div><span>Factura</span><strong>{{ invoice.sale_number }}</strong></div><div><span>Clienta</span><strong>{{ invoice.client_name }}</strong></div><div><span>Fecha</span><strong>{{ invoice.sold_at_display }}</strong></div><div><span>Transferencia</span><strong>{{ money(invoice.amount) }}</strong></div></div>
                <input ref="input" type="file" hidden accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" :disabled="form.processing" @change="choose">
                <VBtn v-if="!form.payment_proof" variant="tonal" prepend-icon="mdi-image-plus-outline" :disabled="form.processing" @click="input?.click()">Seleccionar captura</VBtn>
                <template v-else><img v-if="preview" :src="preview" alt="Vista previa del comprobante" class="proof-preview"><div class="text-body-2 mt-2">{{ form.payment_proof.name }}</div><VBtn size="small" variant="text" color="error" prepend-icon="mdi-close" :disabled="form.processing" @click="clear">Quitar captura</VBtn></template>
                <div class="text-caption text-medium-emphasis mt-2">JPG, PNG o WEBP. Máximo 5 MB.</div>
                <div v-if="form.errors.payment_proof" class="text-error text-caption mt-2">{{ form.errors.payment_proof }}</div>
            </VCardText>
            <VCardActions class="pa-4"><VSpacer /><VBtn :disabled="form.processing" @click="close">Cancelar</VBtn><VBtn color="primary" :loading="form.processing" :disabled="!form.payment_proof" @click="submit">Guardar captura</VBtn></VCardActions>
        </VCard>
    </VDialog>
</template>

<style scoped>
.proof-context { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.proof-context div { display: flex; flex-direction: column; }
.proof-context span { color: rgba(var(--v-theme-on-surface), .62); font-size: .75rem; }
.proof-preview { display: block; width: min(100%, 360px); max-height: 260px; border-radius: 12px; object-fit: contain; background: rgba(var(--v-theme-on-surface), .05); }
@media (max-width: 500px) { .proof-context { grid-template-columns: 1fr; } }
</style>

<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import type { ExpenseListItem, ExpenseOption, ExpensePaymentMethod } from '../../types/expenses';

const props = defineProps<{
    modelValue: boolean;
    expense?: ExpenseListItem | null;
    categories: ExpenseOption[];
    employees: ExpenseOption[];
}>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
const { xs } = useDisplay();
const isEditing = computed(() => Boolean(props.expense));
const form = useForm<{
    checkout_token: string; expense_date: string; category_id: number | null; description: string; amount: string;
    payment_method: ExpensePaymentMethod; vendor: string; employee_id: number | null; notes: string; attachment: File | null;
}>({
    checkout_token: crypto.randomUUID() as string,
    expense_date: hondurasToday(),
    category_id: null as number | null,
    description: '',
    amount: '',
    payment_method: 'cash' as ExpensePaymentMethod,
    vendor: '',
    employee_id: null as number | null,
    notes: '',
    attachment: null as File | null,
});
const formErrors = computed(() => form.errors as Record<string, string | undefined>);
const methodOptions = [
    { title: 'Efectivo', value: 'cash' },
    { title: 'Tarjeta', value: 'card' },
    { title: 'Transferencia', value: 'transfer' },
];

watch(() => props.modelValue, open => {
    if (!open) return;
    form.clearErrors();
    if (props.expense) {
        form.defaults({
            checkout_token: '', expense_date: props.expense.expense_date, category_id: props.expense.category.id,
            description: props.expense.description, amount: props.expense.amount, payment_method: props.expense.payment_method,
            vendor: props.expense.vendor ?? '', employee_id: props.expense.employee?.id ?? null, notes: '', attachment: null,
        });
    } else {
        form.defaults({
            checkout_token: crypto.randomUUID(), expense_date: hondurasToday(), category_id: null, description: '', amount: '',
            payment_method: 'cash', vendor: '', employee_id: null, notes: '', attachment: null,
        });
    }
    form.reset();
});

function close(): void {
    if (!form.processing) emit('update:modelValue', false);
}

function submit(): void {
    if (form.processing) return;
    const options = { preserveScroll: true, onSuccess: close };
    if (props.expense) form.put(`/expenses/${props.expense.id}`, options);
    else form.post('/expenses', { ...options, forceFormData: true });
}

function hondurasToday(): string {
    const parts = new Intl.DateTimeFormat('en-US', { timeZone: 'America/Tegucigalpa', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
    const value = Object.fromEntries(parts.map(part => [part.type, part.value]));
    return `${value.year}-${value.month}-${value.day}`;
}
</script>

<template>
    <VDialog :model-value="modelValue" :fullscreen="xs" max-width="780" :persistent="form.processing" @update:model-value="$event ? emit('update:modelValue', true) : close()">
        <VCard rounded="xl">
            <VCardTitle class="pa-5">{{ isEditing ? `Editar ${expense?.expense_number}` : 'Registrar gasto' }}</VCardTitle>
            <VCardSubtitle class="px-5">Los datos financieros se validan y guardan en el servidor.</VCardSubtitle>
            <VForm @submit.prevent="submit">
                <VCardText class="pa-5">
                    <VAlert v-if="formErrors.expense || formErrors.checkout_token" type="error" variant="tonal" class="mb-4">{{ formErrors.expense || formErrors.checkout_token }}</VAlert>
                    <VRow>
                        <VCol cols="12" sm="6"><VTextField v-model="form.expense_date" type="date" label="Fecha del gasto" :error-messages="form.errors.expense_date" :disabled="form.processing" /></VCol>
                        <VCol cols="12" sm="6"><VSelect v-model="form.category_id" label="Categoría" :items="categories" item-title="name" item-value="id" :error-messages="form.errors.category_id" :disabled="form.processing" /></VCol>
                        <VCol cols="12"><VTextarea v-model="form.description" label="Descripción" rows="2" counter="500" :error-messages="form.errors.description" :disabled="form.processing" /></VCol>
                        <VCol cols="12" sm="6"><VTextField v-model="form.amount" label="Monto" prefix="L" inputmode="decimal" :error-messages="form.errors.amount" :disabled="form.processing" /></VCol>
                        <VCol cols="12" sm="6"><VSelect v-model="form.payment_method" label="Método de pago" :items="methodOptions" :error-messages="form.errors.payment_method" :disabled="form.processing" /></VCol>
                        <VCol cols="12" sm="6"><VTextField v-model="form.vendor" label="Proveedor o destinatario (opcional)" :error-messages="form.errors.vendor" :disabled="form.processing" /></VCol>
                        <VCol cols="12" sm="6"><VSelect v-model="form.employee_id" label="Empleado relacionado (opcional)" :items="employees" item-title="name" item-value="id" clearable :error-messages="form.errors.employee_id" :disabled="form.processing" /></VCol>
                        <VCol v-if="!isEditing" cols="12"><VTextarea v-model="form.notes" label="Nota (opcional)" rows="2" counter="1000" :error-messages="form.errors.notes" :disabled="form.processing" /></VCol>
                        <VCol v-if="!isEditing" cols="12"><VFileInput v-model="form.attachment" label="Comprobante (opcional)" accept="image/jpeg,image/png,image/webp,application/pdf" prepend-icon="mdi-paperclip" hint="JPG, PNG, WEBP o PDF. Máximo 5 MB." persistent-hint :error-messages="form.errors.attachment" :disabled="form.processing" /></VCol>
                    </VRow>
                </VCardText>
                <VCardActions class="pa-4 flex-wrap"><VSpacer /><VBtn :block="xs" :disabled="form.processing" @click="close">Cancelar</VBtn><VBtn :block="xs" type="submit" color="primary" :loading="form.processing">{{ isEditing ? 'Guardar cambios' : 'Registrar gasto' }}</VBtn></VCardActions>
            </VForm>
        </VCard>
    </VDialog>
</template>

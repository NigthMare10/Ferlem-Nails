<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import CancelExpenseDialog from '../../Components/Expenses/CancelExpenseDialog.vue';
import ExpenseFormDialog from '../../Components/Expenses/ExpenseFormDialog.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import type { ExpenseDetail, ExpenseOption } from '../../types/expenses';

const props = defineProps<{ expense: ExpenseDetail; categories: ExpenseOption[]; employees: ExpenseOption[] }>();
const editOpen = ref(false);
const cancelOpen = ref(false);
const money = (value: string) => new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));
const bytes = (value: number) => value < 1024 * 1024 ? `${Math.ceil(value / 1024)} KB` : `${(value / 1024 / 1024).toFixed(1)} MB`;
</script>

<template>
    <Head :title="expense.expense_number" />
    <AppLayout title="Detalle de gasto">
        <PageHeader :title="expense.expense_number" description="Detalle y auditoría del gasto registrado.">
            <template #actions><VBtn variant="text" prepend-icon="mdi-arrow-left" href="/expenses">Volver</VBtn><VBtn v-if="expense.can_edit" variant="tonal" prepend-icon="mdi-pencil-outline" @click="editOpen = true">Editar</VBtn><VBtn v-if="expense.can_cancel" color="error" variant="tonal" prepend-icon="mdi-cancel" @click="cancelOpen = true">Anular</VBtn></template>
        </PageHeader>
        <VAlert v-if="expense.cancellation" type="error" variant="tonal" class="mb-5"><strong>Gasto anulado</strong><div>{{ expense.cancellation.canceled_at_display }} por {{ expense.cancellation.canceled_by }}</div><div class="mt-1">Motivo: {{ expense.cancellation.reason }}</div></VAlert>
        <VRow>
            <VCol cols="12" lg="8"><VCard class="surface-card h-100"><VCardItem><VCardTitle>Información del gasto</VCardTitle><template #append><VChip :color="expense.status === 'canceled' ? 'error' : 'success'" variant="tonal">{{ expense.status_label }}</VChip></template></VCardItem><VCardText><div class="detail-grid"><div><span>Fecha</span><strong>{{ expense.expense_date_display }}</strong></div><div><span>Categoría</span><strong>{{ expense.category.name }}</strong></div><div><span>Monto</span><strong>{{ money(expense.amount) }}</strong></div><div><span>Método</span><strong>{{ expense.payment_method_label }}</strong></div><div><span>Proveedor o destinatario</span><strong>{{ expense.vendor || 'Sin dato' }}</strong></div><div><span>Empleado relacionado</span><strong>{{ expense.employee?.name || 'No relacionado' }}</strong></div><div><span>Registrado por</span><strong>{{ expense.recorded_by.name }}</strong></div><div><span>Fecha y hora del registro</span><strong>{{ expense.created_at_display }}</strong></div></div><VDivider class="my-5" /><div class="text-caption text-medium-emphasis mb-1">Descripción</div><p class="text-body-1 mb-4">{{ expense.description }}</p><template v-if="expense.notes"><div class="text-caption text-medium-emphasis mb-1">Nota</div><p class="text-body-2 mb-0">{{ expense.notes }}</p></template></VCardText></VCard></VCol>
            <VCol cols="12" lg="4"><VCard class="surface-card h-100"><VCardItem><VCardTitle>Comprobante</VCardTitle></VCardItem><VCardText><template v-if="expense.attachment"><div class="text-body-2 font-weight-bold text-break">{{ expense.attachment.original_name }}</div><div class="text-caption text-medium-emphasis mt-1">{{ expense.attachment.mime }} · {{ bytes(expense.attachment.size) }}</div><div class="text-caption text-medium-emphasis">Cargado {{ expense.attachment.uploaded_at_display }}</div><VBtn v-if="expense.attachment_url" block color="primary" variant="tonal" prepend-icon="mdi-open-in-new" :href="expense.attachment_url" target="_blank" class="mt-4">Ver comprobante</VBtn><VAlert v-else type="warning" variant="tonal" density="compact" class="mt-4">No tienes permiso para abrir este archivo.</VAlert></template><p v-else class="text-body-2 text-medium-emphasis mb-0">Este gasto no tiene comprobante adjunto.</p></VCardText></VCard></VCol>
        </VRow>
        <VCard class="surface-card mt-6"><VCardItem><VCardTitle>Auditoría</VCardTitle><VCardSubtitle>Historial inmutable de creación, modificaciones y anulación</VCardSubtitle></VCardItem><VTimeline side="end" align="start" density="compact" class="px-4 pb-4"><VTimelineItem v-for="event in expense.events" :key="event.id" :dot-color="event.type === 'canceled' ? 'error' : event.type === 'updated' ? 'warning' : 'success'" size="small"><div class="font-weight-bold">{{ event.type_label }}</div><div class="text-caption text-medium-emphasis">{{ event.occurred_at_display }} · {{ event.performed_by }}</div><div v-if="event.notes" class="text-body-2 mt-2">{{ event.notes }}</div><div v-for="change in event.changes" :key="change.field" class="change-row mt-2"><strong>{{ change.field }}</strong><span>{{ change.previous }}</span><VIcon icon="mdi-arrow-right" size="small" /><span>{{ change.current }}</span></div></VTimelineItem></VTimeline></VCard>
        <ExpenseFormDialog v-model="editOpen" :expense="expense" :categories="categories" :employees="employees" />
        <CancelExpenseDialog v-model="cancelOpen" :expense="expense" />
    </AppLayout>
</template>

<style scoped>
.detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }.detail-grid div { display: flex; flex-direction: column; gap: 3px; }.detail-grid span { color: rgba(var(--v-theme-on-surface), .62); font-size: .78rem; }.change-row { display: grid; grid-template-columns: minmax(120px, .7fr) 1fr auto 1fr; align-items: center; gap: 8px; font-size: .84rem; }
@media (max-width: 700px) { .detail-grid { grid-template-columns: 1fr; }.change-row { grid-template-columns: 1fr; }.change-row .v-icon { transform: rotate(90deg); } }
</style>

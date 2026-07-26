<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import CancelInvoiceDialog from '../../Components/Invoices/CancelInvoiceDialog.vue';
import TransferProofUploadDialog from '../../Components/Invoices/TransferProofUploadDialog.vue';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import type { InvoiceListItem } from '../../types/invoices';

interface InvoicePage { data: InvoiceListItem[]; meta: { current_page: number; last_page: number; total: number } }
interface Filters { search?: string | null; date_from?: string | null; date_to?: string | null; status?: string | null; method?: string | null; employee_id?: number | null; proof_status?: string | null }

const props = defineProps<{ invoices: InvoicePage; filters: Filters; canViewAll: boolean; employees: Array<{ id: number; name: string }> }>();
const page = usePage();
const loading = ref(false);
const mobileFilters = ref<number[]>([]);
const cancelOpen = ref(false);
const proofOpen = ref(false);
const selected = ref<InvoiceListItem | null>(null);
const form = ref({
    search: props.filters.search ?? '', date_from: props.filters.date_from ?? '', date_to: props.filters.date_to ?? '',
    status: props.filters.status ?? null, method: props.filters.method ?? null,
    employee_id: props.filters.employee_id ?? null, proof_status: props.filters.proof_status ?? null,
});
const errors = computed(() => page.props.errors as Record<string, string>);
const records = computed(() => props.invoices.data ?? []);
const statusOptions = [{ title: 'Todas', value: null }, { title: 'Completadas', value: 'completed' }, { title: 'Anuladas', value: 'canceled' }];
const methodOptions = [{ title: 'Todos', value: null }, { title: 'Efectivo', value: 'cash' }, { title: 'Tarjeta', value: 'card' }, { title: 'Transferencia', value: 'transfer' }, { title: 'Mixto', value: 'mixed' }];
const proofOptions = [{ title: 'Todos', value: null }, { title: 'Con captura', value: 'with_proof' }, { title: 'Pendiente de captura', value: 'pending' }];
const employeeOptions = computed(() => [{ id: null, name: 'Todos los empleados' }, ...props.employees]);
const headers = [
    { title: 'Número de factura', key: 'sale_number', sortable: false }, { title: 'Clienta', key: 'client_name', sortable: false },
    { title: 'Fecha y hora', key: 'sold_at_display', sortable: false }, { title: 'Empleado que cobró', key: 'sold_by', sortable: false },
    { title: 'Método de pago', key: 'payment_method_label', sortable: false }, { title: 'Monto total', key: 'total', sortable: false, align: 'end' as const },
    { title: 'Estado', key: 'status', sortable: false }, { title: 'Comprobante', key: 'proof', sortable: false },
    { title: 'Acciones', key: 'actions', sortable: false, align: 'end' as const },
];
const money = (value: string) => new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));

function query(pageNumber?: number): Record<string, string | number> {
    const values: Record<string, string | number> = {};
    for (const [key, value] of Object.entries(form.value)) if (value !== null && String(value).trim() !== '') values[key] = typeof value === 'string' ? value.trim() : value;
    if (pageNumber) values.page = pageNumber;
    return values;
}
function load(pageNumber?: number): void {
    if (loading.value) return;
    loading.value = true;
    router.get('/invoices', query(pageNumber), { preserveState: true, preserveScroll: true, replace: true, onFinish: () => { loading.value = false; } });
}
function clearFilters(): void {
    form.value = { search: '', date_from: '', date_to: '', status: null, method: null, employee_id: null, proof_status: null };
    load();
}
function openCancel(invoice: InvoiceListItem): void { selected.value = invoice; cancelOpen.value = true; }
function openProof(invoice: InvoiceListItem): void { selected.value = invoice; proofOpen.value = true; }
function proofDialogInvoice(invoice: InvoiceListItem | null) {
    if (!invoice?.transfer_payment) return null;
    return { id: invoice.id, sale_number: invoice.sale_number, client_name: invoice.client_name, sold_at_display: invoice.sold_at_display, payment_id: invoice.transfer_payment.id, amount: invoice.transfer_payment.amount };
}
</script>

<template>
    <Head title="Facturas" />
    <AppLayout title="Facturas">
        <PageHeader title="Facturas" description="Consulta los comprobantes de venta registrados en Studio Lemus." />

        <VExpansionPanels v-model="mobileFilters" multiple class="invoice-mobile-filters mb-4"><VExpansionPanel elevation="0" rounded="lg"><VExpansionPanelTitle><VIcon icon="mdi-filter-variant" class="mr-2" />Filtros</VExpansionPanelTitle><VExpansionPanelText><div class="invoice-filter-grid"><VTextField v-model="form.search" label="Número o clienta" :error-messages="errors.search" /><VTextField v-model="form.date_from" type="date" label="Desde" :error-messages="errors.date_from" /><VTextField v-model="form.date_to" type="date" label="Hasta" :error-messages="errors.date_to" /><VSelect v-model="form.status" label="Estado" :items="statusOptions" /><VSelect v-model="form.method" label="Método" :items="methodOptions" /><VSelect v-if="canViewAll" v-model="form.employee_id" label="Empleado" :items="employeeOptions" item-title="name" item-value="id" /><VSelect v-model="form.proof_status" label="Estado de captura" :items="proofOptions" /></div><div class="d-flex ga-2"><VBtn color="primary" :loading="loading" @click="load()">Aplicar</VBtn><VBtn variant="text" :disabled="loading" @click="clearFilters">Limpiar</VBtn></div></VExpansionPanelText></VExpansionPanel></VExpansionPanels>

        <VCard class="invoice-desktop-filters surface-card mb-5" rounded="xl"><VCardText><div class="invoice-filter-grid"><VTextField v-model="form.search" label="Número o clienta" :error-messages="errors.search" /><VTextField v-model="form.date_from" type="date" label="Desde" :error-messages="errors.date_from" /><VTextField v-model="form.date_to" type="date" label="Hasta" :error-messages="errors.date_to" /><VSelect v-model="form.status" label="Estado" :items="statusOptions" /><VSelect v-model="form.method" label="Método" :items="methodOptions" /><VSelect v-if="canViewAll" v-model="form.employee_id" label="Empleado" :items="employeeOptions" item-title="name" item-value="id" /><VSelect v-model="form.proof_status" label="Estado de captura" :items="proofOptions" /></div><div class="d-flex ga-2"><VBtn color="primary" prepend-icon="mdi-filter-check-outline" :loading="loading" @click="load()">Aplicar</VBtn><VBtn variant="text" prepend-icon="mdi-filter-off-outline" :disabled="loading" @click="clearFilters">Limpiar</VBtn></div></VCardText></VCard>

        <VCard class="surface-card invoice-results" rounded="xl" :class="{ 'invoice-loading': loading }">
            <VProgressLinear v-if="loading" indeterminate color="primary" />
            <VDataTable :headers="headers" :items="records" class="invoice-desktop-table" hide-default-footer>
                <template #item.sale_number="{ item }"><strong>{{ item.sale_number }}</strong></template><template #item.sold_by="{ item }">{{ item.sold_by.name }}</template><template #item.total="{ item }"><strong>{{ money(item.total) }}</strong></template><template #item.status="{ item }"><VChip :color="item.status === 'canceled' ? 'error' : 'success'" variant="tonal" size="small">{{ item.status_label }}</VChip></template><template #item.proof="{ item }"><VChip :color="item.proof_status === 'with_proof' ? 'success' : item.proof_status === 'pending' ? 'warning' : undefined" variant="tonal" size="small">{{ item.proof_status_label }}</VChip></template>
                <template #item.actions="{ item }"><div class="invoice-actions"><VBtn size="small" variant="text" prepend-icon="mdi-eye-outline" :href="item.show_url">Ver factura</VBtn><VBtn v-if="item.transfer_payment?.proof_url" size="small" variant="text" prepend-icon="mdi-image-outline" :href="item.transfer_payment.proof_url" target="_blank">Ver captura</VBtn><VBtn v-if="item.transfer_payment?.can_upload_proof" size="small" variant="text" prepend-icon="mdi-upload-outline" @click="openProof(item)">Subir captura</VBtn><VBtn v-if="item.can_cancel" size="small" variant="text" color="error" prepend-icon="mdi-cancel" @click="openCancel(item)">Anular</VBtn></div></template>
                <template #no-data><EmptyState icon="mdi-receipt-text-search-outline" title="No se encontraron facturas" description="Ajusta los filtros para consultar otros comprobantes." /></template>
            </VDataTable>

            <section class="invoice-mobile-cards" aria-label="Facturas"><template v-if="loading"><VSkeletonLoader v-for="n in 3" :key="n" type="article, actions" class="mb-3" /></template><EmptyState v-else-if="!records.length" icon="mdi-receipt-text-search-outline" title="No se encontraron facturas" description="Ajusta los filtros para consultar otros comprobantes." /><VCard v-for="invoice in records" v-else :key="invoice.id" variant="outlined" rounded="lg" class="invoice-mobile-card"><VCardText><div class="invoice-card-heading"><div><strong>{{ invoice.sale_number }}</strong><div class="text-caption text-medium-emphasis">{{ invoice.client_name }} · {{ invoice.sold_at_display }}</div></div><VChip :color="invoice.status === 'canceled' ? 'error' : 'success'" variant="tonal" size="small">{{ invoice.status_label }}</VChip></div><div class="invoice-card-grid"><div><span>Total</span><strong>{{ money(invoice.total) }}</strong></div><div><span>Método</span><strong>{{ invoice.payment_method_label }}</strong></div><div><span>Captura</span><strong>{{ invoice.proof_status_label }}</strong></div></div><VMenu><template #activator="{ props: menuProps }"><VBtn v-bind="menuProps" block variant="tonal" prepend-icon="mdi-dots-horizontal" class="mt-4">Acciones</VBtn></template><VList><VListItem prepend-icon="mdi-eye-outline" title="Ver factura" :href="invoice.show_url" /><VListItem v-if="invoice.transfer_payment?.proof_url" prepend-icon="mdi-image-outline" title="Ver captura" :href="invoice.transfer_payment.proof_url" target="_blank" /><VListItem v-if="invoice.transfer_payment?.can_upload_proof" prepend-icon="mdi-upload-outline" title="Subir captura" @click="openProof(invoice)" /><VListItem v-if="invoice.can_cancel" prepend-icon="mdi-cancel" title="Anular factura" base-color="error" @click="openCancel(invoice)" /></VList></VMenu></VCardText></VCard></section>
            <VPagination v-if="invoices.meta.last_page > 1" :model-value="invoices.meta.current_page" :length="invoices.meta.last_page" class="my-4" :disabled="loading" @update:model-value="load" />
        </VCard>

        <CancelInvoiceDialog v-model="cancelOpen" :invoice="selected" />
        <TransferProofUploadDialog v-model="proofOpen" :invoice="proofDialogInvoice(selected)" />
    </AppLayout>
</template>

<style scoped>
.invoice-mobile-filters, .invoice-mobile-cards { display: none; }
.invoice-filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.invoice-results { min-width: 0; overflow: hidden; }.invoice-loading { opacity: .7; pointer-events: none; }.invoice-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; }.invoice-card-heading { display: flex; justify-content: space-between; gap: 12px; }.invoice-card-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 16px; }.invoice-card-grid div { display: flex; flex-direction: column; }.invoice-card-grid span { color: rgba(var(--v-theme-on-surface), .62); font-size: .72rem; }
@media (max-width: 1000px) { .invoice-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 700px) { .invoice-desktop-filters, .invoice-desktop-table { display: none; }.invoice-mobile-filters, .invoice-mobile-cards { display: block; }.invoice-mobile-cards { padding: 14px; }.invoice-mobile-card + .invoice-mobile-card { margin-top: 12px; }.invoice-filter-grid { grid-template-columns: 1fr; }.invoice-card-grid { grid-template-columns: 1fr 1fr; } }
</style>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppointmentDetailsDialog from '../../Components/Appointments/AppointmentDetailsDialog.vue';
import AppointmentHistoryFilters from '../../Components/Appointments/AppointmentHistoryFilters.vue';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { usePermissions } from '../../composables/usePermissions';
import type { AppointmentAssignee, AppointmentDetails, AppointmentHistoryItem } from '../../types/appointments';

interface HistoryPage {
    data: AppointmentHistoryItem[];
    meta: { current_page: number; last_page: number; total: number };
}

interface HistoryFilters {
    date_from: string | null;
    date_to: string | null;
    status: string | null;
    employee_id: number | null;
    client: string | null;
    service: string | null;
}

const props = defineProps<{
    appointments: HistoryPage;
    filters: HistoryFilters;
    assignees: AppointmentAssignee[];
    canViewAll: boolean;
}>();

const { can } = usePermissions();
const page = usePage();
const loading = ref(false);
const filterPanel = ref<number[]>([]);
const form = ref({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    status: props.filters.status,
    employee_id: props.filters.employee_id,
    client: props.filters.client ?? '',
    service: props.filters.service ?? '',
});
const detailsOpen = ref(false);
const detailsLoading = ref(false);
const detailsError = ref<string | null>(null);
const selectedAppointmentId = ref<number | null>(null);
const selectedAppointment = ref<AppointmentDetails | null>(null);
const validationErrors = computed(() => page.props.errors as Record<string, string>);
const records = computed(() => props.appointments.data ?? []);
const headers = [
    { title: 'Fecha y hora', key: 'schedule', sortable: false },
    { title: 'Clienta', key: 'client_name', sortable: false },
    { title: 'Estado', key: 'status', sortable: false },
    { title: 'Servicios', key: 'services', sortable: false },
    ...(props.canViewAll ? [{ title: 'Personal', key: 'personnel', sortable: false }] : []),
    { title: 'Estimado visible', key: 'total', sortable: false, align: 'end' as const },
    { title: 'Adelanto', key: 'deposit', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end' as const },
];

function query(pageNumber?: number): Record<string, string | number> {
    const values: Record<string, string | number> = {};
    for (const [key, value] of Object.entries(form.value)) {
        if (value !== null && String(value).trim() !== '') values[key] = typeof value === 'string' ? value.trim() : value;
    }
    if (pageNumber) values.page = pageNumber;
    return values;
}

function load(pageNumber?: number): void {
    if (loading.value) return;
    loading.value = true;
    router.get('/appointments/history', query(pageNumber), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => { loading.value = false; },
    });
}

function clearFilters(): void {
    form.value = { date_from: '', date_to: '', status: null, employee_id: null, client: '', service: '' };
    load();
}

function money(value: string): string {
    return new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));
}

function statusColor(status: AppointmentHistoryItem['status']): string {
    return ({ scheduled: 'primary', completed: 'success', canceled: 'error', no_show: 'warning' })[status];
}

function servicesLabel(appointment: AppointmentHistoryItem): string {
    return appointment.visible_services.map(item => `${item.name}${item.quantity > 1 ? ` × ${item.quantity}` : ''}`).join(', ');
}

function depositLabel(appointment: AppointmentHistoryItem): string {
    if (!appointment.deposit) return 'Sin adelanto';
    if (appointment.deposit.amount === undefined) return appointment.deposit.status_label;
    return `${money(appointment.deposit.amount)} · ${appointment.deposit.status_label}`;
}

async function loadDetails(): Promise<void> {
    if (!selectedAppointmentId.value || detailsLoading.value) return;
    detailsLoading.value = true;
    detailsError.value = null;
    try {
        const response = await fetch(`/appointments/${selectedAppointmentId.value}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(response.status === 403 ? 'No tienes permiso para consultar esta cita.' : 'No se pudo cargar el detalle de la cita.');
        selectedAppointment.value = (await response.json()).appointment as AppointmentDetails;
    } catch (error) {
        detailsError.value = error instanceof Error ? error.message : 'No se pudo cargar el detalle de la cita.';
    } finally {
        detailsLoading.value = false;
    }
}

function openDetails(appointment: AppointmentHistoryItem): void {
    selectedAppointmentId.value = appointment.id;
    selectedAppointment.value = null;
    detailsOpen.value = true;
    void loadDetails();
}

function clearDetails(): void {
    if (detailsOpen.value) return;
    selectedAppointmentId.value = null;
    selectedAppointment.value = null;
    detailsError.value = null;
    detailsLoading.value = false;
}
</script>

<template>
    <Head title="Historial de citas" />
    <AppLayout title="Historial de citas">
        <PageHeader title="Historial de citas" description="Consulta citas programadas y finalizadas sin modificar su registro.">
            <template #actions>
                <VBtn href="/appointments" variant="tonal" prepend-icon="mdi-calendar-clock-outline">Volver a Agenda</VBtn>
            </template>
        </PageHeader>

        <VExpansionPanels v-model="filterPanel" multiple class="mobile-history-filters mb-4">
            <VExpansionPanel elevation="0" rounded="lg">
                <VExpansionPanelTitle><VIcon icon="mdi-filter-variant" class="mr-2" />Filtros</VExpansionPanelTitle>
                <VExpansionPanelText><AppointmentHistoryFilters v-model="form" :assignees="assignees" :can-view-all="canViewAll" :errors="validationErrors" :loading="loading" @apply="load()" @clear="clearFilters" /></VExpansionPanelText>
            </VExpansionPanel>
        </VExpansionPanels>

        <VCard class="desktop-history-filters surface-card mb-5" rounded="xl">
            <VCardText><AppointmentHistoryFilters v-model="form" :assignees="assignees" :can-view-all="canViewAll" :errors="validationErrors" :loading="loading" @apply="load()" @clear="clearFilters" /></VCardText>
        </VCard>

        <VCard class="surface-card history-results" rounded="xl" :class="{ 'history-loading': loading }">
            <VProgressLinear v-if="loading" indeterminate color="primary" aria-label="Cargando historial" />
            <VDataTable :headers="headers" :items="records" :loading="loading" class="history-desktop-table" hide-default-footer>
                <template #item.schedule="{ item }"><strong>{{ item.date_display }}</strong><div class="text-caption text-medium-emphasis">{{ item.start_time }}–{{ item.end_time }}</div></template>
                <template #item.status="{ item }"><VChip :color="statusColor(item.status)" variant="tonal" size="small">{{ item.status_label }}</VChip></template>
                <template #item.services="{ item }"><div class="history-services">{{ servicesLabel(item) }}</div></template>
                <template #item.personnel="{ item }">{{ item.personnel?.join(', ') || 'Sin asignar' }}</template>
                <template #item.total="{ item }"><strong>{{ money(item.visible_total) }}</strong></template>
                <template #item.deposit="{ item }"><span class="text-body-2">{{ depositLabel(item) }}</span></template>
                <template #item.actions="{ item }"><div class="history-actions"><VBtn size="small" variant="text" prepend-icon="mdi-eye-outline" @click="openDetails(item)">Ver detalle</VBtn><VBtn v-if="item.status === 'completed' && item.linked_sale" size="small" variant="text" prepend-icon="mdi-receipt-text-outline" :href="item.linked_sale.receipt_url">Ver comprobante</VBtn></div></template>
                <template #no-data><EmptyState icon="mdi-calendar-search-outline" title="No se encontraron citas" description="Ajusta los filtros para consultar otros registros." /></template>
            </VDataTable>

            <section class="history-mobile-cards" aria-label="Historial de citas">
                <template v-if="loading"><VSkeletonLoader v-for="index in 3" :key="index" type="article, actions" class="mb-3" /></template>
                <EmptyState v-else-if="!records.length" icon="mdi-calendar-search-outline" title="No se encontraron citas" description="Ajusta los filtros para consultar otros registros." />
                <VCard v-for="appointment in records" v-else :key="appointment.id" variant="outlined" rounded="lg" class="history-mobile-card">
                    <VCardText class="pa-4">
                        <div class="history-card-heading"><div><div class="font-weight-bold">{{ appointment.client_name }}</div><div class="text-caption text-medium-emphasis">{{ appointment.date_display }} · {{ appointment.start_time }}–{{ appointment.end_time }}</div></div><VChip :color="statusColor(appointment.status)" variant="tonal" size="small">{{ appointment.status_label }}</VChip></div>
                        <div class="history-card-section"><span>Servicios</span><strong>{{ servicesLabel(appointment) }}</strong></div>
                        <div v-if="appointment.personnel" class="history-card-section"><span>Personal</span><strong>{{ appointment.personnel.join(', ') }}</strong></div>
                        <div class="history-card-summary"><div><span>Estimado visible</span><strong>{{ money(appointment.visible_total) }}</strong></div><div><span>Adelanto</span><strong>{{ depositLabel(appointment) }}</strong></div></div>
                        <div class="history-actions mt-4"><VBtn variant="text" prepend-icon="mdi-eye-outline" @click="openDetails(appointment)">Ver detalle</VBtn><VBtn v-if="appointment.status === 'completed' && appointment.linked_sale" variant="text" prepend-icon="mdi-receipt-text-outline" :href="appointment.linked_sale.receipt_url">Ver comprobante</VBtn></div>
                    </VCardText>
                </VCard>
            </section>

            <VPagination v-if="appointments.meta.last_page > 1" :model-value="appointments.meta.current_page" :length="appointments.meta.last_page" class="my-4" :disabled="loading" @update:model-value="load" />
        </VCard>

        <AppointmentDetailsDialog
            v-model="detailsOpen"
            :appointment="selectedAppointment"
            initial-mode="detail"
            :loading="detailsLoading"
            :error="detailsError"
            :assignees="[]"
            :can-update="false"
            :can-assign="false"
            :can-view-all="can('appointments.view_all')"
            :can-cancel="false"
            :can-mark-no-show="false"
            :can-manage-deposit="false"
            :can-resolve-deposit="false"
            @retry="loadDetails"
            @closed="clearDetails"
        />
    </AppLayout>
</template>

<style scoped>
.mobile-history-filters, .history-mobile-cards { display: none; }
.history-results { min-width: 0; overflow: hidden; }
.history-loading { opacity: .72; pointer-events: none; }
.history-services { max-width: 280px; white-space: normal; }
.history-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 2px; }
@media (max-width: 700px) {
    .desktop-history-filters, .history-desktop-table { display: none; }
    .mobile-history-filters, .history-mobile-cards { display: block; }
    .history-mobile-cards { min-width: 0; padding: 14px; }
    .history-mobile-card + .history-mobile-card { margin-top: 12px; }
    .history-card-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
    .history-card-section { display: flex; min-width: 0; flex-direction: column; gap: 2px; margin-top: 14px; }
    .history-card-section span, .history-card-summary span { color: rgba(var(--v-theme-on-surface), .62); font-size: .72rem; }
    .history-card-summary { display: grid; grid-template-columns: 1fr; gap: 10px; margin-top: 14px; }
    .history-card-summary div { display: flex; flex-direction: column; }
    .history-actions { align-items: stretch; flex-direction: column; }
    .history-actions :deep(.v-btn) { width: 100%; }
}
</style>

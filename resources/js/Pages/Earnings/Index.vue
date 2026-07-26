<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import type {
    ActualResults,
    AppointmentProjection,
    DailySummary,
    EarningsFilters,
    EarningsPeriod,
    EmployeeOption,
    EmployeeSummary,
    PaymentDistribution,
} from '../../types/earnings';

const props = defineProps<{
    filters: EarningsFilters;
    period: EarningsPeriod;
    canViewProjection: boolean;
    actual?: ActualResults;
    projection?: AppointmentProjection;
    employees: EmployeeSummary[];
    daily?: DailySummary[];
    employeeOptions: EmployeeOption[];
    payment_distribution?: PaymentDistribution[];
}>();

const form = useForm({
    period: props.filters.period,
    mode: props.filters.mode,
    date: props.filters.date ?? '',
    month: props.filters.month ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    employee_id: props.filters.employee_id ?? null as number | null,
    payment_method: props.filters.payment_method ?? null as 'cash' | 'card' | 'transfer' | null,
});

const periodOptions = [
    { title: 'Hoy', value: 'today' },
    { title: 'Semana', value: 'week' },
    { title: 'Mes', value: 'month' },
    { title: 'Personalizado', value: 'custom' },
];
const modeOptions = [
    { title: 'Resultados reales', value: 'actual' },
    { title: 'Proyección', value: 'projection' },
    { title: 'Ambos', value: 'both' },
];
const employeeOptions = computed(() => [{ id: null, name: 'Todos los empleados' }, ...props.employeeOptions]);
const paymentMethodOptions = [
    { title: 'Todos', value: null },
    { title: 'Efectivo', value: 'cash' },
    { title: 'Tarjeta', value: 'card' },
    { title: 'Transferencia', value: 'transfer' },
];
const hasProjection = computed(() => Boolean(props.projection));
const employeeHeaders = computed(() => [
    { title: 'Empleado', key: 'name' },
    { title: 'Servicios realizados', key: 'services_count', align: 'end' as const },
    { title: 'Bruto por servicios', key: 'total_sold', align: 'end' as const },
    { title: 'Comisión POS asignada', key: 'card_fee_amount', align: 'end' as const },
    { title: 'Ingreso neto', key: 'net_amount', align: 'end' as const },
    { title: 'Métodos', key: 'methods', sortable: false },
    ...(hasProjection.value ? [
        { title: 'Servicios proyectados', key: 'projected_services_count', align: 'end' as const },
        { title: 'Ingreso proyectado', key: 'projected_income', align: 'end' as const },
    ] : []),
]);
const dailyHeaders = [
    { title: 'Fecha', key: 'date_label' },
    { title: 'Ventas', key: 'sales_count', align: 'end' as const },
    { title: 'Servicios realizados', key: 'services_count', align: 'end' as const },
    { title: 'Ingresos brutos', key: 'total_sold', align: 'end' as const },
    { title: 'Comisión POS', key: 'card_fee_amount', align: 'end' as const },
    { title: 'Ingreso neto', key: 'net_amount', align: 'end' as const },
];
const actualMetrics = computed(() => props.actual ? [
    { label: 'Ingresos brutos reales', value: money(props.actual.gross_revenue), hint: `Promedio por venta: ${money(props.actual.average_sale)}` },
    { label: 'Comisión POS real', value: money(props.actual.pos_fee) },
    { label: 'Ingreso neto real', value: money(props.actual.net_income) },
    { label: 'Ventas completadas', value: count(props.actual.completed_sales_count) },
    { label: 'Servicios realizados', value: count(props.actual.performed_services_count) },
    { label: 'Ventas anuladas', value: count(props.actual.canceled_sales_count), hint: `Monto anulado: ${money(props.actual.canceled_amount)}` },
] : []);
const projectionMetrics = computed(() => props.projection ? [
    { label: 'Ingreso bruto proyectado', value: money(props.projection.projected_gross) },
    { label: 'Saldo pendiente proyectado', value: money(props.projection.pending_balance) },
    { label: 'Adelantos recibidos', value: money(props.projection.deposits_received), hint: 'No se suman al ingreso proyectado.' },
    { label: 'Citas programadas', value: count(props.projection.appointments_count) },
    { label: 'Servicios proyectados', value: count(props.projection.services_count) },
] : []);

function money(value: string): string {
    return new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));
}

function count(value: number): string {
    return new Intl.NumberFormat('es-HN').format(value);
}

function applyFilters(): void {
    if (!form.processing) form.get('/earnings', { preserveState: true, preserveScroll: true, replace: true });
}

function resetFilters(): void {
    if (!form.processing) router.get('/earnings', {}, { replace: true });
}
</script>

<template>
    <Head title="Ganancias Generales" />
    <AppLayout title="Ganancias Generales">
        <PageHeader
            eyebrow="Resultados y agenda"
            title="Ganancias Generales"
            description="Compara servicios realizados con las citas programadas sin mezclar sus importes."
        />

        <VAlert type="info" variant="tonal" density="compact" class="mb-6" icon="mdi-information-outline">
            El ingreso neto descuenta únicamente comisiones del POS. Todavía no incluye otros costos o gastos.
        </VAlert>

        <VCard class="surface-card mb-6">
            <VProgressLinear v-if="form.processing" indeterminate color="primary" />
            <VCardText class="pa-4 pa-sm-5">
                <VForm @submit.prevent="applyFilters">
                    <VRow class="filter-bar pa-2" align="start">
                        <VCol v-if="canViewProjection" cols="12" sm="6" lg="2">
                            <VSelect v-model="form.mode" label="Vista" :items="modeOptions" :error-messages="form.errors.mode" :disabled="form.processing" />
                        </VCol>
                        <VCol cols="12" sm="6" lg="2">
                            <VSelect v-model="form.period" label="Periodo" :items="periodOptions" :error-messages="form.errors.period" :disabled="form.processing" />
                        </VCol>
                        <VCol v-if="form.period === 'today' || form.period === 'week'" cols="12" sm="6" lg="2">
                            <VTextField v-model="form.date" type="date" label="Fecha de referencia" :error-messages="form.errors.date" :disabled="form.processing" />
                        </VCol>
                        <VCol v-else-if="form.period === 'month'" cols="12" sm="6" lg="2">
                            <VTextField v-model="form.month" type="month" label="Mes" :error-messages="form.errors.month" :disabled="form.processing" />
                        </VCol>
                        <template v-else>
                            <VCol cols="12" sm="6" lg="2"><VTextField v-model="form.date_from" type="date" label="Desde" :error-messages="form.errors.date_from" :disabled="form.processing" /></VCol>
                            <VCol cols="12" sm="6" lg="2"><VTextField v-model="form.date_to" type="date" label="Hasta" :error-messages="form.errors.date_to" :disabled="form.processing" /></VCol>
                        </template>
                        <VCol cols="12" sm="6" lg="2">
                            <VSelect v-model="form.employee_id" label="Empleado" :items="employeeOptions" item-title="name" item-value="id" :error-messages="form.errors.employee_id" :disabled="form.processing" />
                        </VCol>
                        <VCol cols="12" sm="6" lg="2">
                            <VSelect v-model="form.payment_method" label="Método de pago" :items="paymentMethodOptions" :error-messages="form.errors.payment_method" :disabled="form.processing" hint="Afecta solo las ventas reales; una venta mixta coincide con cualquiera de sus métodos." persistent-hint />
                        </VCol>
                        <VCol cols="12" lg="2" class="d-flex flex-wrap ga-2 filter-actions">
                            <VBtn type="submit" color="primary" prepend-icon="mdi-filter-check-outline" :loading="form.processing">Aplicar</VBtn>
                            <VBtn variant="text" prepend-icon="mdi-filter-off-outline" :disabled="form.processing" @click="resetFilters">Restablecer</VBtn>
                        </VCol>
                    </VRow>
                </VForm>
            </VCardText>
        </VCard>

        <section v-if="actual" class="report-section" :class="{ 'report-loading': form.processing }">
            <div class="section-heading">
                <div><div class="text-overline text-primary">Ventas completadas</div><h2 class="text-h5 font-weight-bold">Resultados reales</h2></div>
                <span class="text-body-2 text-medium-emphasis">{{ period.label }}</span>
            </div>
            <VRow>
                <VCol v-for="metric in actualMetrics" :key="metric.label" cols="12" sm="6" lg="4">
                    <VCard class="metric-card h-100"><VCardText class="pa-5"><div class="text-body-2 text-medium-emphasis mb-3">{{ metric.label }}</div><div class="text-h5 font-weight-bold">{{ metric.value }}</div><div v-if="metric.hint" class="text-caption mt-2">{{ metric.hint }}</div></VCardText></VCard>
                </VCol>
            </VRow>
        </section>

        <VCard v-if="actual && payment_distribution" class="surface-card report-section" :class="{ 'report-loading': form.processing }">
            <VCardItem class="pa-5 pb-2"><VCardTitle>Distribución por método de pago</VCardTitle><VCardSubtitle>Distribución de pagos de ventas completadas</VCardSubtitle></VCardItem>
            <VRow class="pa-3">
                <VCol v-for="method in payment_distribution" :key="method.method" cols="12" md="4">
                    <VCard variant="outlined" class="h-100"><VCardText><div class="font-weight-bold mb-3">{{ method.method_label }}</div><div class="mobile-stat"><span>Pagos</span><strong>{{ count(method.payments_count) }}</strong></div><div class="mobile-stat"><span>Monto bruto</span><strong>{{ money(method.amount) }}</strong></div><template v-if="method.method === 'card'"><div class="mobile-stat"><span>Comisión POS</span><strong>{{ money(method.card_fee_amount) }}</strong></div><div class="mobile-stat"><span>Ingreso neto</span><strong>{{ money(method.net_amount) }}</strong></div></template></VCardText></VCard>
                </VCol>
            </VRow>
        </VCard>

        <section v-if="projection" class="report-section projection-section" :class="{ 'report-loading': form.processing }">
            <div class="section-heading">
                <div><div class="text-overline text-primary">Agenda programada</div><h2 class="text-h5 font-weight-bold">Proyección</h2></div>
                <span class="text-body-2 text-medium-emphasis">Solo citas programadas</span>
            </div>
            <VRow>
                <VCol v-for="metric in projectionMetrics" :key="metric.label" cols="12" sm="6" lg="4">
                    <VCard class="metric-card projection-card h-100"><VCardText class="pa-5"><div class="text-body-2 text-medium-emphasis mb-3">{{ metric.label }}</div><div class="text-h5 font-weight-bold">{{ metric.value }}</div><div v-if="metric.hint" class="text-caption mt-2">{{ metric.hint }}</div></VCardText></VCard>
                </VCol>
            </VRow>
        </section>

        <VCard v-if="employees.length" class="surface-card report-section" :class="{ 'report-loading': form.processing }">
            <VCardItem class="pa-5 pb-2"><VCardTitle>Rendimiento por empleado</VCardTitle><VCardSubtitle>Servicios atribuidos a quien los realiza, no a quien cobra</VCardSubtitle></VCardItem>
            <VDataTable :headers="employeeHeaders" :items="employees" class="desktop-table" :items-per-page="-1" hide-default-footer>
                <template #item.total_sold="{ item }"><strong>{{ money(item.total_sold) }}</strong></template>
                <template #item.card_fee_amount="{ item }">{{ money(item.card_fee_amount) }}</template>
                <template #item.net_amount="{ item }"><strong>{{ money(item.net_amount) }}</strong></template>
                <template #item.projected_income="{ item }"><strong>{{ money(item.projected_income ?? '0.00') }}</strong></template>
            </VDataTable>
            <div class="mobile-cards pa-4 pt-2">
                <VCard v-for="employee in employees" :key="employee.id" variant="outlined" class="mb-3">
                    <VCardTitle class="pa-4 pb-2 text-body-1 font-weight-bold">{{ employee.name }}</VCardTitle>
                    <VCardText>
                        <div class="mobile-stat"><span>Servicios realizados</span><strong>{{ count(employee.services_count) }}</strong></div>
                        <div class="mobile-stat"><span>Bruto por servicios</span><strong>{{ money(employee.total_sold) }}</strong></div>
                        <div class="mobile-stat"><span>Comisión POS asignada</span><strong>{{ money(employee.card_fee_amount) }}</strong></div>
                        <div class="mobile-stat"><span>Ingreso neto</span><strong>{{ money(employee.net_amount) }}</strong></div>
                        <div v-if="hasProjection" class="mobile-stat"><span>Servicios proyectados</span><strong>{{ count(employee.projected_services_count ?? 0) }}</strong></div>
                        <div v-if="hasProjection" class="mobile-stat"><span>Ingreso proyectado</span><strong>{{ money(employee.projected_income ?? '0.00') }}</strong></div>
                    </VCardText>
                </VCard>
            </div>
        </VCard>

        <VCard v-if="daily?.length" class="surface-card report-section" :class="{ 'report-loading': form.processing }">
            <VCardItem class="pa-5 pb-2"><VCardTitle>Resultados reales por día</VCardTitle><VCardSubtitle>Calculados desde ventas completadas</VCardSubtitle></VCardItem>
            <VDataTable :headers="dailyHeaders" :items="daily" class="desktop-table" :items-per-page="-1" hide-default-footer>
                <template #item.total_sold="{ item }"><strong>{{ money(item.total_sold) }}</strong></template>
                <template #item.card_fee_amount="{ item }">{{ money(item.card_fee_amount) }}</template>
                <template #item.net_amount="{ item }"><strong>{{ money(item.net_amount) }}</strong></template>
                <template #item.methods="{ item }"><div class="daily-methods"><span v-for="method in item.methods.filter(entry => Number(entry.amount) > 0)" :key="method.method">{{ method.method_label }}: <strong>{{ money(method.amount) }}</strong></span></div></template>
            </VDataTable>
            <div class="mobile-cards pa-4 pt-2"><VCard v-for="day in daily" :key="day.date" variant="outlined" class="mb-3"><VCardTitle class="pa-4 pb-2 text-body-1">{{ day.date_label }}</VCardTitle><VCardText><div class="mobile-stat"><span>Ventas</span><strong>{{ count(day.sales_count) }}</strong></div><div class="mobile-stat"><span>Servicios</span><strong>{{ count(day.services_count) }}</strong></div><div class="mobile-stat"><span>Bruto</span><strong>{{ money(day.total_sold) }}</strong></div><div class="mobile-stat"><span>Comisión POS</span><strong>{{ money(day.card_fee_amount) }}</strong></div><div class="mobile-stat"><span>Ingreso neto</span><strong>{{ money(day.net_amount) }}</strong></div><div v-for="method in day.methods.filter(entry => Number(entry.amount) > 0)" :key="method.method" class="mobile-stat"><span>{{ method.method_label }}</span><strong>{{ money(method.amount) }}</strong></div></VCardText></VCard></div>
        </VCard>
        <VCard v-else-if="actual" class="surface-card report-section"><EmptyState icon="mdi-chart-box-outline" title="No hay ventas en este periodo" description="Las ventas completadas aparecerán aquí automáticamente." /></VCard>
    </AppLayout>
</template>

<style scoped>
.filter-actions { align-items: center; min-height: 56px; }
.report-section { margin-top: 32px; }
.section-heading { display: flex; align-items: end; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
.projection-section { padding: 24px; border: 1px solid rgba(var(--v-theme-primary), .22); border-radius: 18px; background: rgba(var(--v-theme-primary), .035); }
.projection-card { border-top: 3px solid rgba(var(--v-theme-primary), .5); }
.report-loading { pointer-events: none; opacity: .58; transition: opacity 160ms ease; }
.mobile-stat { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; padding: 7px 0; color: rgba(var(--v-theme-on-surface), .68); }
.mobile-stat strong { color: rgb(var(--v-theme-on-surface)); text-align: right; }
.daily-methods { display: grid; gap: 3px; white-space: nowrap; }
@media (max-width: 700px) {
    .filter-actions .v-btn { flex: 1 1 140px; }
    .section-heading { align-items: start; flex-direction: column; }
    .projection-section { padding: 16px 12px; margin-inline: -4px; }
}
</style>

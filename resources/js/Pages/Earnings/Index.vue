<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { usePermissions } from '../../composables/usePermissions';
import type {
    DailySummary,
    EarningsFilters,
    EarningsPeriod,
    EarningsSummary,
    EmployeeOption,
    EmployeeSummary,
} from '../../types/earnings';

const props = defineProps<{
    filters: EarningsFilters;
    period: EarningsPeriod;
    summary: EarningsSummary;
    employees: EmployeeSummary[];
    daily: DailySummary[];
    employeeOptions: EmployeeOption[];
}>();

const page = usePage();
const { can } = usePermissions();
const isOwner = computed(() => ((page.props.auth as any)?.roles ?? []).includes('owner'));
const form = useForm({
    period: props.filters.period,
    date: props.filters.date ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    employee_id: props.filters.employee_id ?? null as number | null,
    payment_method: props.filters.payment_method ?? null as 'cash' | 'card' | null,
});

const periodOptions = [
    { title: 'Hoy', value: 'today' },
    { title: 'Esta semana', value: 'week' },
    { title: 'Este mes', value: 'month' },
    { title: 'Personalizado', value: 'custom' },
];
const employeeOptions = computed(() => [
    { id: null, name: 'Todos los empleados' },
    ...props.employeeOptions,
]);
const paymentMethodOptions = [
    { title: 'Todos', value: null },
    { title: 'Efectivo', value: 'cash' },
    { title: 'Tarjeta', value: 'card' },
];
const employeeHeaders = [
    { title: 'Empleado', key: 'name' },
    { title: 'Ventas', key: 'sales_count', align: 'end' as const },
    { title: 'Servicios realizados', key: 'services_count', align: 'end' as const },
    { title: 'Ingresos brutos', key: 'total_sold', align: 'end' as const },
    { title: 'Comisión POS', key: 'card_fee_amount', align: 'end' as const },
    { title: 'Ingreso neto', key: 'net_amount', align: 'end' as const },
];
const dailyHeaders = [
    { title: 'Fecha', key: 'date_label' },
    { title: 'Ventas', key: 'sales_count', align: 'end' as const },
    { title: 'Servicios realizados', key: 'services_count', align: 'end' as const },
    { title: 'Ingresos brutos', key: 'total_sold', align: 'end' as const },
    { title: 'Comisión POS', key: 'card_fee_amount', align: 'end' as const },
    { title: 'Ingreso neto', key: 'net_amount', align: 'end' as const },
];
const metricCards = computed(() => [
    { label: 'Ingresos brutos', value: money(props.summary.total_sold), secondary: `Promedio por venta: ${money(props.summary.average_sale)}`, icon: 'mdi-cash-multiple' },
    { label: 'Comisión POS', value: money(props.summary.card_fee_amount), icon: 'mdi-credit-card-minus-outline' },
    { label: 'Ingreso neto', value: money(props.summary.net_amount), icon: 'mdi-cash-check' },
    { label: 'Ventas realizadas', value: count(props.summary.sales_count), icon: 'mdi-receipt-text-check-outline' },
    { label: 'Servicios realizados', value: count(props.summary.services_count), icon: 'mdi-hand-heart-outline' },
]);

function money(value: string): string {
    return new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));
}

function count(value: number): string {
    return new Intl.NumberFormat('es-HN').format(value);
}

function applyFilters(): void {
    if (form.processing) return;

    form.get('/earnings', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters(): void {
    if (form.processing) return;

    router.get('/earnings', {}, { replace: true });
}
</script>

<template>
    <Head title="Ganancias Generales" />
    <AppLayout title="Ganancias Generales">
        <PageHeader
            eyebrow="Resumen de ventas"
            title="Ganancias Generales"
            description="Consulta los ingresos por servicios y el rendimiento de cada empleado."
        />

        <VAlert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-6"
            icon="mdi-information-outline"
        >
            Los valores mostrados corresponden a ingresos brutos por servicios. Todavía no incluyen costos ni gastos.
            <span class="d-block text-caption mt-1">El ingreso neto mostrado descuenta únicamente la comisión del POS. Todavía no incluye otros costos o gastos.</span>
        </VAlert>

        <VCard class="surface-card mb-6">
            <VProgressLinear v-if="form.processing" indeterminate color="primary" />
            <VCardText class="pa-4 pa-sm-5">
                <VForm @submit.prevent="applyFilters">
                    <VRow class="filter-bar pa-2" align="start">
                        <VCol cols="12" sm="6" lg="2">
                            <VSelect
                                v-model="form.period"
                                label="Periodo"
                                :items="periodOptions"
                                :error-messages="form.errors.period"
                                :disabled="form.processing"
                            />
                        </VCol>
                        <VCol v-if="form.period !== 'custom'" cols="12" sm="6" lg="2">
                            <VTextField
                                v-model="form.date"
                                type="date"
                                label="Fecha de referencia"
                                :error-messages="form.errors.date"
                                :disabled="form.processing"
                            />
                        </VCol>
                        <template v-else>
                            <VCol cols="12" sm="6" lg="2">
                                <VTextField
                                    v-model="form.date_from"
                                    type="date"
                                    label="Desde"
                                    :error-messages="form.errors.date_from"
                                    :disabled="form.processing"
                                />
                            </VCol>
                            <VCol cols="12" sm="6" lg="2">
                                <VTextField
                                    v-model="form.date_to"
                                    type="date"
                                    label="Hasta"
                                    :error-messages="form.errors.date_to"
                                    :disabled="form.processing"
                                />
                            </VCol>
                        </template>
                        <VCol cols="12" sm="6" lg="2">
                            <VSelect
                                v-model="form.employee_id"
                                label="Empleado"
                                :items="employeeOptions"
                                item-title="name"
                                item-value="id"
                                :error-messages="form.errors.employee_id"
                                :disabled="form.processing"
                            />
                        </VCol>
                        <VCol cols="12" sm="6" lg="2">
                            <VSelect
                                v-model="form.payment_method"
                                label="Método de pago"
                                :items="paymentMethodOptions"
                                :error-messages="form.errors.payment_method"
                                :disabled="form.processing"
                            />
                        </VCol>
                        <VCol cols="12" lg="2" class="d-flex flex-wrap ga-2 filter-actions">
                            <VBtn type="submit" color="primary" prepend-icon="mdi-filter-check-outline" :loading="form.processing">
                                Aplicar
                            </VBtn>
                            <VBtn variant="text" prepend-icon="mdi-filter-off-outline" :disabled="form.processing" @click="resetFilters">
                                Restablecer
                            </VBtn>
                        </VCol>
                    </VRow>
                </VForm>
            </VCardText>
        </VCard>

        <VRow class="mb-2" :class="{ 'report-loading': form.processing }">
            <VCol v-for="metric in metricCards" :key="metric.label" cols="12" sm="6" lg="4">
                <VCard class="metric-card report-metric pa-1">
                    <VCardText class="pa-5">
                        <div class="d-flex align-start justify-space-between ga-3 mb-5">
                            <span class="text-body-2 text-medium-emphasis">{{ metric.label }}</span>
                            <VAvatar color="primary" variant="tonal" size="38"><VIcon :icon="metric.icon" size="20" /></VAvatar>
                        </div>
                        <div class="text-h5 font-weight-bold text-no-wrap">{{ metric.value }}</div>
                        <div class="text-caption text-medium-emphasis mt-2">{{ period.label }}</div>
                        <div v-if="metric.secondary" class="text-caption font-weight-medium mt-1">{{ metric.secondary }}</div>
                    </VCardText>
                </VCard>
            </VCol>
        </VRow>

        <VCard v-if="summary.sales_count === 0" class="surface-card mt-4" :class="{ 'report-loading': form.processing }">
            <EmptyState
                icon="mdi-chart-box-outline"
                title="No hay ventas en este periodo"
                description="Las ventas registradas aparecerán aquí automáticamente."
            >
                <VBtn
                    v-if="isOwner && can('sales.create')"
                    color="primary"
                    prepend-icon="mdi-receipt-text-plus-outline"
                    @click="router.visit('/sales/new')"
                >
                    Ir a Nueva venta
                </VBtn>
            </EmptyState>
        </VCard>

        <template v-else>
            <VCard class="surface-card mt-4 mb-6" :class="{ 'report-loading': form.processing }">
                <VCardItem class="pa-5 pb-2">
                    <VCardTitle class="text-h6 font-weight-bold">Rendimiento por empleado</VCardTitle>
                    <VCardSubtitle>Resultados de {{ period.label }}</VCardSubtitle>
                </VCardItem>

                <VDataTable :headers="employeeHeaders" :items="employees" class="desktop-table" hide-default-footer>
                    <template #item.total_sold="{ item }"><span class="font-weight-bold">{{ money(item.total_sold) }}</span></template>
                    <template #item.card_fee_amount="{ item }">{{ money(item.card_fee_amount) }}</template>
                    <template #item.net_amount="{ item }"><span class="font-weight-bold">{{ money(item.net_amount) }}</span></template>
                </VDataTable>

                <div class="mobile-cards pa-4 pt-2">
                    <VCard v-for="employee in employees" :key="employee.id" variant="outlined" class="mb-3">
                        <VCardItem>
                            <VCardTitle class="text-body-1 font-weight-bold">{{ employee.name }}</VCardTitle>
                            <template #append><span class="text-body-1 font-weight-bold text-primary">{{ money(employee.net_amount) }}</span></template>
                        </VCardItem>
                        <VCardText class="pt-1">
                            <div class="mobile-stat"><span>Ventas</span><strong>{{ count(employee.sales_count) }}</strong></div>
                            <div class="mobile-stat"><span>Servicios</span><strong>{{ count(employee.services_count) }}</strong></div>
                            <div class="mobile-stat"><span>Ingresos brutos</span><strong>{{ money(employee.total_sold) }}</strong></div>
                            <div class="mobile-stat"><span>Comisión POS</span><strong>{{ money(employee.card_fee_amount) }}</strong></div>
                            <div class="mobile-stat"><span>Ingreso neto</span><strong>{{ money(employee.net_amount) }}</strong></div>
                        </VCardText>
                    </VCard>
                </div>
            </VCard>

            <VCard class="surface-card" :class="{ 'report-loading': form.processing }">
                <VCardItem class="pa-5 pb-2">
                    <VCardTitle class="text-h6 font-weight-bold">Cierres diarios</VCardTitle>
                    <VCardSubtitle class="mt-1 text-wrap">
                        Este resumen se calcula automáticamente a partir de las ventas registradas. No requiere cierre manual.
                    </VCardSubtitle>
                </VCardItem>

                <VDataTable :headers="dailyHeaders" :items="daily" class="desktop-table" hide-default-footer>
                    <template #item.total_sold="{ item }"><span class="font-weight-bold">{{ money(item.total_sold) }}</span></template>
                    <template #item.card_fee_amount="{ item }">{{ money(item.card_fee_amount) }}</template>
                    <template #item.net_amount="{ item }"><span class="font-weight-bold">{{ money(item.net_amount) }}</span></template>
                </VDataTable>

                <div class="mobile-cards pa-4 pt-2">
                    <VCard v-for="day in daily" :key="day.date" variant="outlined" class="mb-3">
                        <VCardItem>
                            <VCardTitle class="text-body-1 font-weight-bold">{{ day.date_label }}</VCardTitle>
                        </VCardItem>
                        <VCardText class="pt-1">
                            <div class="mobile-stat"><span>Ventas</span><strong>{{ count(day.sales_count) }}</strong></div>
                            <div class="mobile-stat"><span>Servicios</span><strong>{{ count(day.services_count) }}</strong></div>
                            <div class="mobile-stat"><span>Ingresos brutos</span><strong>{{ money(day.total_sold) }}</strong></div>
                            <div class="mobile-stat"><span>Comisión POS</span><strong>{{ money(day.card_fee_amount) }}</strong></div>
                            <div class="mobile-stat"><span>Ingreso neto</span><strong class="text-primary">{{ money(day.net_amount) }}</strong></div>
                        </VCardText>
                    </VCard>
                </div>
            </VCard>
        </template>
    </AppLayout>
</template>

<style scoped>
.filter-actions {
    align-items: center;
    min-height: 56px;
}

.report-metric {
    overflow: hidden;
}

.report-loading {
    pointer-events: none;
    opacity: 0.58;
    transition: opacity 160ms ease;
}

.mobile-stat {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 16px;
    padding: 7px 0;
    color: rgba(var(--v-theme-on-surface), 0.68);
}

.mobile-stat strong {
    color: rgb(var(--v-theme-on-surface));
    text-align: right;
}

@media (max-width: 700px) {
    .filter-actions .v-btn {
        flex: 1 1 140px;
    }

    .report-metric .text-h5 {
        font-size: 1.3rem !important;
    }
}
</style>

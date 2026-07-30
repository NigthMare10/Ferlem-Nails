<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
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
    ExpenseActual,
    ExpensePaymentDistribution,
    PaymentDistribution,
} from '../../types/earnings';

const props = defineProps<{
    filters: EarningsFilters;
    period: EarningsPeriod;
    canViewProjection: boolean;
    canViewSales: boolean;
    canViewExpenses: boolean;
    canManageDailyClose: boolean;
    canDownloadDailyClose?: boolean;
    actual?: ActualResults;
    projection?: AppointmentProjection;
    employees: EmployeeSummary[];
    daily?: DailySummary[];
    employeeOptions: EmployeeOption[];
    payment_distribution?: PaymentDistribution[];
    expense_actual?: ExpenseActual;
    expense_payment_distribution?: ExpensePaymentDistribution[];
}>();

const { smAndDown } = useDisplay();
const filtersOpen = ref(false);
const requestError = ref('');
const sendingClose = ref(false);
const form = useForm({
    period: props.filters.period,
    mode: props.filters.mode,
    date: props.filters.date ?? '',
    month: props.filters.month ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    employee_id: props.filters.employee_id ?? (null as number | null),
    payment_method: props.filters.payment_method ?? (null as 'cash' | 'card' | 'transfer' | null),
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
const closeDate = computed(() => props.filters.date ?? props.period.end_date);
const downloadUrl = computed(() => `/daily-close/download?date=${encodeURIComponent(closeDate.value)}`);
const totalEmployeeGross = computed(() => props.employees.reduce((total, employee) => total + Number(employee.total_sold), 0));
const hasSales = computed(() => (props.actual?.completed_sales_count ?? 0) > 0);
const activeFilterSummary = computed(() => {
    const items = [props.period.label];
    if (props.canViewProjection) items.push(modeOptions.find((item) => item.value === props.filters.mode)?.title ?? 'Resultados');
    if (props.filters.employee_id) items.push(props.employeeOptions.find((item) => item.id === props.filters.employee_id)?.name ?? 'Empleado');
    if (props.filters.payment_method) items.push(paymentMethodOptions.find((item) => item.value === props.filters.payment_method)?.title ?? 'Método');
    return items;
});
const kpis = computed(() =>
    props.actual
        ? [
              {
                  label: 'Ingresos brutos',
                  value: money(props.actual.gross_revenue),
                  icon: 'mdi-cash-multiple',
                  context: `${count(props.actual.completed_sales_count)} ventas completadas`,
                  tone: 'positive',
              },
              {
                  label: 'Comisión POS',
                  value: money(props.actual.pos_fee),
                  icon: 'mdi-credit-card-minus-outline',
                  context: 'Descuento de pagos con tarjeta',
                  tone: Number(props.actual.pos_fee) > 0 ? 'neutral' : 'quiet',
              },
              {
                  label: 'Ingreso neto',
                  value: money(props.actual.net_income),
                  icon: 'mdi-trending-up',
                  context: `Promedio ${money(props.actual.average_sale)}`,
                  tone: 'positive',
              },
              ...(props.actual.paid_expenses !== undefined
                  ? [
                        {
                            label: 'Gastos pagados',
                            value: money(props.actual.paid_expenses),
                            icon: 'mdi-receipt-text-minus-outline',
                            context: `${count(props.expense_actual?.expenses_count ?? 0)} registros`,
                            tone: Number(props.actual.paid_expenses) > 0 ? 'neutral' : 'quiet',
                        },
                    ]
                  : []),
              ...(props.actual.available_result !== undefined
                  ? [
                        {
                            label: 'Resultado disponible',
                            value: money(props.actual.available_result),
                            icon: Number(props.actual.available_result) < 0 ? 'mdi-trending-down' : 'mdi-wallet-check-outline',
                            context: 'Ingreso neto menos gastos registrados',
                            tone: Number(props.actual.available_result) < 0 ? 'negative' : 'result',
                        },
                    ]
                  : []),
          ]
        : [],
);
const employeeHeaders = computed(() => [
    { title: 'Empleado', key: 'name' },
    { title: 'Servicios', key: 'services_count', align: 'end' as const },
    { title: 'Ingreso bruto', key: 'total_sold', align: 'end' as const },
    { title: 'Comisión POS', key: 'card_fee_amount', align: 'end' as const },
    { title: 'Comisión empleado', key: 'employee_commission', align: 'end' as const, sortable: false },
    { title: 'Ingreso neto', key: 'net_amount', align: 'end' as const },
    ...(hasProjection.value ? [{ title: 'Proyección', key: 'projected_income', align: 'end' as const }] : []),
    { title: 'Participación', key: 'participation', align: 'end' as const, sortable: false },
]);
const dailyHeaders = [
    { title: 'Fecha', key: 'date_label' },
    { title: 'Ventas', key: 'sales_count', align: 'end' as const },
    { title: 'Servicios', key: 'services_count', align: 'end' as const },
    { title: 'Ingresos brutos', key: 'total_sold', align: 'end' as const },
    { title: 'Comisión POS', key: 'card_fee_amount', align: 'end' as const },
    { title: 'Ingreso neto', key: 'net_amount', align: 'end' as const },
    { title: 'Cobros', key: 'methods', sortable: false },
];

function money(value: string): string {
    return new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));
}
function count(value: number): string {
    return new Intl.NumberFormat('es-HN').format(value);
}
function participation(employee: EmployeeSummary): string {
    return totalEmployeeGross.value === 0
        ? '0%'
        : `${new Intl.NumberFormat('es-HN', { maximumFractionDigits: 1 }).format((Number(employee.total_sold) * 100) / totalEmployeeGross.value)}%`;
}
function applyFilters(): void {
    if (form.processing) return;
    requestError.value = '';
    form.get('/earnings', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onError: () => {
            requestError.value = 'No pudimos actualizar el informe. Revisa los filtros e inténtalo nuevamente.';
        },
    });
}
function resetFilters(): void {
    if (!form.processing) router.get('/earnings', {}, { replace: true });
}
function sendClose(): void {
    sendingClose.value = true;
    router.post(
        '/daily-close/send',
        { date: closeDate.value },
        {
            preserveScroll: true,
            onFinish: () => {
                sendingClose.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Ganancias Generales" />
    <AppLayout title="Ganancias Generales">
        <PageHeader
            eyebrow="Pulso financiero"
            title="Ganancias Generales"
            description="Lee ingresos, gastos y proyección con los mismos cálculos operativos del estudio."
        >
            <template #actions>
                <VBtn v-if="canDownloadDailyClose ?? canManageDailyClose" :href="downloadUrl" variant="tonal" prepend-icon="mdi-file-pdf-box"
                    >Descargar cierre</VBtn
                >
                <VBtn v-if="canManageDailyClose" color="primary" prepend-icon="mdi-email-fast-outline" :loading="sendingClose" @click="sendClose"
                    >Enviar por correo</VBtn
                >
            </template>
        </PageHeader>

        <VAlert v-if="requestError" type="error" variant="tonal" closable class="mb-5" @click:close="requestError = ''">{{ requestError }}</VAlert>
        <VAlert v-if="!canViewSales" type="info" variant="tonal" density="compact" class="mb-5" icon="mdi-lock-outline"
            >Tu acceso muestra gastos autorizados. Las ventas, empleados y métodos de cobro permanecen privados.</VAlert
        >
        <VAlert v-else-if="canViewExpenses" type="info" variant="tonal" density="compact" class="mb-5" icon="mdi-information-outline">
            El resultado disponible resta todos los gastos registrados, incluida la categoría Nómina. No incluye impuestos.
        </VAlert>

        <section class="filter-shell" aria-labelledby="earnings-filter-title">
            <div class="filter-shell__top">
                <div>
                    <h2 id="earnings-filter-title">Filtros del informe</h2>
                    <div class="filter-summary">
                        <span v-for="item in activeFilterSummary" :key="item">{{ item }}</span>
                    </div>
                </div>
                <VBtn v-if="smAndDown" variant="text" :append-icon="filtersOpen ? 'mdi-chevron-up' : 'mdi-tune-variant'" @click="filtersOpen = !filtersOpen">{{
                    filtersOpen ? 'Ocultar filtros' : 'Editar filtros'
                }}</VBtn>
            </div>
            <VProgressLinear v-if="form.processing" indeterminate color="primary" class="filter-progress" />
            <VForm v-show="!smAndDown || filtersOpen" class="filter-form" @submit.prevent="applyFilters">
                <VSelect
                    v-if="canViewProjection"
                    v-model="form.mode"
                    label="Vista"
                    :items="modeOptions"
                    :error-messages="form.errors.mode"
                    :disabled="form.processing"
                />
                <VSelect v-model="form.period" label="Periodo" :items="periodOptions" :error-messages="form.errors.period" :disabled="form.processing" />
                <VTextField
                    v-if="form.period === 'today' || form.period === 'week'"
                    v-model="form.date"
                    type="date"
                    label="Fecha de referencia"
                    :error-messages="form.errors.date"
                    :disabled="form.processing"
                />
                <VTextField
                    v-else-if="form.period === 'month'"
                    v-model="form.month"
                    type="month"
                    label="Mes"
                    :error-messages="form.errors.month"
                    :disabled="form.processing"
                />
                <template v-else
                    ><VTextField
                        v-model="form.date_from"
                        type="date"
                        label="Desde"
                        :error-messages="form.errors.date_from"
                        :disabled="form.processing" /><VTextField
                        v-model="form.date_to"
                        type="date"
                        label="Hasta"
                        :error-messages="form.errors.date_to"
                        :disabled="form.processing"
                /></template>
                <VSelect
                    v-if="canViewSales"
                    v-model="form.employee_id"
                    label="Empleado"
                    :items="employeeOptions"
                    item-title="name"
                    item-value="id"
                    :error-messages="form.errors.employee_id"
                    :disabled="form.processing"
                />
                <VSelect
                    v-if="canViewSales"
                    v-model="form.payment_method"
                    label="Método de pago"
                    :items="paymentMethodOptions"
                    :error-messages="form.errors.payment_method"
                    :disabled="form.processing"
                />
                <div class="filter-actions">
                    <VBtn type="submit" color="primary" prepend-icon="mdi-filter-check-outline" :loading="form.processing">Aplicar</VBtn
                    ><VBtn variant="text" prepend-icon="mdi-filter-off-outline" :disabled="form.processing" @click="resetFilters">Restablecer</VBtn>
                </div>
            </VForm>
        </section>

        <div class="report-content" :class="{ 'report-content--loading': form.processing }" :aria-busy="form.processing">
            <section v-if="actual" class="financial-summary" aria-labelledby="financial-summary-title">
                <div class="section-heading">
                    <div>
                        <span>Ventas completadas</span>
                        <h2 id="financial-summary-title">Resumen del periodo</h2>
                    </div>
                    <p>{{ period.label }}</p>
                </div>
                <div class="kpi-grid">
                    <article v-for="kpi in kpis" :key="kpi.label" class="kpi" :class="`kpi--${kpi.tone}`">
                        <VIcon :icon="kpi.icon" size="23" />
                        <div>
                            <span>{{ kpi.label }}</span
                            ><strong>{{ kpi.value }}</strong
                            ><small>{{ kpi.context }}</small>
                        </div>
                    </article>
                </div>
                <div v-if="actual.canceled_sales_count" class="cancellation-note">
                    <VIcon icon="mdi-cancel" size="18" /><span
                        >{{ count(actual.canceled_sales_count) }} ventas anuladas por {{ money(actual.canceled_amount) }}. No forman parte de los KPI.</span
                    >
                </div>
            </section>

            <section v-if="actual && payment_distribution" class="flow-section" aria-labelledby="payment-title">
                <div class="section-heading">
                    <div>
                        <span>Liquidez</span>
                        <h2 id="payment-title">Métodos de cobro</h2>
                    </div>
                    <p>Pagos recibidos en ventas completadas</p>
                </div>
                <div class="method-flow">
                    <article v-for="method in payment_distribution" :key="method.method" class="method-flow__item">
                        <div class="method-flow__name">
                            <VIcon
                                :icon="method.method === 'cash' ? 'mdi-cash' : method.method === 'card' ? 'mdi-credit-card-outline' : 'mdi-bank-transfer'"
                                size="20"
                            /><strong>{{ method.method_label }}</strong>
                        </div>
                        <strong class="method-flow__amount">{{ money(method.amount) }}</strong>
                        <span
                            >{{ count(method.payments_count) }} pagos<template v-if="method.method === 'card'">
                                · POS {{ money(method.card_fee_amount) }} · neto {{ money(method.net_amount) }}</template
                            ></span
                        >
                    </article>
                </div>
            </section>

            <section v-if="canViewExpenses && expense_actual" class="flow-section" aria-labelledby="expense-title">
                <div class="section-heading">
                    <div>
                        <span>Métodos de gasto</span>
                        <h2 id="expense-title">Gastos del periodo</h2>
                    </div>
                    <p>{{ count(expense_actual.expenses_count) }} movimientos · {{ money(expense_actual.paid_expenses) }}</p>
                </div>
                <VAlert v-if="form.employee_id" type="info" variant="tonal" density="compact" class="mb-4"
                    >El filtro de empleado afecta ventas y proyección; los gastos generales permanecen completos.</VAlert
                >
                <div v-if="expense_payment_distribution" class="expense-methods">
                    <div v-for="method in expense_payment_distribution" :key="method.method">
                        <span>{{ method.method_label }}</span
                        ><strong>{{ money(method.total) }}</strong
                        ><small>{{ count(method.expenses_count) }} gastos</small>
                    </div>
                </div>
            </section>

            <section v-if="projection" class="projection-band" aria-labelledby="projection-title">
                <div class="projection-band__intro">
                    <span>Agenda programada</span>
                    <h2 id="projection-title">Proyección</h2>
                    <p>Expectativa basada solo en citas programadas; los adelantos no se suman dos veces.</p>
                </div>
                <div class="projection-band__metrics">
                    <div>
                        <span>Bruto proyectado</span><strong>{{ money(projection.projected_gross) }}</strong>
                    </div>
                    <div>
                        <span>Saldo pendiente</span><strong>{{ money(projection.pending_balance) }}</strong>
                    </div>
                    <div>
                        <span>Adelantos recibidos</span><strong>{{ money(projection.deposits_received) }}</strong>
                    </div>
                    <div>
                        <span>Agenda</span><strong>{{ count(projection.appointments_count) }} citas · {{ count(projection.services_count) }} servicios</strong>
                    </div>
                </div>
            </section>

            <section class="report-panel" aria-labelledby="employee-title">
                <div class="section-heading report-panel__heading">
                    <div>
                        <span>Contribución real</span>
                        <h2 id="employee-title">Rendimiento por empleado</h2>
                    </div>
                    <p>Servicios y cargos se atribuyen a quien realiza el trabajo, no a quien cobra</p>
                </div>
                <template v-if="employees.length">
                    <VDataTable :headers="employeeHeaders" :items="employees" class="desktop-table" :items-per-page="-1" hide-default-footer>
                        <template #item.total_sold="{ item }"
                            ><strong>{{ money(item.total_sold) }}</strong></template
                        ><template #item.card_fee_amount="{ item }">{{ money(item.card_fee_amount) }}</template
                        ><template #item.employee_commission><span class="text-medium-emphasis">No configurada</span></template
                        ><template #item.net_amount="{ item }"
                            ><strong>{{ money(item.net_amount) }}</strong></template
                        ><template #item.projected_income="{ item }">{{ money(item.projected_income ?? '0.00') }}</template
                        ><template #item.participation="{ item }"
                            ><strong>{{ participation(item) }}</strong></template
                        >
                    </VDataTable>
                    <div class="mobile-cards employee-cards">
                        <article v-for="employee in employees" :key="employee.id" class="employee-card">
                            <div class="employee-card__head">
                                <div>
                                    <strong>{{ employee.name }}</strong
                                    ><span>{{ count(employee.services_count) }} servicios realizados</span>
                                </div>
                                <b>{{ participation(employee) }}</b>
                            </div>
                            <dl>
                                <div>
                                    <dt>Ingreso bruto</dt>
                                    <dd>{{ money(employee.total_sold) }}</dd>
                                </div>
                                <div>
                                    <dt>Comisión POS</dt>
                                    <dd>{{ money(employee.card_fee_amount) }}</dd>
                                </div>
                                <div>
                                    <dt>Comisión empleado</dt>
                                    <dd>No configurada</dd>
                                </div>
                                <div>
                                    <dt>Ingreso neto</dt>
                                    <dd>{{ money(employee.net_amount) }}</dd>
                                </div>
                                <div v-if="hasProjection">
                                    <dt>Proyección</dt>
                                    <dd>{{ money(employee.projected_income ?? '0.00') }}</dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                </template>
                <EmptyState
                    v-else
                    icon="mdi-account-off-outline"
                    title="Sin rendimiento para mostrar"
                    description="No hay servicios atribuidos a empleados con los filtros seleccionados."
                />
            </section>

            <section class="report-panel" aria-labelledby="daily-title">
                <div class="section-heading report-panel__heading">
                    <div>
                        <span>Detalle temporal</span>
                        <h2 id="daily-title">Resultados diarios</h2>
                    </div>
                    <p>Solo ventas completadas</p>
                </div>
                <template v-if="daily?.length">
                    <VDataTable :headers="dailyHeaders" :items="daily" class="desktop-table" :items-per-page="-1" hide-default-footer
                        ><template #item.total_sold="{ item }"
                            ><strong>{{ money(item.total_sold) }}</strong></template
                        ><template #item.card_fee_amount="{ item }">{{ money(item.card_fee_amount) }}</template
                        ><template #item.net_amount="{ item }"
                            ><strong>{{ money(item.net_amount) }}</strong></template
                        ><template #item.methods="{ item }"
                            ><div class="daily-methods">
                                <span v-for="method in item.methods.filter((entry) => Number(entry.amount) > 0)" :key="method.method"
                                    >{{ method.method_label }} {{ money(method.amount) }}</span
                                >
                            </div></template
                        ></VDataTable
                    >
                    <div class="mobile-cards daily-cards">
                        <article v-for="day in daily" :key="day.date" class="daily-card">
                            <div>
                                <strong>{{ day.date_label }}</strong
                                ><span>{{ count(day.sales_count) }} ventas · {{ count(day.services_count) }} servicios</span>
                            </div>
                            <dl>
                                <div>
                                    <dt>Bruto</dt>
                                    <dd>{{ money(day.total_sold) }}</dd>
                                </div>
                                <div>
                                    <dt>POS</dt>
                                    <dd>{{ money(day.card_fee_amount) }}</dd>
                                </div>
                                <div>
                                    <dt>Neto</dt>
                                    <dd>{{ money(day.net_amount) }}</dd>
                                </div>
                            </dl>
                            <p>
                                <span v-for="method in day.methods.filter((entry) => Number(entry.amount) > 0)" :key="method.method"
                                    >{{ method.method_label }} {{ money(method.amount) }}</span
                                >
                            </p>
                        </article>
                    </div>
                </template>
                <EmptyState
                    v-else
                    icon="mdi-chart-box-outline"
                    title="No hay ventas en este periodo"
                    description="Las ventas completadas aparecerán aquí automáticamente; anulaciones y citas no se mezclan."
                />
            </section>

            <VAlert v-if="actual && !hasSales" type="info" variant="tonal" icon="mdi-information-outline"
                >El informe está completo, pero no existen ventas con los filtros actuales. Los importes en cero son resultados reales, no datos
                faltantes.</VAlert
            >
        </div>
    </AppLayout>
</template>

<style scoped>
.filter-shell {
    position: relative;
    margin-bottom: 34px;
    padding: 18px;
    border-radius: var(--sl-radius-surface);
    background: color-mix(in oklch, var(--sl-surface-soft) 78%, var(--sl-surface));
    box-shadow: var(--sl-shadow-inset);
}
.filter-shell__top {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 16px;
}
.filter-shell h2 {
    margin: 0 0 8px;
    font-size: 1rem;
}
.filter-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.filter-summary span {
    padding: 4px 9px;
    color: var(--sl-primary-strong);
    border-radius: var(--sl-radius-pill);
    background: color-mix(in oklch, var(--sl-secondary) 22%, var(--sl-surface));
    font-size: 0.75rem;
    font-weight: 600;
}
.filter-progress {
    position: absolute;
    right: 16px;
    bottom: 0;
    left: 16px;
    width: auto;
}
.filter-form {
    display: grid;
    grid-template-columns: repeat(6, minmax(130px, 1fr));
    align-items: start;
    gap: 12px;
    margin-top: 18px;
}
.filter-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    min-height: 56px;
}
.report-content {
    display: grid;
    gap: 42px;
    transition: opacity var(--sl-duration-fast) var(--sl-ease);
}
.report-content--loading {
    pointer-events: none;
    opacity: 0.52;
}
.section-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 16px;
}
.section-heading span,
.projection-band__intro > span {
    color: var(--sl-primary);
    font-size: var(--sl-label-size);
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.section-heading h2,
.projection-band h2 {
    margin: 3px 0 0;
    font-size: var(--sl-section-title-size);
}
.section-heading p {
    max-width: 52ch;
    margin: 0;
    color: var(--sl-text-muted);
    text-align: right;
}
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
}
.kpi {
    display: grid;
    grid-template-columns: 24px 1fr;
    gap: 12px;
    min-height: 142px;
    padding: 20px;
    border-radius: var(--sl-radius-surface);
    background: var(--sl-surface);
    box-shadow: 0 9px 24px oklch(32% 0.025 346 / 0.07);
}
.kpi > .v-icon {
    color: var(--sl-primary);
}
.kpi div {
    display: grid;
    align-content: space-between;
    gap: 6px;
}
.kpi span,
.kpi small {
    color: var(--sl-text-muted);
}
.kpi strong {
    font-size: var(--sl-metric-size);
    letter-spacing: -0.025em;
    white-space: nowrap;
}
.kpi--positive {
    grid-column: span 2;
}
.kpi--neutral,
.kpi--quiet {
    grid-column: span 2;
    background: var(--sl-surface-soft);
}
.kpi--result,
.kpi--negative {
    grid-column: span 2;
    background: color-mix(in oklch, var(--sl-secondary) 28%, var(--sl-surface));
    box-shadow: 0 12px 28px var(--sl-plum-relief);
}
.kpi--result strong {
    color: var(--sl-primary-strong);
}
.kpi--negative {
    background: color-mix(in oklch, var(--sl-error) 10%, var(--sl-surface));
}
.kpi--negative strong,
.kpi--negative > .v-icon {
    color: var(--sl-error);
}
.cancellation-note {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    color: var(--sl-text-muted);
    font-size: var(--sl-label-size);
}
.method-flow {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    overflow: hidden;
    border-radius: var(--sl-radius-surface);
    background: var(--sl-surface-soft);
}
.method-flow__item {
    display: grid;
    align-content: start;
    gap: 8px;
    min-height: 132px;
    padding: 20px;
}
.method-flow__item + .method-flow__item {
    border-left: 1px solid var(--sl-border);
}
.method-flow__name {
    display: flex;
    align-items: center;
    gap: 8px;
}
.method-flow__amount {
    font-size: var(--sl-metric-size);
}
.method-flow__item > span {
    color: var(--sl-text-muted);
    font-size: var(--sl-label-size);
}
.expense-methods {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1px;
    overflow: hidden;
    border-radius: var(--sl-radius-surface);
    background: var(--sl-border);
}
.expense-methods > div {
    display: grid;
    gap: 5px;
    padding: 18px;
    background: var(--sl-surface);
}
.expense-methods span,
.expense-methods small {
    color: var(--sl-text-muted);
}
.projection-band {
    display: grid;
    grid-template-columns: minmax(230px, 0.7fr) minmax(0, 1.3fr);
    gap: 32px;
    padding: 26px;
    border-radius: var(--sl-radius-surface);
    background: color-mix(in oklch, var(--sl-secondary) 18%, var(--sl-surface));
    box-shadow: var(--sl-shadow-inset);
}
.projection-band__intro p {
    margin: 10px 0 0;
    color: var(--sl-text-muted);
}
.projection-band__metrics {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1px;
    overflow: hidden;
    border-radius: var(--sl-radius-compact);
    background: var(--sl-border);
}
.projection-band__metrics div {
    display: grid;
    gap: 5px;
    padding: 15px;
    background: color-mix(in oklch, var(--sl-surface) 86%, transparent);
}
.projection-band__metrics span {
    color: var(--sl-text-muted);
    font-size: var(--sl-label-size);
}
.report-panel {
    overflow: hidden;
    border-radius: var(--sl-radius-surface);
    background: var(--sl-surface);
    box-shadow: 0 10px 28px oklch(32% 0.025 346 / 0.065);
}
.report-panel__heading {
    padding: 20px 22px 4px;
}
.daily-methods {
    display: grid;
    gap: 2px;
    color: var(--sl-text-muted);
    font-size: var(--sl-label-size);
    white-space: nowrap;
}
.mobile-cards {
    display: none;
}
.employee-cards,
.daily-cards {
    padding: 0 14px 14px;
}
.employee-card,
.daily-card {
    padding: 16px;
    border-radius: var(--sl-radius-compact);
    background: var(--sl-surface-soft);
}
.employee-card + .employee-card,
.daily-card + .daily-card {
    margin-top: 10px;
}
.employee-card__head,
.daily-card > div:first-child {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}
.employee-card__head > div,
.daily-card > div:first-child {
    display: grid;
    gap: 3px;
}
.employee-card__head span,
.daily-card span {
    color: var(--sl-text-muted);
    font-size: var(--sl-label-size);
}
.employee-card__head b {
    color: var(--sl-primary);
}
.employee-card dl,
.daily-card dl {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px 18px;
    margin: 0;
}
.employee-card dl div,
.daily-card dl div {
    display: grid;
    gap: 2px;
}
.employee-card dt,
.daily-card dt {
    color: var(--sl-text-muted);
    font-size: 0.75rem;
}
.employee-card dd,
.daily-card dd {
    margin: 0;
    font-weight: 700;
}
.daily-card p {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 12px;
    margin: 12px 0 0;
}
@media (max-width: 1180px) {
    .filter-form {
        grid-template-columns: repeat(3, 1fr);
    }
    .kpi-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .kpi,
    .kpi--positive,
    .kpi--neutral,
    .kpi--quiet,
    .kpi--result,
    .kpi--negative {
        grid-column: span 1;
    }
    .kpi--result,
    .kpi--negative {
        grid-column: span 2;
    }
}
@media (max-width: 800px) {
    .filter-form {
        grid-template-columns: 1fr 1fr;
    }
    .kpi-grid {
        grid-template-columns: 1fr 1fr;
    }
    .kpi,
    .kpi--positive,
    .kpi--neutral,
    .kpi--quiet {
        grid-column: span 1;
    }
    .kpi--result,
    .kpi--negative {
        grid-column: 1 / -1;
    }
    .projection-band {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 700px) {
    .section-heading {
        align-items: start;
        flex-direction: column;
        gap: 5px;
    }
    .section-heading p {
        text-align: left;
    }
    .desktop-table {
        display: none;
    }
    .mobile-cards {
        display: block;
    }
    .method-flow,
    .expense-methods {
        grid-template-columns: 1fr;
    }
    .method-flow__item {
        min-height: 105px;
    }
    .method-flow__item + .method-flow__item {
        border-top: 1px solid var(--sl-border);
        border-left: 0;
    }
}
@media (max-width: 600px) {
    .filter-shell {
        margin-inline: -4px;
        padding: 14px;
    }
    .filter-form,
    .kpi-grid {
        grid-template-columns: 1fr;
    }
    .kpi,
    .kpi--result,
    .kpi--negative {
        grid-column: 1;
        min-height: 112px;
    }
    .filter-actions {
        flex-wrap: wrap;
    }
    .filter-actions .v-btn {
        flex: 1 1 130px;
    }
    .projection-band {
        padding: 20px 16px;
    }
    .projection-band__metrics {
        grid-template-columns: 1fr;
    }
    .report-panel {
        margin-inline: -4px;
    }
    .report-panel__heading {
        padding-inline: 16px;
    }
    .employee-card dl,
    .daily-card dl {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

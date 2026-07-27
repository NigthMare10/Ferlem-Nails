<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import CancelExpenseDialog from '../../Components/Expenses/CancelExpenseDialog.vue';
import ExpenseFormDialog from '../../Components/Expenses/ExpenseFormDialog.vue';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import type { ExpenseCategory, ExpenseFilters, ExpenseListItem, ExpenseOption, ExpensePage } from '../../types/expenses';

const props = defineProps<{
    expenses: ExpensePage;
    filters: ExpenseFilters;
    categories: ExpenseCategory[];
    employees: ExpenseOption[];
    capabilities: { create: boolean; manage_categories: boolean };
}>();
const page = usePage();
const loading = ref(false);
const mobileFilters = ref<number[]>([]);
const formOpen = ref(false);
const cancelOpen = ref(false);
const selected = ref<ExpenseListItem | null>(null);
const form = ref({
    search: props.filters.search ?? '', date_from: props.filters.date_from ?? '', date_to: props.filters.date_to ?? '',
    category_id: props.filters.category_id ?? null, status: props.filters.status ?? null, payment_method: props.filters.payment_method ?? null,
    employee_id: props.filters.employee_id ?? null, recorded_by: props.filters.recorded_by ?? null,
});
const errors = computed(() => page.props.errors as Record<string, string>);
const records = computed(() => props.expenses.data ?? []);
const activeCategories = computed(() => props.categories.filter(category => category.is_active || category.id === selected.value?.category.id));
const activeEmployees = computed(() => props.employees.filter(employee => employee.is_active || employee.id === selected.value?.employee?.id));
const categoryOptions = computed(() => [{ id: null, name: 'Todas las categorías' }, ...props.categories]);
const employeeOptions = computed(() => [{ id: null, name: 'Todos los empleados' }, ...props.employees]);
const recorderOptions = computed(() => [{ id: null, name: 'Todos los usuarios' }, ...props.employees]);
const statusOptions = [{ title: 'Todos', value: null }, { title: 'Registrados', value: 'recorded' }, { title: 'Anulados', value: 'canceled' }];
const methodOptions = [{ title: 'Todos', value: null }, { title: 'Efectivo', value: 'cash' }, { title: 'Tarjeta', value: 'card' }, { title: 'Transferencia', value: 'transfer' }];
const headers = [
    { title: 'Número', key: 'expense_number', sortable: false }, { title: 'Fecha', key: 'expense_date_display', sortable: false },
    { title: 'Categoría', key: 'category', sortable: false }, { title: 'Descripción', key: 'description', sortable: false },
    { title: 'Empleado', key: 'employee', sortable: false }, { title: 'Método', key: 'payment_method_label', sortable: false },
    { title: 'Monto', key: 'amount', sortable: false, align: 'end' as const }, { title: 'Origen', key: 'origin_label', sortable: false },
    { title: 'Estado', key: 'status', sortable: false }, { title: 'Acciones', key: 'actions', sortable: false, align: 'end' as const },
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
    router.get('/expenses', query(pageNumber), { preserveState: true, preserveScroll: true, replace: true, onFinish: () => { loading.value = false; } });
}
function resetFilters(): void {
    form.value = { search: '', date_from: '', date_to: '', category_id: null, status: null, payment_method: null, employee_id: null, recorded_by: null };
    load();
}
function createExpense(): void { selected.value = null; formOpen.value = true; }
function editExpense(expense: ExpenseListItem): void { selected.value = expense; formOpen.value = true; }
function cancelExpense(expense: ExpenseListItem): void { selected.value = expense; cancelOpen.value = true; }
</script>

<template>
    <Head title="Gastos" />
    <AppLayout title="Gastos">
        <PageHeader title="Gastos" description="Registra y consulta gastos operativos.">
            <template #actions>
                <VBtn v-if="capabilities.manage_categories" variant="tonal" prepend-icon="mdi-shape-outline" href="/expenses/categories">Categorías</VBtn>
                <VBtn v-if="capabilities.create" color="primary" prepend-icon="mdi-plus" @click="createExpense">Registrar gasto</VBtn>
            </template>
        </PageHeader>

        <VExpansionPanels v-model="mobileFilters" multiple class="expense-mobile-filters mb-4"><VExpansionPanel elevation="0" rounded="lg"><VExpansionPanelTitle><VIcon icon="mdi-filter-variant" class="mr-2" />Filtros</VExpansionPanelTitle><VExpansionPanelText><div class="expense-filter-grid"><VTextField v-model="form.search" label="Número, descripción o proveedor" :error-messages="errors.search" /><VTextField v-model="form.date_from" type="date" label="Desde" :error-messages="errors.date_from" /><VTextField v-model="form.date_to" type="date" label="Hasta" :error-messages="errors.date_to" /><VSelect v-model="form.category_id" label="Categoría" :items="categoryOptions" item-title="name" item-value="id" /><VSelect v-model="form.status" label="Estado" :items="statusOptions" /><VSelect v-model="form.payment_method" label="Método de gasto" :items="methodOptions" /><VSelect v-model="form.employee_id" label="Empleado relacionado" :items="employeeOptions" item-title="name" item-value="id" /><VSelect v-model="form.recorded_by" label="Registrado por" :items="recorderOptions" item-title="name" item-value="id" /></div><div class="d-flex ga-2"><VBtn color="primary" :loading="loading" @click="load()">Aplicar</VBtn><VBtn variant="text" :disabled="loading" @click="resetFilters">Restablecer</VBtn></div></VExpansionPanelText></VExpansionPanel></VExpansionPanels>
        <VCard class="expense-desktop-filters surface-card mb-5" rounded="xl"><VCardText><div class="expense-filter-grid"><VTextField v-model="form.search" label="Número, descripción o proveedor" :error-messages="errors.search" /><VTextField v-model="form.date_from" type="date" label="Desde" :error-messages="errors.date_from" /><VTextField v-model="form.date_to" type="date" label="Hasta" :error-messages="errors.date_to" /><VSelect v-model="form.category_id" label="Categoría" :items="categoryOptions" item-title="name" item-value="id" /><VSelect v-model="form.status" label="Estado" :items="statusOptions" /><VSelect v-model="form.payment_method" label="Método de gasto" :items="methodOptions" /><VSelect v-model="form.employee_id" label="Empleado relacionado" :items="employeeOptions" item-title="name" item-value="id" /><VSelect v-model="form.recorded_by" label="Registrado por" :items="recorderOptions" item-title="name" item-value="id" /></div><div class="d-flex ga-2"><VBtn color="primary" prepend-icon="mdi-filter-check-outline" :loading="loading" @click="load()">Aplicar</VBtn><VBtn variant="text" prepend-icon="mdi-filter-off-outline" :disabled="loading" @click="resetFilters">Restablecer</VBtn></div></VCardText></VCard>

        <VCard class="surface-card expense-results" rounded="xl" :class="{ 'expense-loading': loading }"><VProgressLinear v-if="loading" indeterminate color="primary" />
            <VDataTable :headers="headers" :items="records" class="expense-desktop-table" hide-default-footer>
                <template #item.expense_number="{ item }"><strong>{{ item.expense_number }}</strong></template><template #item.category="{ item }">{{ item.category.name }}</template><template #item.description="{ item }"><div class="expense-description">{{ item.description }}</div></template><template #item.employee="{ item }">{{ item.employee?.name || 'No relacionado' }}</template><template #item.amount="{ item }"><strong>{{ money(item.amount) }}</strong></template><template #item.origin_label="{ item }"><VChip :color="item.origin === 'payroll_automatic' ? 'primary' : undefined" variant="tonal" size="small">{{ item.origin_label }}</VChip></template><template #item.status="{ item }"><VChip :color="item.status === 'canceled' ? 'error' : 'success'" variant="tonal" size="small">{{ item.status_label }}</VChip></template>
                <template #item.actions="{ item }"><div class="expense-actions"><VBtn size="small" variant="text" prepend-icon="mdi-eye-outline" :href="item.show_url">Detalle</VBtn><VBtn v-if="item.can_edit" size="small" variant="text" prepend-icon="mdi-pencil-outline" @click="editExpense(item)">Editar</VBtn><VBtn v-if="item.can_cancel" size="small" variant="text" color="error" prepend-icon="mdi-cancel" @click="cancelExpense(item)">Anular</VBtn></div></template>
                <template #no-data><EmptyState icon="mdi-cash-minus" title="No se encontraron gastos" description="Registra un gasto o ajusta los filtros." /></template>
            </VDataTable>
            <section class="expense-mobile-cards" aria-label="Gastos"><template v-if="loading"><VSkeletonLoader v-for="n in 3" :key="n" type="article, actions" class="mb-3" /></template><EmptyState v-else-if="!records.length" icon="mdi-cash-minus" title="No se encontraron gastos" description="Registra un gasto o ajusta los filtros." /><VCard v-for="expense in records" v-else :key="expense.id" variant="outlined" rounded="lg" class="expense-mobile-card"><VCardText><div class="expense-card-heading"><div><strong>{{ expense.expense_number }}</strong><div class="text-caption text-medium-emphasis">{{ expense.expense_date_display }} · {{ expense.category.name }}</div></div><VChip :color="expense.status === 'canceled' ? 'error' : 'success'" variant="tonal" size="small">{{ expense.status_label }}</VChip></div><VChip v-if="expense.origin === 'payroll_automatic'" color="primary" variant="tonal" size="small" class="mt-3">Nómina automática</VChip><p class="text-body-2 my-3">{{ expense.description }}</p><div class="expense-card-grid"><div><span>Monto</span><strong>{{ money(expense.amount) }}</strong></div><div><span>Método</span><strong>{{ expense.payment_method_label }}</strong></div></div><VBtn block variant="tonal" prepend-icon="mdi-eye-outline" class="mt-4" :href="expense.show_url">Ver detalle</VBtn></VCardText></VCard></section>
            <VPagination v-if="expenses.meta.last_page > 1" :model-value="expenses.meta.current_page" :length="expenses.meta.last_page" class="my-4" :disabled="loading" @update:model-value="load" />
        </VCard>
        <ExpenseFormDialog v-model="formOpen" :expense="selected" :categories="activeCategories" :employees="activeEmployees" />
        <CancelExpenseDialog v-model="cancelOpen" :expense="selected" />
    </AppLayout>
</template>

<style scoped>
.expense-mobile-filters, .expense-mobile-cards { display: none; }.expense-filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }.expense-results { min-width: 0; overflow: hidden; }.expense-loading { opacity: .7; pointer-events: none; }.expense-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; }.expense-description { max-width: 280px; white-space: normal; }.expense-card-heading { display: flex; justify-content: space-between; gap: 12px; }.expense-card-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }.expense-card-grid div { display: flex; flex-direction: column; }.expense-card-grid span { color: rgba(var(--v-theme-on-surface), .62); font-size: .72rem; }
@media (max-width: 1100px) { .expense-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 700px) { .expense-desktop-filters, .expense-desktop-table { display: none; }.expense-mobile-filters, .expense-mobile-cards { display: block; }.expense-mobile-cards { padding: 14px; }.expense-mobile-card + .expense-mobile-card { margin-top: 12px; }.expense-filter-grid { grid-template-columns: 1fr; } }
</style>

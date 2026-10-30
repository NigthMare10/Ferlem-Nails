<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import type { ExpenseCategory } from '../../types/expenses';

defineProps<{ categories: ExpenseCategory[] }>();
const { xs } = useDisplay();
const dialog = ref(false);
const statusDialog = ref(false);
const selected = ref<ExpenseCategory | null>(null);
const statusLoading = ref(false);
const form = useForm({ name: '' });
const headers = [{ title: 'Categoría', key: 'name' }, { title: 'Gastos registrados', key: 'expenses_count', align: 'end' as const }, { title: 'Estado', key: 'is_active' }, { title: 'Acciones', key: 'actions', sortable: false, align: 'end' as const }];

function openCreate(): void { selected.value = null; form.defaults({ name: '' }); form.reset(); form.clearErrors(); dialog.value = true; }
function openEdit(category: ExpenseCategory): void { selected.value = category; form.defaults({ name: category.name }); form.reset(); form.clearErrors(); dialog.value = true; }
function close(): void { if (!form.processing) dialog.value = false; }
function submit(): void {
    if (form.processing) return;
    const options = { preserveScroll: true, onSuccess: close };
    if (selected.value) form.put(`/expenses/categories/${selected.value.id}`, options);
    else form.post('/expenses/categories', options);
}
function openStatus(category: ExpenseCategory): void { selected.value = category; statusDialog.value = true; }
function updateStatus(): void {
    if (!selected.value || statusLoading.value) return;
    statusLoading.value = true;
    router.patch(`/expenses/categories/${selected.value.id}/status`, { is_active: !selected.value.is_active }, { preserveScroll: true, onSuccess: () => { statusDialog.value = false; }, onFinish: () => { statusLoading.value = false; } });
}
</script>

<template>
    <Head title="Categorías de gastos" />
    <AppLayout title="Categorías de gastos">
        <PageHeader title="Categorías de gastos" description="Organiza los gastos sin eliminar su historial."><template #actions><VBtn variant="text" prepend-icon="mdi-arrow-left" href="/expenses">Volver a Gastos</VBtn><VBtn color="primary" prepend-icon="mdi-plus" @click="openCreate">Crear categoría</VBtn></template></PageHeader>
        <VAlert type="info" variant="tonal" class="mb-5">Las categorías utilizadas permanecen en el historial. Si dejan de usarse, desactívalas.</VAlert>
        <VCard class="surface-card"><VDataTable :headers="headers" :items="categories" class="desktop-table" :items-per-page="-1" hide-default-footer><template #item.name="{ item }"><strong>{{ item.name }}</strong></template><template #item.is_active="{ item }"><VChip :color="item.is_active ? 'success' : 'default'" variant="tonal" size="small">{{ item.is_active ? 'Activa' : 'Inactiva' }}</VChip></template><template #item.actions="{ item }"><div class="d-flex justify-end"><VBtn size="small" variant="text" prepend-icon="mdi-pencil-outline" @click="openEdit(item)">Editar</VBtn><VBtn size="small" variant="text" prepend-icon="mdi-power" @click="openStatus(item)">{{ item.is_active ? 'Desactivar' : 'Activar' }}</VBtn></div></template><template #no-data><EmptyState icon="mdi-shape-outline" title="No hay categorías" description="Crea la primera categoría para registrar gastos." /></template></VDataTable><div class="mobile-cards pa-4"><EmptyState v-if="!categories.length" icon="mdi-shape-outline" title="No hay categorías" description="Crea la primera categoría." /><VCard v-for="category in categories" v-else :key="category.id" variant="outlined" class="mb-3"><VCardItem><VCardTitle class="text-body-1">{{ category.name }}</VCardTitle><VCardSubtitle>{{ category.expenses_count }} gastos registrados</VCardSubtitle><template #append><VChip :color="category.is_active ? 'success' : 'default'" variant="tonal" size="small">{{ category.is_active ? 'Activa' : 'Inactiva' }}</VChip></template></VCardItem><VCardActions><VBtn variant="text" @click="openEdit(category)">Editar</VBtn><VBtn variant="text" @click="openStatus(category)">{{ category.is_active ? 'Desactivar' : 'Activar' }}</VBtn></VCardActions></VCard></div></VCard>
        <VDialog v-model="dialog" :fullscreen="xs" max-width="520" :persistent="form.processing"><VCard rounded="xl"><VCardTitle class="pa-5">{{ selected ? 'Editar categoría' : 'Crear categoría' }}</VCardTitle><VForm @submit.prevent="submit"><VCardText><VTextField v-model="form.name" label="Nombre" counter="100" :error-messages="form.errors.name" :disabled="form.processing" /></VCardText><VCardActions class="pa-4 flex-wrap"><VSpacer /><VBtn :block="xs" :disabled="form.processing" @click="close">Cancelar</VBtn><VBtn :block="xs" type="submit" color="primary" :loading="form.processing">Guardar</VBtn></VCardActions></VForm></VCard></VDialog>
        <ConfirmDialog v-model="statusDialog" title="Cambiar estado de la categoría" :message="`¿Deseas ${selected?.is_active ? 'desactivar' : 'activar'} ${selected?.name}?`" :confirm-text="selected?.is_active ? 'Desactivar' : 'Activar'" :color="selected?.is_active ? 'warning' : 'success'" :loading="statusLoading" @confirm="updateStatus" />
    </AppLayout>
</template>

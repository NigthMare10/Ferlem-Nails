<script setup lang="ts">
import type { AppointmentAssignee } from '../../types/appointments';

interface HistoryFilterModel {
    date_from: string;
    date_to: string;
    status: string | null;
    employee_id: number | null;
    client: string;
    service: string;
}

defineProps<{
    assignees: AppointmentAssignee[];
    canViewAll: boolean;
    errors: Record<string, string>;
    loading: boolean;
}>();
defineEmits<{ apply: []; clear: [] }>();
const model = defineModel<HistoryFilterModel>({ required: true });
const statuses = [
    { title: 'Todos los estados', value: null },
    { title: 'Programada', value: 'scheduled' },
    { title: 'Completada', value: 'completed' },
    { title: 'Cancelada', value: 'canceled' },
    { title: 'No llegó', value: 'no_show' },
];
</script>

<template>
    <div class="history-filter-fields">
        <VTextField v-model="model.date_from" type="date" label="Desde" :error-messages="errors.date_from" :disabled="loading" hide-details="auto" />
        <VTextField v-model="model.date_to" type="date" label="Hasta" :error-messages="errors.date_to" :disabled="loading" hide-details="auto" />
        <VSelect v-model="model.status" label="Estado" :items="statuses" :error-messages="errors.status" :disabled="loading" hide-details="auto" />
        <VSelect v-if="canViewAll" v-model="model.employee_id" label="Personal" :items="[{ id: null, name: 'Todo el personal' }, ...assignees]" item-title="name" item-value="id" :error-messages="errors.employee_id" :disabled="loading" hide-details="auto" />
        <VTextField v-model="model.client" label="Clienta" maxlength="120" prepend-inner-icon="mdi-account-search-outline" :error-messages="errors.client" :disabled="loading" hide-details="auto" />
        <VTextField v-model="model.service" label="Servicio reservado" maxlength="120" prepend-inner-icon="mdi-magnify" :error-messages="errors.service" :disabled="loading" hide-details="auto" />
        <div class="history-filter-actions"><VBtn variant="text" :disabled="loading" @click="$emit('clear')">Limpiar</VBtn><VBtn color="primary" prepend-icon="mdi-filter-check-outline" :loading="loading" @click="$emit('apply')">Aplicar filtros</VBtn></div>
    </div>
</template>

<style scoped>
.history-filter-fields { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.history-filter-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; grid-column: 1 / -1; }
@media (max-width: 700px) {
    .history-filter-fields { grid-template-columns: 1fr; }
    .history-filter-actions { align-items: stretch; flex-direction: column-reverse; }
    .history-filter-actions :deep(.v-btn) { width: 100%; }
}
</style>

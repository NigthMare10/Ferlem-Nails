<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import AmPmTimeField from '../AmPmTimeField.vue';
const props = defineProps<{ appointmentId: number | null }>();
const emit = defineEmits<{ close: [] }>();
const { xs } = useDisplay();
const loading = ref(false),
    error = ref('');
const services = ref<any[]>([]),
    assignees = ref<any[]>([]);
const form = useForm({
    client_name: '',
    client_phone: '',
    notes: '',
    date: '',
    start_time: '',
    expected_updated_at: '',
    expected_revision: '',
    items: [] as any[],
});
let sequence = 0;
watch(
    () => props.appointmentId,
    async (id) => {
        const current = ++sequence;
        form.clearErrors();
        error.value = '';
        if (!id) return;
        loading.value = true;
        try {
            const response = await fetch(`/appointments/${id}/edit`, { headers: { Accept: 'application/json' } });
            if (!response.ok)
                throw new Error('No se puede editar esta cita. Puede haber cambiado o no tienes permiso.');
            const data = await response.json();
            if (current !== sequence) return;
            services.value = data.services;
            assignees.value = data.assignees;
            form.defaults({ ...data.appointment, expected_revision: data.revision });
            form.reset();
        } catch (e) {
            if (current === sequence) error.value = String(e);
        } finally {
            if (current === sequence) loading.value = false;
        }
    },
    { immediate: true },
);
function add(id: number | null) {
    const s = services.value.find((s) => s.id === id);
    if (s)
        form.items.push({
            id: null,
            service_id: s.id,
            service_name: s.name,
            unit_price: s.price,
            quantity: 1,
            duration_minutes: s.duration_minutes,
            assigned_to: assignees.value[0]?.id,
        });
}
</script>
<template>
    <VDialog
        :model-value="!!appointmentId"
        :fullscreen="xs"
        max-width="760"
        :persistent="form.processing"
        @update:model-value="!form.processing && emit('close')"
    >
        <VCard rounded="xl"
            ><VCardTitle class="pa-5 d-flex justify-space-between align-center"
                ><h2 class="text-h6">Editar cita y servicios</h2>
                <VBtn
                    icon="mdi-close"
                    variant="text"
                    aria-label="Cerrar edición"
                    :disabled="form.processing"
                    @click="emit('close')"
            /></VCardTitle>
            <VProgressLinear v-if="loading" indeterminate />
            <VCardText
                ><VAlert v-if="error" type="error">{{ error }}</VAlert>
                <VForm
                    v-if="!loading && !error"
                    id="edit-appointment-services"
                    @submit.prevent="
                        form.put(`/appointments/${appointmentId}`, {
                            preserveScroll: true,
                            onSuccess: () => emit('close'),
                        })
                    "
                >
                    <p class="mb-5">
                        Los servicios conservados mantienen su precio. Se validan horario, comidas y disponibilidad al
                        guardar. El adelanto no se elimina.
                    </p>
                    <VAlert v-if="form.hasErrors" type="error" class="mb-4">{{
                        Object.values(form.errors).join(' ')
                    }}</VAlert>
                    <VTextField v-model="form.client_name" label="Clienta" /><VTextField
                        v-model="form.client_phone"
                        label="Teléfono"
                    />
                    <VTextField v-model="form.date" type="date" label="Fecha" /><AmPmTimeField
                        v-model="form.start_time"
                        label="Hora de inicio"
                    />
                    <VSelect
                        :model-value="null"
                        :items="services"
                        item-title="name"
                        item-value="id"
                        label="Agregar servicio"
                        @update:model-value="add"
                    />
                    <VCard
                        v-for="(item, index) in form.items"
                        :key="item.id ?? `new-${index}`"
                        variant="outlined"
                        class="pa-4 mb-4"
                        ><h3>{{ item.service_name }}</h3>
                        <p class="mb-3">L {{ item.unit_price }} por unidad</p>
                        <VRow
                            ><VCol cols="12" sm="6"
                                ><VTextField
                                    v-model.number="item.quantity"
                                    label="Cantidad"
                                    type="number"
                                    min="1"
                                    max="20" /></VCol
                            ><VCol cols="12" sm="6"
                                ><VTextField
                                    v-model.number="item.duration_minutes"
                                    label="Duración por unidad (minutos)"
                                    type="number"
                                    min="5"
                                    max="480"
                                    step="5" /></VCol></VRow
                        ><VSelect
                            v-model="item.assigned_to"
                            :items="assignees"
                            item-title="name"
                            item-value="id"
                            label="Persona asignada"
                        /><VBtn variant="text" color="error" @click="form.items.splice(index, 1)"
                            >Quitar servicio</VBtn
                        ></VCard
                    >
                    <VTextarea v-model="form.notes" label="Notas" /> </VForm
            ></VCardText>
            <VCardActions class="pa-5"
                ><VBtn :disabled="form.processing" @click="emit('close')">Cancelar</VBtn><VSpacer /><VBtn
                    form="edit-appointment-services"
                    type="submit"
                    color="primary"
                    variant="flat"
                    :loading="form.processing"
                    :disabled="loading || !!error || !form.items.length"
                    >Guardar cambios</VBtn
                ></VCardActions
            >
        </VCard></VDialog
    >
</template>

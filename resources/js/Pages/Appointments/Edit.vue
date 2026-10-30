<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import AmPmTimeField from '../../Components/AmPmTimeField.vue';
const props = defineProps<{ appointment: any; services: any[]; assignees: any[]; revision: string }>();
const form = useForm({
    ...props.appointment,
    expected_revision: props.revision,
    items: props.appointment.items.map((i: any) => ({ ...i })),
});
function add(id: number) {
    const s = props.services.find((s) => s.id === id);
    if (s)
        form.items.push({
            id: null,
            service_id: s.id,
            service_name: s.name,
            unit_price: s.price,
            quantity: 1,
            duration_minutes: s.duration_minutes,
            assigned_to: props.assignees[0]?.id,
        });
}
</script>
<template>
    <Head title="Editar cita" /><AppLayout title="Editar cita"
        ><h1>Editar cita y servicios</h1>
        <p class="mb-5">
            Los servicios conservados mantienen su precio. Los nuevos usan el precio actual. El servidor validará
            disponibilidad y conservará el adelanto.
        </p>
        <VForm @submit.prevent="form.put(`/appointments/${appointment.id}`)"
            ><VAlert v-if="form.hasErrors" type="error" class="mb-4">{{ Object.values(form.errors).join(' ') }}</VAlert
            ><VTextField v-model="form.client_name" label="Clienta" /><VTextField
                v-model="form.client_phone"
                label="Teléfono"
            /><VTextField v-model="form.date" type="date" label="Fecha" /><AmPmTimeField
                v-model="form.start_time"
                label="Hora de inicio"
            /><VSelect
                :items="services"
                item-title="name"
                item-value="id"
                label="Agregar servicio"
                @update:model-value="add"
            /><VCard v-for="(item, index) in form.items" :key="item.id ?? `new-${index}`" class="pa-4 mb-4"
                ><h2>{{ item.service_name }}</h2>
                <p>L {{ item.unit_price }} por unidad</p>
                <VTextField v-model.number="item.quantity" type="number" min="1" max="20" label="Cantidad" /><VTextField
                    v-model.number="item.duration_minutes"
                    type="number"
                    min="5"
                    step="5"
                    max="480"
                    label="Duración por unidad (minutos)"
                /><VSelect
                    v-model="item.assigned_to"
                    :items="assignees"
                    item-title="name"
                    item-value="id"
                    label="Persona asignada"
                /><VBtn variant="text" color="error" @click="form.items.splice(index, 1)">Quitar servicio</VBtn></VCard
            ><VTextarea v-model="form.notes" label="Notas" /><VBtn
                type="submit"
                :loading="form.processing"
                :disabled="!form.items.length"
                >Guardar cambios</VBtn
            ></VForm
        ></AppLayout
    >
</template>

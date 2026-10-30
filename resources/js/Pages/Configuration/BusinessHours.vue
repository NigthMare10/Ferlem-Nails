<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ConfigurationLayout from '../../Layouts/ConfigurationLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';

type BusinessHour = {
    weekday: number;
    is_open: boolean;
    opens_at: string | null;
    closes_at: string | null;
};

const props = defineProps<{ hours: BusinessHour[] }>();
const days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
const saved = ref(false);
const form = useForm({
    hours: props.hours.map(hour => ({
        ...hour,
        opens_at: hour.opens_at ?? '08:00',
        closes_at: hour.closes_at ?? '18:00',
    })),
});

function save(): void {
    saved.value = false;
    form.put('/configuration/business-hours', {
        preserveScroll: true,
        onSuccess: () => { saved.value = true; },
    });
}
</script>

<template>
    <Head title="Horario de atención" />
    <ConfigurationLayout>
        <PageHeader title="Horario de atención" description="Define los días y horas disponibles para nuevas citas.">
            <template #actions>
                <VBtn color="primary" prepend-icon="mdi-content-save-outline" :loading="form.processing" @click="save">Guardar horario</VBtn>
            </template>
        </PageHeader>

        <VAlert v-if="saved" type="success" variant="tonal" density="compact" class="mb-5" closable @click:close="saved = false">
            Horario de atención actualizado correctamente.
        </VAlert>

        <VCard class="surface-card">
            <VCardText class="pa-4 pa-sm-6">
                <p class="text-body-2 text-medium-emphasis mb-5">Los cambios aplican a las nuevas citas y reprogramaciones. Las citas existentes se conservan sin cambios.</p>
                <div class="hours-list">
                    <div v-for="(hour, index) in form.hours" :key="hour.weekday" class="hour-row">
                        <div class="day-name">{{ days[index] }}</div>
                        <VSwitch v-model="hour.is_open" color="primary" :label="hour.is_open ? 'Abierto' : 'Cerrado'" hide-details density="compact" class="open-switch" />
                        <div class="time-fields">
                            <VTextField v-model="hour.opens_at" type="time" label="Apertura" :disabled="!hour.is_open || form.processing" :error-messages="form.errors[`hours.${index}.opens_at`]" />
                            <VTextField v-model="hour.closes_at" type="time" label="Cierre" :disabled="!hour.is_open || form.processing" :error-messages="form.errors[`hours.${index}.closes_at`]" />
                        </div>
                    </div>
                </div>
                <VAlert v-if="form.errors.hours" type="error" variant="tonal" density="compact" class="mt-4">{{ form.errors.hours }}</VAlert>
            </VCardText>
        </VCard>
    </ConfigurationLayout>
</template>

<style scoped>
.hours-list { display: grid; gap: 1px; border: 1px solid var(--sl-border); border-radius: var(--sl-radius-surface); overflow: hidden; }
.hour-row { display: grid; grid-template-columns: minmax(110px, 1fr) 130px minmax(260px, 1.7fr); align-items: center; gap: 20px; padding: 16px; background: rgb(var(--v-theme-surface)); }
.hour-row + .hour-row { border-top: 1px solid var(--sl-border); }
.day-name { font-weight: 700; }
.open-switch { align-self: center; }
.time-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
@media (max-width: 700px) { .hour-row { grid-template-columns: 1fr auto; gap: 12px; } .time-fields { grid-column: 1 / -1; } }
</style>

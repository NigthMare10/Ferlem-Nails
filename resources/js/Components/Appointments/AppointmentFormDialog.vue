<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import type { AppointmentAssignee, AppointmentService } from '../../types/appointments';

const props = defineProps<{ modelValue: boolean; date: string; assignees: AppointmentAssignee[]; services: AppointmentService[]; canAssign: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
const { xs } = useDisplay();
const selectedServiceIds = ref<number[]>([]);
const samePerson = ref(true);
const commonAssignee = ref<number | null>(null);
const availabilityLoading = ref(false);
const availabilityLoaded = ref(false);
const availableTimes = ref<string[]>([]);
const availabilityMessage = ref('Selecciona los servicios para consultar horarios disponibles.');
let availabilityTimer: ReturnType<typeof setTimeout> | undefined;
const form = useForm({ client_name: '', client_phone: '', date: props.date, start_time: '', items: [] as Array<{ service_id: number; assigned_to: number | null; quantity: number; duration_minutes: number }>, notes: '' });
const selectedItems = computed(() => form.items.map((item, index) => ({ item, index, service: props.services.find(service => service.id === item.service_id) })).filter(entry => entry.service));
const duration = computed(() => form.items.reduce((sum, item) => sum + item.duration_minutes * item.quantity, 0));
const total = computed(() => selectedItems.value.reduce((sum, entry) => sum + Number(entry.service!.price) * entry.item.quantity, 0));
const canSave = computed(() => availableTimes.value.includes(form.start_time) && !availabilityLoading.value && !form.processing);

function segment(index: number, item: { duration_minutes: number; quantity: number }): string {
    const [hours, minutes] = form.start_time.split(':').map(Number);
    if (!Number.isInteger(hours) || !Number.isInteger(minutes)) return '—';
    const start = hours * 60 + minutes + form.items.slice(0, index).reduce((sum, line) => sum + line.duration_minutes * line.quantity, 0);
    const end = start + item.duration_minutes * item.quantity;
    return `${String(Math.floor(start / 60) % 24).padStart(2, '0')}:${String(start % 60).padStart(2, '0')} – ${String(Math.floor(end / 60) % 24).padStart(2, '0')}:${String(end % 60).padStart(2, '0')}`;
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function loadAvailability(): Promise<void> {
    if (!form.date || !form.items.length || form.items.some(item => !item.assigned_to)) {
        availabilityLoaded.value = false;
        availableTimes.value = [];
        form.start_time = '';
        availabilityMessage.value = 'Selecciona los servicios para consultar horarios disponibles.';
        return;
    }
    availabilityLoading.value = true;
    try {
        const response = await fetch('/appointments/availability', {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ date: form.date, items: form.items }),
        });
        if (!response.ok) throw new Error();
        const payload = await response.json() as { available_times: string[]; has_availability: boolean };
        availableTimes.value = payload.available_times;
        availabilityLoaded.value = true;
        if (!availableTimes.value.includes(form.start_time)) form.start_time = '';
        availabilityMessage.value = payload.has_availability ? '' : 'No hay horarios disponibles para esta configuración.';
    } catch {
        availableTimes.value = [];
        form.start_time = '';
        availabilityLoaded.value = true;
        availabilityMessage.value = 'No se pudieron consultar los horarios disponibles.';
    } finally {
        availabilityLoading.value = false;
    }
}

function scheduleAvailability(): void {
    if (availabilityTimer) clearTimeout(availabilityTimer);
    availabilityTimer = setTimeout(() => { void loadAvailability(); }, 250);
}

watch(() => props.modelValue, open => {
    if (!open) return;
    selectedServiceIds.value = [];
    samePerson.value = true;
    commonAssignee.value = props.assignees[0]?.id ?? null;
    availabilityLoaded.value = false;
    availableTimes.value = [];
    availabilityMessage.value = 'Selecciona los servicios para consultar horarios disponibles.';
    form.defaults({ client_name: '', client_phone: '', date: props.date, start_time: '', items: [], notes: '' }).reset().clearErrors();
});
watch(selectedServiceIds, ids => {
    const previous = new Map(form.items.map(item => [item.service_id, item]));
    form.items = ids.map(id => previous.get(id) ?? { service_id: id, assigned_to: commonAssignee.value, quantity: 1, duration_minutes: props.services.find(service => service.id === id)?.duration_minutes ?? 5 });
});
watch(commonAssignee, id => { if (samePerson.value || !props.canAssign) form.items.forEach(item => { item.assigned_to = id; }); });
watch(samePerson, active => { if (active) form.items.forEach(item => { item.assigned_to = commonAssignee.value; }); });
watch(() => [form.date, form.items.map(item => `${item.service_id}:${item.assigned_to}:${item.quantity}:${item.duration_minutes}`).join('|')], scheduleAvailability);
function close(): void { if (!form.processing) emit('update:modelValue', false); }
function save(): void { if (canSave.value) form.post('/appointments', { preserveScroll: true, onSuccess: () => emit('update:modelValue', false) }); }
function money(value: number | string): string { return new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value)); }
</script>

<template>
    <VDialog :model-value="modelValue" :fullscreen="xs" :persistent="form.processing" max-width="780" scrollable @update:model-value="value => value ? undefined : close()">
        <VCard><VToolbar color="surface" flat><VToolbarTitle class="font-weight-bold">Nueva cita</VToolbarTitle><VBtn icon="mdi-close" aria-label="Cerrar" :disabled="form.processing" @click="close" /></VToolbar><VDivider />
            <VCardText class="pa-4 pa-sm-7"><VForm @submit.prevent="save"><div class="form-section-title">Información de la clienta</div><VRow><VCol cols="12" sm="7"><VTextField v-model="form.client_name" label="Nombre de la clienta" :error-messages="form.errors.client_name" /></VCol><VCol cols="12" sm="5"><VTextField v-model="form.client_phone" label="Teléfono (opcional)" :error-messages="form.errors.client_phone" /></VCol></VRow><VTextField v-model="form.date" type="date" label="Fecha" :error-messages="form.errors.date" />
                <div class="form-section-title mt-2">Servicios</div><VAutocomplete v-model="selectedServiceIds" label="Selecciona uno o varios servicios" :items="services" item-title="name" item-value="id" multiple chips closable-chips :error-messages="form.errors.items" />
                <VAlert v-if="selectedItems.length && !canAssign" type="info" variant="tonal" density="compact" class="mb-3">Tú realizarás los servicios de esta cita</VAlert>
                <template v-if="canAssign"><VSwitch v-if="selectedItems.length" v-model="samePerson" label="Una misma persona realizará todos los servicios" color="primary" hide-details class="mb-3" /><VSelect v-if="samePerson && selectedItems.length" v-model="commonAssignee" label="Persona asignada" :items="assignees" item-title="name" item-value="id" /></template>
                <VCard v-for="entry in selectedItems" :key="entry.item.service_id" variant="outlined" class="pa-4 mb-3" rounded="lg"><div class="font-weight-bold">{{ entry.service!.name }} · {{ money(entry.service!.price) }}</div><div class="text-caption text-medium-emphasis mb-3">Tiempo habitual: {{ entry.service!.duration_minutes }} min</div><VRow><VCol cols="6"><VTextField v-model.number="entry.item.duration_minutes" type="number" min="5" max="480" step="5" label="Duración reservada" suffix="min" /></VCol><VCol cols="6"><VTextField v-model.number="entry.item.quantity" type="number" min="1" max="20" label="Cantidad" /></VCol></VRow><VSelect v-if="canAssign && !samePerson" v-model="entry.item.assigned_to" label="Persona asignada" :items="assignees" item-title="name" item-value="id" /><div class="text-caption text-primary">Horario: {{ segment(entry.index, entry.item) }}</div></VCard>
                <div class="form-section-title mt-5">Horario disponible</div><VProgressLinear v-if="availabilityLoading" indeterminate color="primary" class="mb-3" /><VSelect v-model="form.start_time" label="Hora" :items="availableTimes" :loading="availabilityLoading" :disabled="!availableTimes.length" :error-messages="form.errors.start_time" /><VAlert v-if="availabilityMessage" :type="availabilityLoaded ? 'warning' : 'info'" variant="tonal" density="compact" class="mb-4">{{ availabilityMessage }}</VAlert>
                <VTextarea v-model="form.notes" label="Notas (opcional)" rows="3" counter="1000" :error-messages="form.errors.notes" /><div class="summary-grid mt-4"><div><span>Duración</span><strong>{{ duration }} min</strong></div><div><span>Total estimado</span><strong>{{ money(total) }}</strong></div></div></VForm></VCardText>
            <VDivider /><VCardActions class="pa-4 dialog-actions"><VBtn variant="text" :disabled="form.processing" @click="close">Cancelar</VBtn><VSpacer /><VBtn color="primary" :loading="form.processing" :disabled="!canSave" @click="save">Guardar cita</VBtn></VCardActions>
        </VCard>
    </VDialog>
</template>

<style scoped>.form-section-title { font-size: .78rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: rgb(var(--v-theme-primary)); margin-bottom: 12px; } .summary-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; } .summary-grid div { display: flex; flex-direction: column; } .summary-grid span { font-size: .75rem; color: rgba(var(--v-theme-on-surface), .65); } @media (max-width: 599px) { .summary-grid { grid-template-columns: 1fr; } .dialog-actions { align-items: stretch; flex-direction: column-reverse; } .dialog-actions :deep(.v-btn) { width: 100%; } }</style>

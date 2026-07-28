<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import type { AppointmentAssignee, AppointmentDetails } from '../../types/appointments';

type DialogMode = 'detail' | 'edit' | 'reschedule' | 'cancel' | 'no_show' | 'deposit';
type InitialDialogMode = Exclude<DialogMode, 'edit' | 'deposit'>;

const props = defineProps<{
    modelValue: boolean;
    appointment: AppointmentDetails | null;
    initialMode: InitialDialogMode;
    loading: boolean;
    error: string | null;
    assignees: AppointmentAssignee[];
    canUpdate: boolean;
    canAssign: boolean;
    canViewAll: boolean;
    canCancel: boolean;
    canMarkNoShow: boolean;
    canManageDeposit: boolean;
    canResolveDeposit: boolean;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; retry: []; saved: [mode: DialogMode]; closed: [] }>();
const { xs } = useDisplay();
const mode = ref<DialogMode>('detail');
const editForm = useForm({ client_name: '', client_phone: '', notes: '' });
const rescheduleForm = useForm({
    date: '',
    start_time: '',
    assignments: [] as Array<{ appointment_item_id: number; assigned_to: number | null }>,
    reschedule_note: '',
});
const depositForm = useForm({ amount: '', payment_method: 'cash', note: '' });
const cancelForm = useForm({ reason: '', deposit_resolution: '', refund_amount: '', resolution_notes: '', operation_token: '' });
const noShowForm = useForm({ reason: '', deposit_resolution: '', refund_amount: '', resolution_notes: '', operation_token: '' });
const editErrors = computed(() => editForm.errors as Record<string, string | undefined>);
const rescheduleErrors = computed(() => rescheduleForm.errors as Record<string, string | undefined>);
const depositErrors = computed(() => depositForm.errors as Record<string, string | undefined>);
const cancelErrors = computed(() => cancelForm.errors as Record<string, string | undefined>);
const noShowErrors = computed(() => noShowForm.errors as Record<string, string | undefined>);
const availabilityLoading = ref(false);
const availableTimes = ref<string[]>([]);
const availabilityMessage = ref('');
let availabilityTimer: ReturnType<typeof setTimeout> | undefined;
const formProcessing = computed(() => editForm.processing
    || rescheduleForm.processing
    || depositForm.processing
    || cancelForm.processing
    || noShowForm.processing);
const dialogBusy = computed(() => formProcessing.value || availabilityLoading.value);
const detailCanEdit = computed(() => props.appointment?.status === 'scheduled'
    && props.appointment.can_reschedule
    && props.canUpdate);
const detailCanRecordDeposit = computed(() => props.appointment?.can_record_deposit && props.canManageDeposit);
const showFooter = computed(() => Boolean(props.appointment)
    && !props.loading
    && !props.error
    && (mode.value !== 'detail' || detailCanEdit.value || detailCanRecordDeposit.value));
const title = computed(() => ({
    detail: 'Detalle de cita',
    edit: 'Editar información',
    reschedule: 'Reprogramar cita',
    cancel: 'Cancelar cita',
    no_show: 'Marcar No llegó',
    deposit: 'Registrar adelanto',
})[mode.value]);
const estimatedEnd = computed(() => {
    if (!props.appointment) return '—';
    const [hours, minutes] = rescheduleForm.start_time.split(':').map(Number);
    if (!Number.isInteger(hours) || !Number.isInteger(minutes)) return '—';
    const total = hours * 60 + minutes + props.appointment.visible_duration_minutes;
    return `${String(Math.floor(total / 60) % 24).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}${total >= 1440 ? ' (día siguiente)' : ''}`;
});

watch([() => props.modelValue, () => props.appointment, () => props.initialMode], ([open, appointment, initialMode]) => {
    if (!open) return;
    if (!appointment) {
        mode.value = initialMode;
        return;
    }
    mode.value = resolveInitialMode(initialMode, appointment);
    editForm.defaults({
        client_name: appointment.client_name,
        client_phone: appointment.client_phone ?? '',
        notes: appointment.notes ?? '',
    }).reset().clearErrors();
    rescheduleForm.defaults({
        date: appointment.date,
        start_time: appointment.visible_start_time,
        assignments: props.canAssign
            ? appointment.visible_items.map(item => ({ appointment_item_id: item.id, assigned_to: item.assigned_to.id }))
            : [],
        reschedule_note: '',
    }).reset().clearErrors();
    depositForm.defaults({ amount: '', payment_method: 'cash', note: '' }).reset().clearErrors();
    cancelForm.defaults({ reason: '', deposit_resolution: '', refund_amount: '', resolution_notes: '', operation_token: '' }).reset().clearErrors();
    noShowForm.defaults({ reason: '', deposit_resolution: '', refund_amount: '', resolution_notes: '', operation_token: '' }).reset().clearErrors();
});

function resolveInitialMode(initialMode: InitialDialogMode, appointment: AppointmentDetails): InitialDialogMode {
    if (initialMode === 'reschedule' && props.canUpdate && appointment.status === 'scheduled' && appointment.can_reschedule) return initialMode;
    const canResolvePending = !appointment.has_pending_deposit || (props.canResolveDeposit && appointment.can_resolve_deposit);
    if (initialMode === 'cancel' && props.canCancel && appointment.status === 'scheduled' && appointment.can_cancel && canResolvePending) return initialMode;
    if (initialMode === 'no_show' && props.canMarkNoShow && appointment.status === 'scheduled' && appointment.can_change_status && appointment.can_mark_no_show_now && canResolvePending) return initialMode;
    return 'detail';
}

function closeDialog(): void {
    if (!dialogBusy.value) emit('update:modelValue', false);
}

function resetDialog(): void {
    if (availabilityTimer) clearTimeout(availabilityTimer);
    availabilityTimer = undefined;
    mode.value = 'detail';
    availabilityLoading.value = false;
    availableTimes.value = [];
    availabilityMessage.value = '';
    editForm.reset().clearErrors();
    rescheduleForm.reset().clearErrors();
    depositForm.reset().clearErrors();
    cancelForm.reset().clearErrors();
    noShowForm.reset().clearErrors();
    emit('closed');
}

function openMode(nextMode: Exclude<DialogMode, 'detail'>): void {
    mode.value = nextMode;
    if (nextMode === 'reschedule') scheduleAvailability();
}

function back(): void {
    if (!formProcessing.value) mode.value = 'detail';
}

function cancelCurrentMode(): void {
    if (mode.value === 'edit' || props.initialMode === 'detail') back();
    else closeDialog();
}

function save(): void {
    if (!props.appointment || formProcessing.value) return;
    const submittedMode = mode.value;
    if (submittedMode === 'cancel' || submittedMode === 'no_show') {
        const terminalForm = submittedMode === 'cancel' ? cancelForm : noShowForm;
        if (['full_refund', 'partial_refund'].includes(terminalForm.deposit_resolution) && !terminalForm.operation_token) {
            terminalForm.operation_token = window.crypto.randomUUID();
        }
    }
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', submittedMode);
            if (submittedMode === 'cancel' || submittedMode === 'no_show') {
                emit('update:modelValue', false);
                return;
            }
            mode.value = 'detail';
        },
    };
    if (mode.value === 'edit') editForm.put(`/appointments/${props.appointment.id}`, options);
    if (mode.value === 'reschedule') rescheduleForm.post(`/appointments/${props.appointment.id}/reschedule`, options);
    if (mode.value === 'deposit') depositForm.post(`/appointments/${props.appointment.id}/deposit`, options);
    if (mode.value === 'cancel') cancelForm.post(`/appointments/${props.appointment.id}/cancel`, options);
    if (mode.value === 'no_show') noShowForm.post(`/appointments/${props.appointment.id}/no-show`, options);
}

function money(value: string): string {
    return new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));
}

function statusColor(status: AppointmentDetails['status']): string {
    return ({ scheduled: 'primary', completed: 'success', canceled: 'error', no_show: 'warning' })[status];
}

function dateLabel(date: string): string {
    return new Intl.DateTimeFormat('es-HN', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC',
    }).format(new Date(`${date}T12:00:00Z`));
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function loadAvailability(): Promise<void> {
    if (!props.appointment || mode.value !== 'reschedule') return;
    if (!rescheduleForm.date) {
        availableTimes.value = [];
        rescheduleForm.start_time = '';
        availabilityMessage.value = 'Selecciona una fecha.';
        return;
    }
    availabilityLoading.value = true;
    availabilityMessage.value = '';
    try {
        const response = await fetch('/appointments/availability', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                appointment_id: props.appointment.id,
                date: rescheduleForm.date,
                assignments: rescheduleForm.assignments,
            }),
        });
        const payload = await response.json() as {
            available_times?: string[];
            has_availability?: boolean;
            errors?: Record<string, string[]>;
        };
        if (!response.ok) {
            if (response.status === 422) {
                availabilityMessage.value = payload.errors?.date?.[0]
                    ?? payload.errors?.appointment?.[0]
                    ?? payload.errors?.assignments?.[0]
                    ?? 'No se pudo consultar la disponibilidad con los datos de esta cita.';
            } else if (response.status === 403) {
                availabilityMessage.value = 'No tienes permiso para consultar la disponibilidad de esta cita.';
            } else if (response.status === 404) {
                availabilityMessage.value = 'La cita ya no está disponible.';
            } else {
                console.error('Error inesperado al consultar disponibilidad', { status: response.status });
                availabilityMessage.value = 'No se pudieron consultar los horarios disponibles. Inténtalo de nuevo.';
            }
            availableTimes.value = [];
            return;
        }
        availableTimes.value = payload.available_times ?? [];
        if (!availableTimes.value.includes(rescheduleForm.start_time)) rescheduleForm.start_time = '';
        availabilityMessage.value = payload.has_availability ? '' : 'No hay horarios disponibles.';
    } catch (error) {
        console.error('Error de red al consultar disponibilidad', error);
        availableTimes.value = [];
        availabilityMessage.value = 'No se pudieron consultar los horarios disponibles. Inténtalo de nuevo.';
    } finally {
        availabilityLoading.value = false;
    }
}

function scheduleAvailability(): void {
    if (availabilityTimer) clearTimeout(availabilityTimer);
    availabilityTimer = setTimeout(() => { void loadAvailability(); }, 250);
}

watch(
    () => [mode.value, rescheduleForm.date, rescheduleForm.assignments.map(item => `${item.appointment_item_id}:${item.assigned_to}`).join('|')],
    scheduleAvailability,
);
</script>

<template>
    <VDialog
        :model-value="modelValue"
        :fullscreen="xs"
        :persistent="dialogBusy || mode !== 'detail'"
        :scrollable="false"
        max-width="780"
        @after-leave="resetDialog"
        @update:model-value="value => value ? undefined : closeDialog()"
    >
        <VCard v-if="modelValue" class="appointment-dialog-card">
            <VToolbar color="surface" flat class="dialog-header">
                <VToolbarTitle class="font-weight-bold">{{ title }}</VToolbarTitle>
                <VBtn icon="mdi-close" aria-label="Cerrar detalle de cita" :disabled="dialogBusy" @click="closeDialog" />
            </VToolbar>
            <VDivider />

            <VCardText v-if="loading" class="pa-5 pa-sm-6 dialog-content compact-loading" aria-label="Cargando detalle de la cita">
                <VSkeletonLoader type="heading" width="55%" />
                <VSkeletonLoader type="text" width="35%" class="mt-2" />
                <VSkeletonLoader type="list-item-two-line" class="mt-4" />
            </VCardText>
            <VCardText v-else-if="error" class="pa-4 pa-sm-5 dialog-content compact-error">
                <VAlert type="error" variant="tonal" density="compact" icon="mdi-alert-circle-outline">
                    {{ error }}
                    <template #append><VBtn variant="text" @click="emit('retry')">Reintentar</VBtn></template>
                </VAlert>
            </VCardText>
            <VCardText v-else-if="appointment" class="pa-4 pa-sm-7 dialog-content">
                <template v-if="mode === 'detail'">
                    <div class="detail-heading">
                        <div>
                            <div class="text-h5 font-weight-bold">{{ appointment.client_name }}</div>
                            <div v-if="appointment.client_phone" class="text-body-2 text-medium-emphasis mt-1">
                                <VIcon icon="mdi-phone-outline" size="17" class="mr-1" />{{ appointment.client_phone }}
                            </div>
                        </div>
                        <VChip :color="statusColor(appointment.status)" variant="tonal">{{ appointment.status_label }}</VChip>
                    </div>

                    <div class="detail-grid mt-6">
                        <VCard variant="outlined" rounded="lg" class="detail-tile"><VIcon icon="mdi-calendar-outline" color="primary" /><div><span>Fecha</span><strong class="text-capitalize">{{ dateLabel(appointment.date) }}</strong></div></VCard>
                        <VCard variant="outlined" rounded="lg" class="detail-tile"><VIcon icon="mdi-clock-outline" color="primary" /><div><span>Horario{{ appointment.is_shared && !canViewAll ? ' de tus servicios' : '' }}</span><strong>{{ appointment.visible_start_time }} – {{ appointment.visible_end_time }}</strong></div></VCard>
                        <VCard v-if="canViewAll" variant="outlined" rounded="lg" class="detail-tile"><VIcon icon="mdi-account-star-outline" color="primary" /><div><span>Personal participante</span><strong>{{ [...new Set(appointment.visible_items.map(item => item.assigned_to.name))].join(', ') }}</strong></div></VCard>
                        <VCard variant="outlined" rounded="lg" class="detail-tile"><VIcon icon="mdi-timer-sand" color="primary" /><div><span>Duración{{ appointment.is_shared && !canViewAll ? ' propia' : ' total' }}</span><strong>{{ appointment.visible_duration_minutes }} min</strong></div></VCard>
                    </div>
                    <VChip v-if="appointment.is_shared" color="secondary" variant="tonal" class="mt-4">Cita compartida</VChip>
                    <VAlert v-if="appointment.outside_business_hours" type="warning" variant="tonal" density="compact" class="mt-4">
                        Esta cita está fuera del horario de atención configurado actualmente.
                    </VAlert>

                    <section class="mt-7">
                        <div class="section-title">Servicios reservados</div>
                        <VCard variant="outlined" rounded="lg" class="overflow-hidden">
                            <div v-for="item in appointment.visible_items" :key="item.id" class="reserved-service pa-4">
                                <div><div class="font-weight-bold">{{ item.start_time }}–{{ item.end_time }} · {{ item.service_name }}<template v-if="canViewAll"> · {{ item.assigned_to.name }}</template></div><div class="text-caption text-medium-emphasis">{{ item.duration_minutes === item.default_duration_minutes ? `${item.duration_minutes} min habituales` : `Duración ajustada: ${item.duration_minutes} min · habitual ${item.default_duration_minutes} min` }} · {{ money(item.unit_price) }} cada uno</div></div>
                                <div class="text-right"><div class="text-caption">Cantidad {{ item.quantity }}</div><strong>{{ money(item.line_total) }}</strong></div>
                            </div>
                            <div class="total-row pa-4"><span>{{ appointment.is_shared && !canViewAll ? 'Subtotal propio' : 'Total estimado' }}</span><strong>{{ money(appointment.visible_total) }}</strong></div>
                        </VCard>
                    </section>
                    <section v-if="appointment.deposit" class="mt-7">
                        <div class="section-title">Adelanto</div>
                        <VCard variant="outlined" rounded="lg" class="pa-4">
                            <div class="deposit-grid"><div><span>Recibido originalmente</span><strong>{{ money(appointment.deposit.amount) }}</strong></div><div v-if="appointment.deposit.available_amount !== undefined"><span>Disponible</span><strong>{{ money(appointment.deposit.available_amount) }}</strong></div><div><span>Método</span><strong>{{ appointment.deposit.payment_method_label }}</strong></div><div><span>Estado</span><strong>{{ appointment.deposit.status_label }}</strong></div><div><span>Saldo estimado</span><strong>{{ money(appointment.deposit.estimated_balance) }}</strong></div><div v-if="appointment.deposit.refunded_amount !== undefined"><span>Devuelto</span><strong>{{ money(appointment.deposit.refunded_amount) }}</strong></div><div v-if="appointment.deposit.retained_amount !== undefined"><span>Retenido</span><strong>{{ money(appointment.deposit.retained_amount) }}</strong></div></div>
                            <div v-if="appointment.deposit.card_fee_amount !== undefined" class="internal-finance mt-4"><div><span>Comisión POS {{ appointment.deposit.card_fee_rate }}%</span><strong>{{ money(appointment.deposit.card_fee_amount) }}</strong></div><div><span>Neto recibido</span><strong>{{ money(appointment.deposit.net_amount!) }}</strong></div></div>
                            <div class="text-caption text-medium-emphasis mt-3">Registrado {{ appointment.deposit.paid_at_display }}</div>
                            <VAlert v-if="appointment.deposit.resolution_notes" type="info" variant="tonal" density="compact" class="mt-4"><strong>Nota de resolución:</strong> {{ appointment.deposit.resolution_notes }}</VAlert>
                        </VCard>
                    </section>
                    <section v-if="appointment.notes" class="mt-7"><div class="section-title">Notas</div><VCard color="surface-variant" rounded="lg" class="pa-4 text-body-2">{{ appointment.notes }}</VCard></section>
                    <section v-if="appointment.status_reason" class="mt-7">
                        <div class="section-title">Cambio de estado</div>
                        <VAlert :type="appointment.status === 'canceled' ? 'error' : 'warning'" variant="tonal">
                            <div><strong>Motivo:</strong> {{ appointment.status_reason }}</div>
                            <div class="text-caption mt-2"><template v-if="appointment.status_changed_by">{{ appointment.status_changed_by.name }} · </template>{{ appointment.status_changed_at_display }}</div>
                        </VAlert>
                    </section>
                    <section v-if="appointment.status === 'completed' && appointment.completed_at_display" class="mt-7">
                        <div class="section-title">Cita completada</div>
                        <VAlert type="success" variant="tonal"><strong>Completada:</strong> {{ appointment.completed_at_display }}</VAlert>
                    </section>
                    <section v-if="appointment.linked_sale" class="mt-7">
                        <div class="section-title">Venta vinculada</div>
                        <VCard variant="outlined" rounded="lg" class="pa-4 d-flex align-center justify-space-between ga-3">
                            <div><strong>{{ appointment.linked_sale.sale_number }}</strong><div class="text-caption text-medium-emphasis">Total {{ money(appointment.linked_sale.total) }}</div></div>
                            <VBtn v-if="appointment.linked_sale.can_view_receipt" :href="appointment.linked_sale.receipt_url" variant="tonal" color="primary" prepend-icon="mdi-receipt-text-outline">Ver comprobante</VBtn>
                        </VCard>
                    </section>
                    <section class="mt-7"><div class="section-title">Registro</div><div v-if="appointment.created_by" class="text-body-2">Creada por <strong>{{ appointment.created_by.name }}</strong></div><div class="text-caption text-medium-emphasis mt-1">{{ appointment.created_at_display }}</div></section>
                    <section class="mt-7">
                        <div class="section-title">Historial de cambios</div>
                        <VTimeline side="end" density="compact" class="history-timeline">
                            <VTimelineItem v-for="event in appointment.events" :key="event.id" :dot-color="event.type === 'completed' ? 'success' : event.type === 'canceled' ? 'error' : event.type === 'no_show' || event.type === 'rescheduled' ? 'warning' : event.type === 'deposit_resolved' ? 'secondary' : 'primary'" size="x-small">
                                <div class="font-weight-bold text-body-2">{{ event.type_label }}</div>
                                <div v-if="event.changes.length" class="history-changes mt-1"><div v-for="change in event.changes" :key="change.label"><strong>{{ change.label }}:</strong> {{ change.previous ? `${change.previous} → ` : '' }}{{ change.new }}</div></div>
                                <div v-else class="text-caption text-medium-emphasis">Se actualizó la cita</div>
                                <div v-if="event.notes" class="text-caption mt-2"><strong>Motivo:</strong> {{ event.notes }}</div>
                                <div class="text-caption text-medium-emphasis mt-2"><template v-if="event.performed_by">{{ event.performed_by.name }} · </template>{{ event.occurred_at_display }}</div>
                            </VTimelineItem>
                        </VTimeline>
                    </section>
                </template>

                <template v-else-if="mode === 'edit'">
                    <VAlert v-if="editErrors.appointment" type="error" variant="tonal" class="mb-5">{{ editErrors.appointment }}</VAlert>
                    <div class="form-section-title">Información de la clienta</div>
                    <VRow><VCol cols="12" sm="7"><VTextField v-model="editForm.client_name" label="Nombre de la clienta" :error-messages="editForm.errors.client_name" :disabled="editForm.processing" /></VCol><VCol cols="12" sm="5"><VTextField v-model="editForm.client_phone" label="Teléfono (opcional)" :error-messages="editForm.errors.client_phone" :disabled="editForm.processing" /></VCol></VRow>
                    <VTextarea v-model="editForm.notes" label="Notas (opcional)" rows="3" counter="1000" :error-messages="editForm.errors.notes" :disabled="editForm.processing" />
                </template>

                <template v-else-if="mode === 'reschedule'">
                    <VAlert v-if="rescheduleErrors.appointment" type="error" variant="tonal" class="mb-5">{{ rescheduleErrors.appointment }}</VAlert>
                    <VAlert type="info" variant="tonal" density="compact" class="mb-5">Los servicios, cantidades, duración y total reservado no cambian al reprogramar.</VAlert>
                    <div class="form-section-title">Nuevo horario</div>
                    <VRow><VCol cols="12" sm="7"><VTextField v-model="rescheduleForm.date" type="date" label="Fecha" :error-messages="rescheduleForm.errors.date" :disabled="rescheduleForm.processing" /></VCol><VCol cols="12" sm="5"><VSelect v-model="rescheduleForm.start_time" label="Hora disponible" :items="availableTimes" :loading="availabilityLoading" :disabled="!availableTimes.length || rescheduleForm.processing" :error-messages="rescheduleForm.errors.start_time" /></VCol></VRow>
                    <VAlert v-if="availabilityMessage" type="warning" variant="tonal" density="compact" class="mb-3">{{ availabilityMessage }}</VAlert>
                    <VCard variant="outlined" rounded="lg" class="pa-4 mt-2">
                        <div class="text-overline text-primary mb-2">Servicios reservados</div>
                        <div v-for="(item, index) in appointment.visible_items" :key="item.id" class="mb-3"><div class="text-body-2">{{ item.service_name }} · {{ item.duration_minutes }} min · {{ item.start_time }}–{{ item.end_time }}</div><VSelect v-if="canAssign" v-model="rescheduleForm.assignments[index].assigned_to" label="Persona asignada" density="compact" class="mt-2" :items="assignees" item-title="name" item-value="id" :disabled="rescheduleForm.processing" /><div v-else class="text-caption text-medium-emphasis">{{ item.assigned_to.name }}</div></div>
                        <div class="summary-grid mt-4"><div><span>Duración total</span><strong>{{ appointment.visible_duration_minutes }} min</strong></div><div><span>Nueva finalización</span><strong>{{ estimatedEnd }}</strong></div><div><span>Total estimado</span><strong>{{ money(appointment.visible_total) }}</strong></div></div>
                    </VCard>
                    <VTextarea v-model="rescheduleForm.reschedule_note" label="Motivo o nota de reprogramación (opcional)" rows="3" counter="500" :error-messages="rescheduleForm.errors.reschedule_note" :disabled="rescheduleForm.processing" class="mt-5" />
                </template>

                <template v-else-if="mode === 'deposit'">
                    <VAlert v-if="depositErrors.appointment || depositErrors.deposit" type="error" variant="tonal" class="mb-5">{{ depositErrors.appointment || depositErrors.deposit }}</VAlert>
                    <VAlert type="info" variant="tonal" density="compact" class="mb-5">El adelanto se registra separado de la venta y reduce únicamente el saldo estimado.</VAlert>
                    <VRow><VCol cols="12" sm="6"><VTextField v-model="depositForm.amount" type="number" min="0.01" step="0.01" :max="appointment.visible_total" label="Monto recibido" prefix="L" :error-messages="depositForm.errors.amount" :disabled="depositForm.processing" /></VCol><VCol cols="12" sm="6"><VSelect v-model="depositForm.payment_method" label="Método de pago" :items="[{ title: 'Efectivo', value: 'cash' }, { title: 'Tarjeta', value: 'card' }]" :error-messages="depositForm.errors.payment_method" :disabled="depositForm.processing" /></VCol></VRow>
                    <VTextarea v-model="depositForm.note" label="Nota (opcional)" rows="3" counter="500" :error-messages="depositForm.errors.note" :disabled="depositForm.processing" />
                    <VAlert v-if="depositForm.payment_method === 'card'" type="warning" variant="tonal" density="compact">El backend calculará y conservará la comisión POS exacta del 4%.</VAlert>
                </template>

                <template v-else-if="mode === 'cancel'">
                    <VAlert v-if="cancelErrors.appointment" type="error" variant="tonal" class="mb-5">{{ cancelErrors.appointment }}</VAlert>
                    <div class="terminal-summary"><strong>{{ appointment.client_name }}</strong><span class="text-capitalize">{{ dateLabel(appointment.date) }} · {{ appointment.visible_start_time }}</span><span>{{ appointment.visible_items.map(item => item.service_name).join(', ') }}</span></div>
                    <VAlert type="warning" variant="tonal" class="my-5">La cita dejará de ocupar estos horarios.</VAlert>
                    <VTextarea v-model="cancelForm.reason" label="Motivo" rows="4" counter="500" :error-messages="cancelForm.errors.reason" :disabled="cancelForm.processing" autofocus />
                    <VCard v-if="appointment.has_pending_deposit" variant="outlined" rounded="lg" class="pa-4 mt-5"><div class="form-section-title">Resolver saldo disponible de {{ money(appointment.deposit!.available_amount ?? '0.00') }}</div><VAlert v-if="cancelForm.errors.deposit_resolution" type="error" variant="tonal" density="compact" class="mb-3">{{ cancelForm.errors.deposit_resolution }}</VAlert><VSelect v-model="cancelForm.deposit_resolution" label="Resolución obligatoria" :items="[{ title: 'Devolución completa', value: 'full_refund' }, { title: 'Retención completa', value: 'full_retention' }, { title: 'Devolución parcial', value: 'partial_refund' }]" :disabled="cancelForm.processing" /><VTextField v-if="cancelForm.deposit_resolution === 'partial_refund'" v-model="cancelForm.refund_amount" type="number" min="0.01" step="0.01" :max="appointment.deposit!.available_amount" label="Monto a devolver" prefix="L" :error-messages="cancelForm.errors.refund_amount" :disabled="cancelForm.processing" /><VTextarea v-model="cancelForm.resolution_notes" label="Nota de resolución (opcional)" rows="2" counter="500" :error-messages="cancelForm.errors.resolution_notes" :disabled="cancelForm.processing" /></VCard>
                </template>

                <template v-else-if="mode === 'no_show'">
                    <VAlert v-if="noShowErrors.appointment" type="error" variant="tonal" class="mb-5">{{ noShowErrors.appointment }}</VAlert>
                    <div class="terminal-summary"><strong>{{ appointment.client_name }}</strong><span class="text-capitalize">{{ dateLabel(appointment.date) }} · {{ appointment.visible_start_time }}</span></div>
                    <VAlert type="warning" variant="tonal" class="my-5">La cita se marcará como no presentada.</VAlert>
                    <VTextarea v-model="noShowForm.reason" label="Motivo" rows="4" counter="500" :error-messages="noShowForm.errors.reason" :disabled="noShowForm.processing" autofocus />
                    <VCard v-if="appointment.has_pending_deposit" variant="outlined" rounded="lg" class="pa-4 mt-5"><div class="form-section-title">Resolver saldo disponible de {{ money(appointment.deposit!.available_amount ?? '0.00') }}</div><VAlert v-if="noShowForm.errors.deposit_resolution" type="error" variant="tonal" density="compact" class="mb-3">{{ noShowForm.errors.deposit_resolution }}</VAlert><VSelect v-model="noShowForm.deposit_resolution" label="Resolución obligatoria" :items="[{ title: 'Devolución completa', value: 'full_refund' }, { title: 'Retención completa', value: 'full_retention' }, { title: 'Devolución parcial', value: 'partial_refund' }]" :disabled="noShowForm.processing" /><VTextField v-if="noShowForm.deposit_resolution === 'partial_refund'" v-model="noShowForm.refund_amount" type="number" min="0.01" step="0.01" :max="appointment.deposit!.available_amount" label="Monto a devolver" prefix="L" :error-messages="noShowForm.errors.refund_amount" :disabled="noShowForm.processing" /><VTextarea v-model="noShowForm.resolution_notes" label="Nota de resolución (opcional)" rows="2" counter="500" :error-messages="noShowForm.errors.resolution_notes" :disabled="noShowForm.processing" /></VCard>
                </template>
            </VCardText>

            <template v-if="showFooter">
                <VDivider />
                <VCardActions class="pa-4 dialog-actions">
                    <template v-if="mode === 'detail'">
                        <VSpacer />
                        <VBtn v-if="detailCanRecordDeposit" variant="tonal" prepend-icon="mdi-cash-plus" @click="openMode('deposit')">Registrar adelanto</VBtn>
                        <VBtn v-if="detailCanEdit" variant="tonal" prepend-icon="mdi-pencil-outline" @click="openMode('edit')">Editar información</VBtn>
                    </template>
                    <template v-else>
                        <VBtn variant="text" :disabled="dialogBusy" @click="cancelCurrentMode">{{ mode === 'edit' || initialMode === 'detail' ? 'Volver' : 'Cerrar' }}</VBtn>
                        <VSpacer />
                        <VBtn v-if="mode === 'edit'" color="primary" prepend-icon="mdi-content-save-outline" :loading="editForm.processing" @click="save">Guardar cambios</VBtn>
                        <VBtn v-else-if="mode === 'reschedule'" color="primary" prepend-icon="mdi-content-save-outline" :loading="rescheduleForm.processing" :disabled="availabilityLoading || !availableTimes.includes(rescheduleForm.start_time)" @click="save">Confirmar reprogramación</VBtn>
                        <VBtn v-else-if="mode === 'deposit'" color="primary" prepend-icon="mdi-cash-plus" :loading="depositForm.processing" @click="save">Registrar adelanto</VBtn>
                        <VBtn v-else-if="mode === 'cancel'" color="error" prepend-icon="mdi-calendar-remove-outline" :loading="cancelForm.processing" @click="save">Confirmar cancelación</VBtn>
                        <VBtn v-else-if="mode === 'no_show'" color="warning" prepend-icon="mdi-account-off-outline" :loading="noShowForm.processing" @click="save">Marcar No llegó</VBtn>
                    </template>
                </VCardActions>
            </template>
        </VCard>
    </VDialog>
</template>

<style scoped>
.appointment-dialog-card { display: flex; max-height: 85vh; flex-direction: column; overflow: hidden; }
.dialog-header, .dialog-actions { flex: 0 0 auto; }
.dialog-content { min-height: 0; flex: 0 1 auto !important; overflow-y: auto; overscroll-behavior: contain; }
.compact-loading { min-height: 150px; }
.compact-error { min-height: 0; }
.detail-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.detail-tile { display: flex; align-items: center; gap: 13px; padding: 15px; }
.detail-tile div, .summary-grid div, .terminal-summary { display: flex; min-width: 0; flex-direction: column; }
.detail-tile span, .summary-grid span { font-size: .72rem; color: rgba(var(--v-theme-on-surface), .62); }
.detail-tile strong { font-size: .9rem; }
.section-title, .form-section-title { margin-bottom: 10px; color: rgb(var(--v-theme-primary)); font-size: .76rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.reserved-service { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.reserved-service + .reserved-service { border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.total-row { display: flex; justify-content: space-between; background: rgb(var(--v-theme-surface-variant)); }
.history-timeline { margin-left: -14px; }
.history-changes { display: grid; gap: 4px; font-size: .78rem; }
.summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.deposit-grid, .internal-finance { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.deposit-grid div, .internal-finance div { display: flex; flex-direction: column; }
.deposit-grid span, .internal-finance span { color: rgba(var(--v-theme-on-surface), .62); font-size: .72rem; }
.internal-finance { grid-template-columns: repeat(2, minmax(0, 1fr)); padding-top: 14px; border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.terminal-summary { gap: 5px; padding: 16px; background: rgb(var(--v-theme-surface-variant)); border-radius: 12px; }
@media (max-width: 599px) {
    .appointment-dialog-card { width: 100%; height: 100dvh; max-height: 100dvh; }
    .dialog-content { flex: 1 1 auto !important; }
    .detail-grid, .summary-grid, .deposit-grid, .internal-finance { grid-template-columns: 1fr; }
    .summary-grid div { flex-direction: row; justify-content: space-between; }
    .dialog-actions { align-items: stretch; flex-direction: column-reverse; }
    .dialog-actions :deep(.v-btn), .dialog-actions :deep(.v-spacer), .dialog-actions :deep(.v-alert) { width: 100%; }
    .dialog-actions :deep(.v-spacer) { display: none; }
}
</style>

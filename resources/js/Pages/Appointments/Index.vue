<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppointmentDetailsDialog from '../../Components/Appointments/AppointmentDetailsDialog.vue';
import AppointmentFormDialog from '../../Components/Appointments/AppointmentFormDialog.vue';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { usePermissions } from '../../composables/usePermissions';
import type { Appointment, AppointmentAssignee, AppointmentDetails, AppointmentService } from '../../types/appointments';

type AppointmentDialogMode = 'detail' | 'reschedule' | 'cancel' | 'no_show';

interface CalendarPreview {
    appointment_id: number;
    start_time: string;
    service_name: string;
    client_name: string;
    assigned_name: string | null;
    is_shared: boolean;
}

interface CalendarDay {
    date: string;
    appointments_count: number;
    services_count: number;
    has_appointments: boolean;
    previews: CalendarPreview[];
}

const props = defineProps<{
    date: string;
    month: string;
    view: 'month' | 'day';
    today: string;
    employee_id: number | null;
    calendar_days: CalendarDay[];
    timezone: string;
    appointments: Appointment[];
    assignees: AppointmentAssignee[];
    services: AppointmentService[];
}>();

const { can } = usePermissions();
const formOpen = ref(false);
const loading = ref(false);
const detailsOpen = ref(false);
const detailsLoading = ref(false);
const detailsError = ref<string | null>(null);
const selectedAppointmentId = ref<number | null>(null);
const selectedAppointment = ref<AppointmentDetails | null>(null);
const selectedDialogMode = ref<AppointmentDialogMode>('detail');
const pageDescription = computed(() => props.view === 'month'
    ? 'Selecciona un día para consultar las citas programadas.'
    : `Citas del ${dateLabel.value}`);
const dateLabel = computed(() => new Intl.DateTimeFormat('es-HN', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC',
}).format(new Date(`${props.date}T12:00:00Z`)));
const monthLabel = computed(() => new Intl.DateTimeFormat('es-HN', {
    month: 'long', year: 'numeric', timeZone: 'UTC',
}).format(new Date(`${props.month}-01T12:00:00Z`)));
const calendarByDate = computed(() => new Map(props.calendar_days.map(day => [day.date, day])));
const monthCells = computed(() => {
    const first = new Date(`${props.month}-01T12:00:00Z`);
    const offset = (first.getUTCDay() + 6) % 7;
    const start = new Date(first);
    start.setUTCDate(first.getUTCDate() - offset);

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start);
        date.setUTCDate(start.getUTCDate() + index);
        const value = date.toISOString().slice(0, 10);
        return {
            date: value,
            day: date.getUTCDate(),
            currentMonth: value.startsWith(props.month),
            summary: calendarByDate.value.get(value),
        };
    });
});

function navigate(parameters: Record<string, string | number>): void {
    if (loading.value) return;
    loading.value = true;
    router.get('/appointments', parameters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => { loading.value = false; },
    });
}

function employeeParameter(): Record<string, number> {
    return props.employee_id ? { employee_id: props.employee_id } : {};
}

function visitDate(date = props.date): void {
    navigate({ view: 'day', date, month: props.month, ...employeeParameter() });
}

function visitMonth(month = props.month): void {
    navigate({ view: 'month', month, date: props.date, ...employeeParameter() });
}

function shiftMonth(amount: number): void {
    const value = new Date(`${props.month}-01T12:00:00Z`);
    value.setUTCMonth(value.getUTCMonth() + amount);
    visitMonth(value.toISOString().slice(0, 7));
}

function shiftDate(days: number): void {
    const value = new Date(`${props.date}T12:00:00Z`);
    value.setUTCDate(value.getUTCDate() + days);
    visitDate(value.toISOString().slice(0, 10));
}

function goToday(): void {
    if (props.view === 'month') navigate({ view: 'month', month: props.today.slice(0, 7), date: props.today, ...employeeParameter() });
    else navigate({ view: 'day', month: props.month, date: props.today, ...employeeParameter() });
}

function changeEmployee(value: number | null): void {
    navigate({ view: props.view, month: props.month, date: props.date, ...(value ? { employee_id: value } : {}) });
}

function money(value: string): string {
    return new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));
}

function durationLabel(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;
    if (!hours) return `${remainder} min`;
    return remainder ? `${hours} h ${remainder} min` : `${hours} h`;
}

function statusColor(status: Appointment['status']): string {
    return ({ scheduled: 'primary', completed: 'success', canceled: 'error', no_show: 'warning' })[status];
}

async function loadDetails(): Promise<void> {
    if (!selectedAppointmentId.value || detailsLoading.value) return;
    detailsLoading.value = true;
    detailsError.value = null;
    try {
        const response = await fetch(`/appointments/${selectedAppointmentId.value}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(response.status === 403 ? 'No tienes permiso para consultar esta cita.' : 'No se pudo cargar el detalle de la cita.');
        selectedAppointment.value = (await response.json()).appointment as AppointmentDetails;
    } catch (error) {
        detailsError.value = error instanceof Error ? error.message : 'No se pudo cargar el detalle de la cita.';
    } finally {
        detailsLoading.value = false;
    }
}

function canReprogram(appointment: Appointment): boolean {
    return appointment.status === 'scheduled'
        && can('appointments.update')
        && appointment.can_reschedule;
}

function canCheckout(appointment: Appointment): boolean {
    return appointment.status === 'scheduled'
        && can('appointments.convert_to_sale')
        && can('sales.access')
        && can('sales.create')
        && appointment.can_checkout;
}

function checkout(appointment: Appointment): void {
    router.visit(`/sales/new?appointment=${appointment.id}`);
}

function canCancelAppointment(appointment: Appointment): boolean {
    return appointment.status === 'scheduled'
        && can('appointments.cancel')
        && appointment.can_change_status;
}

function canMarkNoShow(appointment: Appointment): boolean {
    return appointment.status === 'scheduled'
        && can('appointments.mark_no_show')
        && appointment.can_change_status
        && appointment.can_mark_no_show_now;
}

function hasMoreActions(appointment: Appointment): boolean {
    return canReprogram(appointment) || canCancelAppointment(appointment) || canMarkNoShow(appointment);
}

function openAppointment(appointment: Appointment, mode: AppointmentDialogMode = 'detail'): void {
    selectedAppointmentId.value = appointment.id;
    selectedAppointment.value = null;
    selectedDialogMode.value = mode;
    detailsOpen.value = true;
    void loadDetails();
}

function handleSaved(mode: string): void {
    if (mode === 'edit' || mode === 'reschedule' || mode === 'deposit') {
        selectedDialogMode.value = 'detail';
        void loadDetails();
    }
}

function clearDetails(): void {
    if (detailsOpen.value) return;
    selectedAppointmentId.value = null;
    selectedAppointment.value = null;
    selectedDialogMode.value = 'detail';
    detailsError.value = null;
    detailsLoading.value = false;
}
</script>

<template>
    <Head title="Agenda" />
    <AppLayout title="Agenda">
        <PageHeader title="Agenda" :description="pageDescription">
            <template #actions>
                <VBtn variant="tonal" prepend-icon="mdi-history" href="/appointments/history">
                    Historial
                </VBtn>
                <VBtn v-if="can('appointments.create')" color="primary" prepend-icon="mdi-calendar-plus" @click="formOpen = true">
                    Nueva cita
                </VBtn>
            </template>
        </PageHeader>

        <Transition name="agenda-view" mode="out-in">
            <section :key="view" class="agenda-view-shell">
                <VCard v-if="view === 'month'" class="calendar-card" :class="{ 'agenda-loading': loading }" rounded="xl" variant="flat">
                    <VProgressLinear v-if="loading" indeterminate color="primary" />
                    <VCardText class="pa-3 pa-sm-5">
                        <div class="month-toolbar">
                            <div class="month-navigation">
                                <VBtn icon="mdi-chevron-left" variant="text" aria-label="Mes anterior" :disabled="loading" @click="shiftMonth(-1)" />
                                <div class="month-title text-capitalize">{{ monthLabel }}</div>
                                <VBtn icon="mdi-chevron-right" variant="text" aria-label="Mes siguiente" :disabled="loading" @click="shiftMonth(1)" />
                            </div>
                            <div class="calendar-actions">
                                <VBtn variant="tonal" prepend-icon="mdi-calendar-today" :disabled="loading" @click="goToday">Hoy</VBtn>
                                <VSelect
                                    v-if="can('appointments.view_all')"
                                    :model-value="employee_id"
                                    label="Empleado"
                                    :items="[{ id: null, name: 'Todos los empleados' }, ...assignees]"
                                    item-title="name"
                                    item-value="id"
                                    hide-details
                                    density="compact"
                                    class="employee-filter"
                                    @update:model-value="changeEmployee"
                                />
                            </div>
                        </div>

                        <div class="month-grid month-weekdays" aria-hidden="true">
                            <span v-for="dayName in ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']" :key="dayName">{{ dayName }}</span>
                        </div>
                        <div class="month-grid calendar-grid" aria-label="Calendario mensual">
                            <button
                                v-for="cell in monthCells"
                                :key="cell.date"
                                type="button"
                                class="month-cell"
                                :class="{ muted: !cell.currentMonth, selected: cell.date === date, today: cell.date === today }"
                                :aria-label="`${cell.date}${cell.summary ? `, ${cell.summary.appointments_count} citas` : ', sin citas'}`"
                                :disabled="loading"
                                @click="visitDate(cell.date)"
                            >
                                <span class="day-number">{{ cell.day }}</span>
                                <span v-if="cell.summary" class="mobile-count">{{ cell.summary.appointments_count }}</span>
                                <span v-if="cell.summary" class="month-count">{{ cell.summary.appointments_count }} cita{{ cell.summary.appointments_count === 1 ? '' : 's' }} · {{ cell.summary.services_count }} serv.</span>
                                <div v-if="cell.summary" class="month-previews">
                                    <span v-for="preview in cell.summary.previews" :key="`${preview.appointment_id}-${preview.start_time}-${preview.service_name}`">
                                        {{ preview.start_time }} · {{ preview.service_name }}<template v-if="can('appointments.view_all')"> · {{ preview.assigned_name }}</template><template v-else-if="preview.is_shared"> · Compartida</template>
                                    </span>
                                    <span v-if="cell.summary.services_count > cell.summary.previews.length" class="more-preview">+{{ cell.summary.services_count - cell.summary.previews.length }} más</span>
                                </div>
                            </button>
                        </div>
                    </VCardText>
                </VCard>

                <div v-else class="day-view">
                    <VCard class="day-toolbar-card mb-6" rounded="xl" variant="flat" :class="{ 'agenda-loading': loading }">
                        <VProgressLinear v-if="loading" indeterminate color="primary" />
                        <VCardText class="pa-3 pa-sm-4">
                            <div class="day-toolbar">
                                <VBtn variant="text" prepend-icon="mdi-arrow-left" :disabled="loading" @click="visitMonth()">Volver al calendario</VBtn>
                                <div class="day-navigation">
                                    <VBtn icon="mdi-chevron-left" variant="text" aria-label="Día anterior" :disabled="loading" @click="shiftDate(-1)" />
                                    <div class="day-title text-capitalize">{{ dateLabel }}</div>
                                    <VBtn icon="mdi-chevron-right" variant="text" aria-label="Día siguiente" :disabled="loading" @click="shiftDate(1)" />
                                </div>
                                <div class="day-actions">
                                    <VBtn variant="tonal" prepend-icon="mdi-calendar-today" :disabled="loading" @click="goToday">Hoy</VBtn>
                                    <VSelect
                                        v-if="can('appointments.view_all')"
                                        :model-value="employee_id"
                                        label="Empleado"
                                        :items="[{ id: null, name: 'Todos los empleados' }, ...assignees]"
                                        item-title="name"
                                        item-value="id"
                                        hide-details
                                        density="compact"
                                        class="employee-filter"
                                        @update:model-value="changeEmployee"
                                    />
                                </div>
                            </div>
                        </VCardText>
                    </VCard>

                    <VCard v-if="appointments.length === 0" class="surface-card" :class="{ 'agenda-loading': loading }">
                        <EmptyState icon="mdi-calendar-blank-outline" title="No hay citas para este día" description="Las citas programadas aparecerán aquí.">
                            <VBtn v-if="can('appointments.create')" color="primary" prepend-icon="mdi-calendar-plus" @click="formOpen = true">Nueva cita</VBtn>
                        </EmptyState>
                    </VCard>

                    <section v-else class="appointment-list" :class="{ 'agenda-loading': loading }" aria-label="Citas del día">
                        <article v-for="appointment in appointments" :key="appointment.id" class="appointment-row">
                            <div class="time-column">
                                <div v-for="item in appointment.visible_items" :key="item.id" class="segment-time"><strong>{{ item.start_time }}</strong><span>{{ item.end_time }}</span></div>
                            </div>
                            <VCard class="surface-card appointment-card" :class="{ 'is-terminal': appointment.status !== 'scheduled' }" rounded="xl">
                                <VCardText class="pa-4">
                                    <div class="appointment-topline">
                                        <div>
                                            <div class="text-h6 font-weight-bold">{{ appointment.client_name }}</div>
                                            <div v-if="appointment.client_phone" class="text-body-2 text-medium-emphasis mt-1">
                                                <VIcon icon="mdi-phone-outline" size="16" class="mr-1" />{{ appointment.client_phone }}
                                            </div>
                                        </div>
                                        <VChip :color="statusColor(appointment.status)" variant="tonal" size="small">{{ appointment.status_label }}</VChip>
                                    </div>
                                    <div class="service-chips mt-3">
                                        <VChip v-for="item in appointment.visible_items.slice(0, 2)" :key="item.id" size="small" variant="outlined" color="primary">
                                            {{ item.start_time }}–{{ item.end_time }} · {{ item.service_name }}<template v-if="can('appointments.view_all')"> · {{ item.assigned_to.name }}</template>
                                        </VChip>
                                        <VChip v-if="appointment.visible_items.length > 2" size="small" variant="text">+{{ appointment.visible_items.length - 2 }} más</VChip>
                                        <VChip v-if="appointment.is_shared" size="small" color="secondary" variant="tonal">Cita compartida</VChip>
                                    </div>
                                    <div class="appointment-meta mt-3">
                                        <div><VIcon icon="mdi-clock-outline" /><span>{{ durationLabel(appointment.visible_duration_minutes) }}</span></div>
                                        <div><VIcon icon="mdi-cash" /><strong>{{ money(appointment.visible_total) }}</strong></div>
                                    </div>
                                    <p v-if="appointment.status_reason" class="status-reason mt-3 mb-0">
                                        <VIcon icon="mdi-text-box-outline" size="17" class="mr-1" /><strong>Motivo:</strong> {{ appointment.status_reason }}
                                    </p>
                                    <p v-else-if="appointment.notes" class="notes-preview mt-3 mb-0"><VIcon icon="mdi-note-text-outline" size="17" class="mr-1" />{{ appointment.notes }}</p>

                                    <div class="appointment-actions desktop-appointment-actions">
                                        <VBtn size="small" variant="text" color="primary" prepend-icon="mdi-eye-outline" @click="openAppointment(appointment)">Ver detalle</VBtn>
                                        <VBtn v-if="canCheckout(appointment)" size="small" variant="tonal" color="primary" prepend-icon="mdi-cash-register" @click="checkout(appointment)">Atender y cobrar</VBtn>
                                        <VBtn v-if="canReprogram(appointment)" size="small" variant="text" prepend-icon="mdi-calendar-sync-outline" @click="openAppointment(appointment, 'reschedule')">Reprogramar</VBtn>
                                        <VBtn v-if="canCancelAppointment(appointment)" size="small" variant="text" color="error" prepend-icon="mdi-calendar-remove-outline" @click="openAppointment(appointment, 'cancel')">Cancelar</VBtn>
                                        <VBtn v-if="canMarkNoShow(appointment)" size="small" variant="text" color="warning" prepend-icon="mdi-account-off-outline" @click="openAppointment(appointment, 'no_show')">No llegó</VBtn>
                                    </div>

                                    <div class="appointment-actions mobile-appointment-actions">
                                        <VBtn v-if="canCheckout(appointment)" size="small" variant="tonal" color="primary" prepend-icon="mdi-cash-register" @click="checkout(appointment)">Atender y cobrar</VBtn>
                                        <VBtn v-else size="small" variant="tonal" color="primary" prepend-icon="mdi-eye-outline" @click="openAppointment(appointment)">Ver detalle</VBtn>
                                        <VMenu v-if="hasMoreActions(appointment)" location="bottom end">
                                            <template #activator="{ props: menuProps }">
                                                <VBtn v-bind="menuProps" size="small" variant="text" append-icon="mdi-chevron-down">Más acciones</VBtn>
                                            </template>
                                            <VList density="compact" min-width="210">
                                                <VListItem v-if="canCheckout(appointment)" prepend-icon="mdi-eye-outline" title="Ver detalle" @click="openAppointment(appointment)" />
                                                <VListItem v-if="canReprogram(appointment)" prepend-icon="mdi-calendar-sync-outline" title="Reprogramar" @click="openAppointment(appointment, 'reschedule')" />
                                                <VListItem v-if="canCancelAppointment(appointment)" prepend-icon="mdi-calendar-remove-outline" title="Cancelar" base-color="error" @click="openAppointment(appointment, 'cancel')" />
                                                <VListItem v-if="canMarkNoShow(appointment)" prepend-icon="mdi-account-off-outline" title="No llegó" base-color="warning" @click="openAppointment(appointment, 'no_show')" />
                                            </VList>
                                        </VMenu>
                                    </div>
                                </VCardText>
                            </VCard>
                        </article>
                    </section>
                </div>
            </section>
        </Transition>

        <VBtn v-if="can('appointments.create')" class="mobile-create-button" color="primary" icon="mdi-calendar-plus" aria-label="Nueva cita" size="large" @click="formOpen = true" />
        <AppointmentFormDialog v-model="formOpen" :date="date" :assignees="assignees" :services="services" :can-assign="can('appointments.assign')" :can-manage-deposit="can('appointments.manage_deposit')" />
        <AppointmentDetailsDialog v-model="detailsOpen" :appointment="selectedAppointment" :initial-mode="selectedDialogMode" :loading="detailsLoading" :error="detailsError" :assignees="assignees" :can-update="can('appointments.update')" :can-assign="can('appointments.assign')" :can-view-all="can('appointments.view_all')" :can-cancel="can('appointments.cancel')" :can-mark-no-show="can('appointments.mark_no_show')" :can-manage-deposit="can('appointments.manage_deposit')" :can-resolve-deposit="can('appointments.resolve_deposit')" @retry="loadDetails" @saved="handleSaved" @closed="clearDetails" />
    </AppLayout>
</template>

<style scoped>
.appointment-card.is-terminal { opacity: .7; border-style: dashed; }
.appointment-actions { align-items: center; justify-content: flex-end; gap: 2px; margin-top: 14px; padding-top: 10px; border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.desktop-appointment-actions { display: flex; flex-wrap: wrap; }
.mobile-appointment-actions { display: none; }
.status-reason, .notes-preview { display: -webkit-box; overflow: hidden; color: rgba(var(--v-theme-on-surface), .68); font-size: .84rem; line-height: 1.45; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.agenda-view-shell { min-width: 0; }.calendar-card, .day-toolbar-card { background: rgba(var(--v-theme-surface), .9); border: 1px solid rgba(var(--v-theme-primary), .1); }.month-toolbar, .day-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; }.month-navigation, .day-navigation, .calendar-actions, .day-actions { display: flex; align-items: center; gap: 8px; }.month-title, .day-title { min-width: 190px; text-align: center; font-size: 1.1rem; font-weight: 750; }.employee-filter { width: 230px; }.month-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; }.month-weekdays { margin: 14px 0 7px; text-align: center; color: rgba(var(--v-theme-on-surface), .56); font-size: .72rem; font-weight: 800; letter-spacing: .04em; }.calendar-grid { grid-auto-rows: minmax(128px, 1fr); }.month-cell { position: relative; min-width: 0; padding: 10px; overflow: hidden; text-align: left; color: inherit; background: rgba(var(--v-theme-surface-variant), .38); border: 1px solid rgba(var(--v-border-color), .12); border-radius: 10px; cursor: pointer; transition: background-color 160ms ease, border-color 160ms ease, transform 160ms ease; }.month-cell:hover { background: rgba(var(--v-theme-primary), .06); border-color: rgba(var(--v-theme-primary), .22); transform: translateY(-1px); }.month-cell.selected { background: rgba(var(--v-theme-primary), .1); border-color: rgba(var(--v-theme-primary), .35); }.month-cell.today .day-number { display: inline-grid; width: 25px; height: 25px; place-items: center; color: rgb(var(--v-theme-on-primary)); background: rgb(var(--v-theme-primary)); border-radius: 50%; }.month-cell.muted { opacity: .42; }.day-number { font-weight: 800; }.month-count { display: block; margin: 7px 0 6px; color: rgba(var(--v-theme-on-surface), .62); font-size: .68rem; }.month-previews { display: grid; gap: 4px; font-size: .69rem; line-height: 1.25; }.month-previews span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.more-preview { color: rgb(var(--v-theme-primary)); font-weight: 750; }.mobile-count { display: none; }.appointment-list { max-width: 980px; margin: 0 auto; position: relative; }.appointment-list::before { content: ''; position: absolute; left: 52px; top: 12px; bottom: 12px; width: 1px; background: rgba(var(--v-theme-primary), .18); }.appointment-row { display: grid; grid-template-columns: 88px minmax(0, 1fr); gap: 18px; margin-bottom: 18px; position: relative; }.time-column { display: flex; flex-direction: column; align-items: flex-end; padding-top: 20px; line-height: 1.2; position: relative; }.time-column::after { content: ''; position: absolute; right: -24px; top: 26px; width: 11px; height: 11px; border-radius: 50%; background: rgb(var(--v-theme-primary)); box-shadow: 0 0 0 4px rgb(var(--v-theme-background)); }.time-column strong { font-size: 1rem; }.time-column span { margin-top: 3px; color: rgba(var(--v-theme-on-surface), .6); font-size: .76rem; }.segment-time { display: flex; flex-direction: column; align-items: flex-end; margin-bottom: 8px; }.appointment-card { border-left: 4px solid rgb(var(--v-theme-primary)); }.appointment-topline { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }.service-chips { display: flex; flex-wrap: wrap; gap: 8px; }.appointment-meta { display: flex; flex-wrap: wrap; gap: 12px 24px; color: rgba(var(--v-theme-on-surface), .72); }.appointment-meta div { display: flex; align-items: center; gap: 7px; font-size: .875rem; }.appointment-meta :deep(.v-icon) { color: rgb(var(--v-theme-primary)); font-size: 18px; }.notes-preview { overflow: hidden; color: rgba(var(--v-theme-on-surface), .68); font-size: .86rem; text-overflow: ellipsis; white-space: nowrap; }.agenda-loading { opacity: .58; pointer-events: none; }.mobile-create-button { display: none; }.agenda-view-enter-active, .agenda-view-leave-active { transition: opacity 220ms ease, transform 220ms ease; }.agenda-view-enter-from { opacity: 0; transform: scale(.985) translateY(6px); }.agenda-view-leave-to { opacity: 0; transform: scale(.985) translateY(-4px); }
@media (max-width: 960px) { .calendar-grid { grid-auto-rows: minmax(105px, 1fr); }.month-cell { padding: 7px; }.month-previews { font-size: .62rem; }.month-title, .day-title { min-width: 160px; }.day-toolbar { flex-wrap: wrap; }.day-actions { margin-left: auto; } }
@media (max-width: 700px) { .month-toolbar, .day-toolbar { align-items: stretch; flex-direction: column; }.month-navigation, .day-navigation { justify-content: space-between; }.calendar-actions, .day-actions { width: 100%; justify-content: space-between; margin: 0; }.employee-filter { flex: 1; width: auto; }.month-grid { gap: 3px; }.month-weekdays { margin-top: 12px; font-size: .58rem; }.calendar-grid { grid-auto-rows: 58px; }.month-cell { min-height: 58px; padding: 5px; border-radius: 7px; }.month-cell:hover { transform: none; }.day-number { font-size: .74rem; }.month-cell.today .day-number { width: 21px; height: 21px; }.month-count, .month-previews { display: none; }.mobile-count { position: absolute; right: 5px; bottom: 5px; display: inline-grid; min-width: 17px; height: 17px; padding: 0 4px; place-items: center; color: rgb(var(--v-theme-on-primary)); background: rgb(var(--v-theme-primary)); border-radius: 999px; font-size: .58rem; font-weight: 800; }.appointment-list::before { display: none; }.appointment-row { display: block; margin-bottom: 16px; }.time-column { flex-direction: row; align-items: baseline; justify-content: flex-start; gap: 7px; padding: 0 0 8px 4px; }.time-column::after { display: none; }.segment-time { align-items: flex-start; flex-direction: row; gap: 7px; margin: 0; }.appointment-card { border-left-width: 3px; }.appointment-meta { display: grid; grid-template-columns: 1fr; gap: 9px; }.mobile-create-button { position: fixed; right: 18px; bottom: 20px; z-index: 5; display: inline-flex; box-shadow: 0 8px 24px rgba(63, 31, 42, .28); } }
@media (max-width: 700px) { .desktop-appointment-actions { display: none; }.mobile-appointment-actions { display: flex; justify-content: space-between; }.mobile-appointment-actions :deep(.v-btn) { min-height: 40px; } }
@media (prefers-reduced-motion: reduce) { .agenda-view-enter-active, .agenda-view-leave-active, .month-cell { transition-duration: 1ms !important; }.agenda-view-enter-from, .agenda-view-leave-to { transform: none; } }
</style>

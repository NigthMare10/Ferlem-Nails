<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import PageHeader from '../Components/PageHeader.vue';
import { usePermissions } from '../composables/usePermissions';
import type { Appointment } from '../types/appointments';

const props = defineProps<{ metrics: { active_services?: number; active_users?: number }; today: string; todayAgenda: { appointments_count: number; services_count: number }; todayAppointments: Appointment[] }>();
const page = usePage();
const { can, canAny } = usePermissions();
const userName = computed(() => (page.props.auth as any)?.user?.name ?? '');
const nextAppointment = computed(() => props.todayAppointments[0] ?? null);
const todayLabel = computed(() => new Intl.DateTimeFormat('es-HN', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(`${props.today}T12:00:00Z`)));
</script>

<template>
    <Head title="Inicio" />
    <AppLayout title="Inicio">
        <PageHeader
            eyebrow="Panel principal"
            :title="`Hola, ${userName}`"
            description="Agenda, operación y movimientos esenciales para tu jornada."
        />

        <section class="today-panel" aria-labelledby="agenda-hoy">
            <div class="today-panel__intro">
                <div><div class="text-overline text-primary">{{ todayLabel }}</div><h2 id="agenda-hoy">Agenda de hoy</h2></div>
                <VBtn variant="tonal" color="primary" prepend-icon="mdi-calendar-today" @click="router.visit(`/appointments?view=day&date=${today}&month=${today.slice(0, 7)}`)">Ver agenda del día</VBtn>
            </div>
            <div class="today-panel__summary">
                <div><span>Citas pendientes</span><strong>{{ todayAgenda.appointments_count }}</strong></div>
                <div><span>Servicios programados</span><strong>{{ todayAgenda.services_count }}</strong></div>
                <div><span>Próxima cita</span><strong>{{ nextAppointment ? nextAppointment.visible_start_time : 'Sin citas' }}</strong></div>
            </div>
            <div v-if="todayAppointments.length" class="today-list">
                <article v-for="appointment in todayAppointments" :key="appointment.id" class="today-appointment">
                    <div class="today-appointment__time">{{ appointment.visible_start_time }}</div>
                    <div class="today-appointment__body"><strong>{{ appointment.client_name }}</strong><span>{{ appointment.visible_items.map(item => item.service_name).join(' · ') }}</span><small>{{ appointment.visible_items.map(item => item.assigned_to.name).filter((name, index, all) => all.indexOf(name) === index).join(', ') }}</small></div>
                    <VChip size="small" variant="tonal" :color="appointment.operational_status === 'pending_checkout' ? 'warning' : 'primary'">{{ appointment.status_label }}</VChip>
                    <VBtn icon="mdi-eye-outline" variant="text" :aria-label="`Ver detalle de ${appointment.client_name}`" @click="router.visit(`/appointments?view=day&date=${today}&month=${today.slice(0, 7)}&appointment=${appointment.id}`)" />
                </article>
            </div>
            <div v-else class="today-empty">No hay citas pendientes para hoy.</div>
        </section>

        <section v-if="can('services.view') || can('users.view') || can('expenses.access') || canAny(['sales.view_own', 'sales.view_all'])" class="quick-actions">
            <div class="quick-actions__label">Accesos rápidos</div>
            <div class="d-flex flex-wrap ga-2">
                <VBtn v-if="can('sales.create')" color="primary" size="small" prepend-icon="mdi-receipt-text-plus-outline" @click="router.visit('/sales/new')">Nueva venta</VBtn>
                <VBtn v-if="canAny(['sales.view_own', 'sales.view_all'])" variant="text" size="small" prepend-icon="mdi-file-document-outline" @click="router.visit('/invoices')">Facturas</VBtn>
                <VBtn v-if="can('expenses.create')" variant="text" size="small" prepend-icon="mdi-cash-minus" @click="router.visit('/expenses')">Registrar gasto</VBtn>
                <VBtn v-if="can('services.view')" variant="text" size="small" prepend-icon="mdi-hand-heart-outline" @click="router.visit('/configuration/services')">Servicios</VBtn>
                <VBtn v-if="can('users.view')" variant="text" size="small" prepend-icon="mdi-account-group-outline" @click="router.visit('/configuration/users')">Usuarios</VBtn>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.today-panel { padding: 24px; border: 1px solid var(--sl-glass-border); border-radius: var(--sl-radius-surface); background: color-mix(in oklch, var(--sl-surface) 92%, var(--sl-secondary) 8%); box-shadow: var(--sl-shadow-raised); animation: home-enter 260ms var(--sl-ease) both; }
.today-panel__intro, .today-appointment { display: flex; align-items: center; gap: 16px; }.today-panel__intro { justify-content: space-between; }.today-panel h2 { margin: 2px 0 0; font-size: 1.3rem; }.today-panel__summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 24px 0 16px; }.today-panel__summary div { display: grid; gap: 4px; }.today-panel__summary span, .today-appointment span, .today-appointment small { color: rgba(var(--v-theme-on-surface), .62); font-size: .78rem; }.today-panel__summary strong { font-size: 1.15rem; }.today-list { border-top: 1px solid var(--sl-border); }.today-appointment { min-width: 0; padding: 14px 0; border-bottom: 1px solid var(--sl-border); }.today-appointment__time { width: 46px; color: rgb(var(--v-theme-primary)); font-weight: 800; }.today-appointment__body { display: grid; min-width: 0; flex: 1; gap: 2px; }.today-appointment__body span, .today-appointment__body small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.today-empty { padding: 24px 0 4px; color: rgba(var(--v-theme-on-surface), .65); }.quick-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 14px; margin-top: 20px; }.quick-actions__label { color: rgba(var(--v-theme-on-surface), .6); font-size: .78rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }@keyframes home-enter { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }@media (max-width: 700px) { .today-panel { padding: 18px; }.today-panel__intro { align-items: flex-start; flex-direction: column; }.today-panel__intro :deep(.v-btn) { width: 100%; }.today-panel__summary { grid-template-columns: 1fr; gap: 10px; }.today-panel__summary div { grid-template-columns: 1fr auto; align-items: baseline; }.today-appointment { align-items: flex-start; flex-wrap: wrap; gap: 10px; }.today-appointment__body { flex-basis: calc(100% - 58px); }.today-appointment :deep(.v-chip) { margin-left: 46px; }.today-appointment :deep(.v-btn) { margin-left: auto; }.quick-actions { align-items: flex-start; flex-direction: column; }}@media (prefers-reduced-motion: reduce) { .today-panel { animation-duration: 1ms; } }
</style>

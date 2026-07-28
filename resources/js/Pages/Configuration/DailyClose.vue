<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ConfigurationLayout from '../../Layouts/ConfigurationLayout.vue';

type CloseReport = {
    id: number;
    date: string;
    date_label: string;
    recipient: string | null;
    trigger: 'scheduled' | 'manual' | 'test' | 'download';
    status: 'pending' | 'processing' | 'sent' | 'failed';
    attempts: number;
    error_message: string | null;
    requested_by: string | null;
    created_at: string | null;
    sent_at: string | null;
    download_url: string | null;
    retry_url: string | null;
};

type CloseSetting = {
    enabled: boolean;
    send_time: string;
    timezone: string;
    recipient_emails: string[];
};

const props = defineProps<{
    setting: CloseSetting;
    lastReport: CloseReport | null;
    reports: CloseReport[];
}>();

const form = useForm({
    enabled: props.setting.enabled,
    send_time: props.setting.send_time,
    recipient_emails: [...props.setting.recipient_emails],
});
const testing = ref(false);
const statusMeta = {
    pending: { label: 'Pendiente', color: 'warning', icon: 'mdi-clock-outline' },
    processing: { label: 'Procesando', color: 'info', icon: 'mdi-progress-clock' },
    sent: { label: 'Enviado', color: 'success', icon: 'mdi-check-circle-outline' },
    failed: { label: 'Fallido', color: 'error', icon: 'mdi-alert-circle-outline' },
};
const triggerLabels = { scheduled: 'Automático', manual: 'Manual', test: 'Prueba', download: 'Descarga' };
const recipientErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([field]) => field.startsWith('recipient_emails'))
        .map(([, message]) => message),
);
const recipientsReady = computed(() => form.recipient_emails.length > 0);

function save(): void {
    form.put('/configuration/daily-close', {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults();
        },
    });
}

function sendTest(): void {
    if (testing.value || form.isDirty) return;
    testing.value = true;
    router.post(
        '/configuration/daily-close/test',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                testing.value = false;
            },
        },
    );
}

function retryReport(report: CloseReport): void {
    if (!report.retry_url || testing.value) return;
    testing.value = true;
    router.post(
        report.retry_url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                testing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Cierre diario por correo" />
    <ConfigurationLayout>
        <PageHeader
            title="Cierre diario por correo"
            description="Programa la entrega del informe diario y consulta cada intento desde un solo lugar."
        >
            <template #actions>
                <VBtn color="primary" prepend-icon="mdi-content-save-outline" :loading="form.processing" @click="save">Guardar cambios</VBtn>
            </template>
        </PageHeader>

        <div class="close-overview">
            <section class="close-status" :class="{ 'close-status--enabled': form.enabled }">
                <div class="close-status__icon"><VIcon :icon="form.enabled ? 'mdi-email-check-outline' : 'mdi-email-off-outline'" size="26" /></div>
                <div>
                    <span>Envío diario</span>
                    <strong>{{ form.enabled ? `Activo a las ${form.send_time}` : 'Pausado' }}</strong>
                    <small>America/Tegucigalpa</small>
                </div>
            </section>
            <section class="last-delivery">
                <span>Último informe</span>
                <template v-if="lastReport">
                    <div class="last-delivery__line">
                        <strong>{{ lastReport.date_label }}</strong>
                        <VChip :color="statusMeta[lastReport.status].color" size="small" variant="tonal" :prepend-icon="statusMeta[lastReport.status].icon">
                            {{ statusMeta[lastReport.status].label }}
                        </VChip>
                    </div>
                    <small>{{ lastReport.sent_at ?? lastReport.created_at }}</small>
                </template>
                <strong v-else>Sin envíos todavía</strong>
            </section>
        </div>

        <section class="settings-section">
            <div class="settings-section__heading">
                <div>
                    <h2>Programación</h2>
                    <p>El servidor revisa cada minuto y realiza una sola entrega por fecha y destinatario.</p>
                </div>
                <VSwitch v-model="form.enabled" color="primary" inset hide-details label="Envío diario" />
            </div>
            <div class="schedule-grid">
                <VTextField v-model="form.send_time" type="time" label="Hora de envío" :error-messages="form.errors.send_time" :disabled="form.processing" />
                <VTextField :model-value="props.setting.timezone" label="Zona horaria" prepend-inner-icon="mdi-map-clock-outline" readonly />
            </div>
        </section>

        <section class="settings-section">
            <div class="settings-section__heading">
                <div>
                    <h2>Destinatarios</h2>
                    <p>Cada dirección recibe su propio correo; ningún destinatario puede ver las direcciones de los demás.</p>
                </div>
            </div>
            <VCombobox
                v-model="form.recipient_emails"
                label="Correos destinatarios"
                placeholder="Escribe un correo y presiona Enter"
                prepend-inner-icon="mdi-email-multiple-outline"
                multiple
                chips
                closable-chips
                clearable
                :error-messages="recipientErrors"
                :disabled="form.processing"
                hint="Puedes agregar hasta 20 correos. No se permiten duplicados."
                persistent-hint
            />
            <div class="settings-actions">
                <VBtn
                    variant="tonal"
                    color="primary"
                    prepend-icon="mdi-email-fast-outline"
                    :loading="testing"
                    :disabled="!recipientsReady || form.isDirty"
                    @click="sendTest"
                >
                    Enviar correo de prueba
                </VBtn>
                <VBtn href="/daily-close/download" variant="text" prepend-icon="mdi-file-pdf-box">Descargar cierre</VBtn>
            </div>
            <p v-if="form.isDirty" class="save-reminder">Guarda los cambios antes de enviar una prueba.</p>
            <VAlert v-if="lastReport?.error_message" type="error" variant="tonal" density="compact" class="mt-5">
                <strong>Último error:</strong> {{ lastReport.error_message }}
            </VAlert>
        </section>

        <section class="history-section">
            <div class="settings-section__heading">
                <div>
                    <h2>Historial reciente</h2>
                    <p>Estados por destinatario para envíos automáticos, manuales, pruebas y descargas.</p>
                </div>
            </div>
            <div v-if="reports.length" class="history-list">
                <article v-for="report in reports" :key="report.id" class="history-row">
                    <div class="history-row__date">
                        <strong>{{ report.date_label }}</strong>
                        <small>{{ report.created_at }}</small>
                    </div>
                    <div>
                        <span>{{ triggerLabels[report.trigger] }}</span>
                        <small>{{ report.requested_by ?? 'Proceso automático' }}</small>
                    </div>
                    <div class="history-row__recipient">
                        <span>{{ report.recipient ?? 'Solo descarga' }}</span>
                        <small>{{ report.attempts }} intento{{ report.attempts === 1 ? '' : 's' }}</small>
                    </div>
                    <VChip :color="statusMeta[report.status].color" size="small" variant="tonal" :prepend-icon="statusMeta[report.status].icon">
                        {{ statusMeta[report.status].label }}
                    </VChip>
                    <div class="history-row__actions">
                        <VBtn
                            v-if="report.retry_url"
                            icon="mdi-refresh"
                            variant="text"
                            size="small"
                            aria-label="Reintentar correo"
                            :loading="testing"
                            @click="retryReport(report)"
                        />
                        <VBtn
                            v-if="report.download_url"
                            :href="report.download_url"
                            icon="mdi-download-outline"
                            variant="text"
                            size="small"
                            aria-label="Descargar informe"
                        />
                        <span v-if="!report.download_url && !report.retry_url" class="history-row__placeholder">PDF pendiente</span>
                    </div>
                </article>
            </div>
            <EmptyState
                v-else
                icon="mdi-file-clock-outline"
                title="Aún no hay cierres"
                description="El historial aparecerá al generar, probar o enviar el primer informe."
            />
        </section>
    </ConfigurationLayout>
</template>

<style scoped>
.close-overview {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
    gap: 12px;
    margin-bottom: 28px;
}
.close-status,
.last-delivery {
    min-height: 116px;
    padding: 20px;
    border-radius: var(--sl-radius-surface);
    background: var(--sl-surface-soft);
}
.close-status {
    display: flex;
    align-items: center;
    gap: 16px;
}
.close-status--enabled {
    background: color-mix(in oklch, var(--sl-success) 11%, var(--sl-surface));
}
.close-status__icon {
    display: grid;
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    place-items: center;
    color: var(--sl-primary);
    border-radius: 50%;
    background: var(--sl-surface);
    box-shadow: var(--sl-shadow-inset);
}
.close-status div:last-child,
.last-delivery {
    display: grid;
    align-content: center;
    gap: 4px;
    min-width: 0;
}
.close-status span,
.last-delivery > span {
    color: var(--sl-text-muted);
    font-size: var(--sl-label-size);
}
.close-status strong,
.last-delivery strong {
    font-size: 1rem;
}
.close-status small,
.last-delivery small {
    color: var(--sl-text-muted);
}
.last-delivery__line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.settings-section,
.history-section {
    padding-block: 28px;
    border-top: 1px solid var(--sl-border);
}
.settings-section__heading {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 20px;
}
.settings-section__heading h2 {
    margin: 0 0 4px;
    font-size: var(--sl-section-title-size);
}
.settings-section__heading p {
    max-width: 68ch;
    margin: 0;
    color: var(--sl-text-muted);
}
.schedule-grid {
    display: grid;
    gap: 16px;
}
.schedule-grid {
    grid-template-columns: minmax(180px, 0.5fr) minmax(260px, 1fr);
    max-width: 720px;
}
.settings-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
}
.save-reminder {
    margin: 8px 0 0;
    color: var(--sl-text-muted);
    font-size: var(--sl-label-size);
}
.history-list {
    overflow: hidden;
    border-radius: var(--sl-radius-surface);
    background: var(--sl-surface-soft);
}
.history-row {
    display: grid;
    grid-template-columns: 1.05fr 0.9fr minmax(180px, 1.4fr) auto minmax(42px, auto);
    align-items: center;
    gap: 14px;
    min-height: 72px;
    padding: 12px 16px;
}
.history-row + .history-row {
    border-top: 1px solid var(--sl-border);
}
.history-row > div {
    display: grid;
    gap: 2px;
    min-width: 0;
}
.history-row small {
    overflow: hidden;
    color: var(--sl-text-muted);
    font-size: 0.75rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.history-row__recipient span {
    overflow-wrap: anywhere;
}
.history-row__placeholder {
    color: var(--sl-text-muted);
    font-size: var(--sl-label-size);
}
.history-row__actions {
    display: flex !important;
    align-items: center;
    justify-content: flex-end;
}
@media (max-width: 900px) {
    .history-row {
        grid-template-columns: 1fr auto 42px;
    }
    .history-row > div:nth-child(2),
    .history-row > div:nth-child(3) {
        grid-column: 1;
    }
    .history-row > .v-chip {
        grid-column: 2;
        grid-row: 1;
    }
    .history-row > .history-row__actions,
    .history-row__placeholder {
        grid-column: 3;
        grid-row: 1;
    }
}
@media (max-width: 600px) {
    .close-overview,
    .schedule-grid {
        grid-template-columns: 1fr;
    }
    .settings-section__heading {
        flex-direction: column;
    }
    .settings-actions .v-btn {
        flex: 1 1 100%;
    }
    .history-row {
        padding-inline: 12px;
    }
}
</style>

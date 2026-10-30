<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps<{ saleId?: number; appointmentId?: number }>();
const opened = ref(false);
const scanning = ref(false);
const error = ref('');
const name = ref('');
const video = ref<HTMLVideoElement>();
const form = useForm({ token: '' });
let controls: { stop: () => void } | undefined;
function stop() {
    controls?.stop();
    controls = undefined;
    const stream = video.value?.srcObject as MediaStream | null;
    stream?.getTracks().forEach((track) => track.stop());
    scanning.value = false;
}
function close() {
    stop();
    opened.value = false;
    name.value = '';
    form.reset();
    form.clearErrors();
}
async function scan() {
    error.value = '';
    scanning.value = true;
    try {
        const { BrowserQRCodeReader } = await import('@zxing/browser');
        const reader = new BrowserQRCodeReader();
        controls = await reader.decodeFromVideoDevice(undefined, video.value!, (result) => {
            if (result) {
                form.token = result.getText();
                stop();
                void resolve();
            }
        });
        if (!opened.value || form.token) stop();
    } catch {
        error.value = 'No se pudo abrir la cámara. Permite su acceso o ingresa el código temporal.';
        stop();
    }
}
async function resolve() {
    form.token = form.token.trim();
    name.value = '';
    error.value = '';
    try {
        const response = await fetch('/loyalty/qr/resolve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie
                        .split('; ')
                        .find((x) => x.startsWith('XSRF-TOKEN='))
                        ?.split('=')
                        .slice(1)
                        .join('=') ?? '',
                ),
            },
            body: JSON.stringify({ token: form.token.trim() }),
        });
        if (!response.ok) throw new Error();
        name.value = (await response.json()).name;
    } catch {
        error.value = 'Código inválido o vencido. Solicita uno nuevo.';
    }
}
onBeforeUnmount(stop);
</script>
<template>
    <VBtn variant="tonal" prepend-icon="mdi-qrcode-scan" @click="opened = true">{{
        appointmentId ? 'Vincular clienta' : 'Asignar puntos'
    }}</VBtn
    ><VDialog
        :model-value="opened"
        max-width="520"
        :persistent="form.processing"
        @update:model-value="!form.processing && close()"
        ><VCard class="pa-5"
            ><h2>{{ appointmentId ? 'Vincular cita' : 'Asignar puntos' }} #{{ appointmentId ?? saleId }}</h2>
            <p>
                {{
                    appointmentId
                        ? 'Los puntos se acreditarán al completar y cobrar. La cita aparecerá en el perfil de la clienta.'
                        : 'Solo se acreditarán servicios elegibles que no hayan recibido puntos.'
                }}
            </p>
            <video ref="video" autoplay playsinline muted style="width: 100%; max-height: 260px" /><VBtn
                :disabled="scanning"
                @click="scan"
                >Abrir cámara</VBtn
            ><VTextField
                v-model="form.token"
                label="Código temporal alternativo"
                maxlength="64"
                @update:model-value="name = ''"
            /><VBtn variant="text" @click="resolve">Verificar clienta</VBtn
            ><VAlert v-if="error || form.hasErrors" type="error">{{
                error || Object.values(form.errors).join(' ')
            }}</VAlert
            ><VAlert v-if="name" type="info">Confirmar asociación con {{ name }}.</VAlert
            ><VCardActions
                ><VBtn :disabled="form.processing" @click="close">Cancelar</VBtn
                ><VBtn
                    color="primary"
                    :disabled="!name"
                    :loading="form.processing"
                    @click="
                        form.post(
                            props.appointmentId
                                ? `/appointments/${props.appointmentId}/customer`
                                : `/sales/${props.saleId}/loyalty-awards`,
                            { preserveScroll: true, onSuccess: close },
                        )
                    "
                    >Confirmar asignación</VBtn
                ></VCardActions
            ></VCard
        ></VDialog
    >
</template>

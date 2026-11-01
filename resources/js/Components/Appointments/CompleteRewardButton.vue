<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
defineProps<{ appointmentId: number }>();
const open = ref(false);
const form = useForm({ confirmed: true });
</script>
<template>
    <VBtn
        color="primary"
        variant="tonal"
        prepend-icon="mdi-gift-outline"
        @click="
            open = true;
            form.clearErrors();
        "
        >Completar premio</VBtn
    ><VDialog v-model="open" max-width="480" :persistent="form.processing"
        ><VCard class="pa-5"
            ><h2 class="text-h6">Confirmar atención gratuita</h2>
            <p class="my-4">
                Confirma que el servicio del premio ya fue realizado. Se marcarán la cita como completada y el canje como entregado, sin generar una venta,
                cobro ni puntos por compra.
            </p>
            <VAlert v-if="form.hasErrors" type="error">{{ Object.values(form.errors).join(' ') }}</VAlert
            ><VCardActions
                ><VBtn :disabled="form.processing" @click="open = false">Volver</VBtn><VSpacer /><VBtn
                    color="primary"
                    :loading="form.processing"
                    @click="form.post(`/appointments/${appointmentId}/complete-reward`, { preserveScroll: true, onSuccess: () => (open = false) })"
                    >Confirmar premio atendido</VBtn
                ></VCardActions
            ></VCard
        ></VDialog
    >
</template>

<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';

const props = defineProps<{ modelValue: boolean; service?: any }>();
const emit = defineEmits(['update:modelValue']);
const { xs } = useDisplay();
const form = useForm({ name: '', description: '', duration_minutes: 60, price: '0.00', is_active: true });

watch(() => props.modelValue, open => {
    if (!open) return;
    form.defaults(props.service
        ? { name: props.service.name, description: props.service.description ?? '', duration_minutes: props.service.duration_minutes, price: props.service.price, is_active: props.service.is_active }
        : { name: '', description: '', duration_minutes: 60, price: '0.00', is_active: true })
        .reset()
        .clearErrors();
});

const save = () => {
    if (form.processing) return;
    const options = { preserveScroll: true, onSuccess: () => emit('update:modelValue', false) };
    props.service ? form.put(`/configuration/services/${props.service.id}`, options) : form.post('/configuration/services', options);
};
</script>

<template>
    <VDialog
        :model-value="modelValue"
        :fullscreen="xs"
        max-width="640"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <VCard>
            <VToolbar color="surface" flat>
                <VToolbarTitle class="font-weight-bold">{{ service ? 'Editar servicio' : 'Crear servicio' }}</VToolbarTitle>
                <VBtn icon="mdi-close" aria-label="Cerrar" @click="$emit('update:modelValue', false)" />
            </VToolbar>
            <VDivider />
            <VCardText class="pa-5 pa-sm-7">
                <p class="text-body-2 text-medium-emphasis mb-6">
                    Define la información que se mostrará para este servicio.
                </p>
                <VForm @submit.prevent="save">
                    <VTextField
                        v-model="form.name"
                        label="Nombre del servicio"
                        prepend-inner-icon="mdi-hand-heart-outline"
                        :error-messages="form.errors.name"
                    />
                    <VTextarea
                        v-model="form.description"
                        label="Descripción"
                        rows="3"
                        counter="2000"
                        :error-messages="form.errors.description"
                    />
                    <VRow>
                        <VCol cols="12" sm="6">
                            <VTextField
                                v-model.number="form.duration_minutes"
                                label="Duración (minutos)"
                                type="number"
                                min="1"
                                prepend-inner-icon="mdi-clock-outline"
                                :error-messages="form.errors.duration_minutes"
                            />
                        </VCol>
                        <VCol cols="12" sm="6">
                            <VTextField
                                v-model="form.price"
                                label="Precio"
                                prefix="L"
                                type="number"
                                min="0"
                                step="0.01"
                                :error-messages="form.errors.price"
                            />
                        </VCol>
                    </VRow>
                    <VSwitch v-model="form.is_active" label="Servicio activo" color="primary" inset hide-details />
                    <VCardActions class="px-0 pt-7 pb-0">
                        <VSpacer />
                        <VBtn variant="text" @click="$emit('update:modelValue', false)">Cancelar</VBtn>
                        <VBtn type="submit" color="primary" prepend-icon="mdi-content-save-outline" :loading="form.processing" :disabled="form.processing">
                            Guardar servicio
                        </VBtn>
                    </VCardActions>
                </VForm>
            </VCardText>
        </VCard>
    </VDialog>
</template>

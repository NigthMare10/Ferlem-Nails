<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';

const props = defineProps<{ modelValue: boolean; user?: any; roles: string[]; canAssignOwner: boolean }>();
const emit = defineEmits(['update:modelValue']);
const { xs } = useDisplay();
const roleNames: Record<string, string> = { owner: 'Propietario', administrator: 'Administrador', employee: 'Empleado' };
const roleItems = computed(() => props.roles
    .filter(role => props.canAssignOwner || role !== 'owner')
    .map(role => ({ title: roleNames[role] ?? role, value: role })));
const form = useForm({ name: '', email: '', password: '', password_confirmation: '', role: 'employee', is_active: true });

watch(() => props.modelValue, open => {
    if (!open) return;
    form.defaults(props.user
        ? { name: props.user.name, email: props.user.email, password: '', password_confirmation: '', role: props.user.role, is_active: props.user.is_active }
        : { name: '', email: '', password: '', password_confirmation: '', role: 'employee', is_active: true })
        .reset()
        .clearErrors();
});

const save = () => {
    if (form.processing) return;
    const options = { preserveScroll: true, onSuccess: () => emit('update:modelValue', false) };
    props.user ? form.put(`/configuration/users/${props.user.id}`, options) : form.post('/configuration/users', options);
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
                <VToolbarTitle class="font-weight-bold">{{ user ? 'Editar usuario' : 'Crear usuario' }}</VToolbarTitle>
                <VBtn icon="mdi-close" aria-label="Cerrar" @click="$emit('update:modelValue', false)" />
            </VToolbar>
            <VDivider />
            <VCardText class="pa-5 pa-sm-7">
                <p class="text-body-2 text-medium-emphasis mb-6">
                    {{ user ? 'Actualiza los datos y el nivel de acceso del usuario.' : 'Completa los datos para crear un nuevo acceso.' }}
                </p>
                <VForm @submit.prevent="save">
                    <VTextField
                        v-model="form.name"
                        label="Nombre completo"
                        autocomplete="name"
                        prepend-inner-icon="mdi-account-outline"
                        :error-messages="form.errors.name"
                    />
                    <VTextField
                        v-model="form.email"
                        label="Correo electrónico"
                        type="email"
                        autocomplete="email"
                        prepend-inner-icon="mdi-email-outline"
                        :error-messages="form.errors.email"
                    />
                    <template v-if="!user">
                        <VTextField
                            v-model="form.password"
                            label="Contraseña"
                            type="password"
                            autocomplete="new-password"
                            prepend-inner-icon="mdi-lock-outline"
                            hint="Mínimo 8 caracteres"
                            persistent-hint
                            :error-messages="form.errors.password"
                        />
                        <VTextField
                            v-model="form.password_confirmation"
                            label="Confirmar contraseña"
                            type="password"
                            autocomplete="new-password"
                            prepend-inner-icon="mdi-lock-check-outline"
                        />
                    </template>
                    <VSelect
                        v-model="form.role"
                        :items="roleItems"
                        label="Rol"
                        prepend-inner-icon="mdi-shield-account-outline"
                        :error-messages="form.errors.role"
                    />
                    <VSwitch
                        v-if="!user"
                        v-model="form.is_active"
                        label="Usuario activo"
                        color="primary"
                        inset
                        hide-details
                    />
                    <VCardActions class="px-0 pt-7 pb-0">
                        <VSpacer />
                        <VBtn variant="text" @click="$emit('update:modelValue', false)">Cancelar</VBtn>
                        <VBtn type="submit" color="primary" prepend-icon="mdi-content-save-outline" :loading="form.processing" :disabled="form.processing">
                            Guardar usuario
                        </VBtn>
                    </VCardActions>
                </VForm>
            </VCardText>
        </VCard>
    </VDialog>
</template>

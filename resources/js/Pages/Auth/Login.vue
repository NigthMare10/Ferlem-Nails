<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import StudioLemusBrand from '../../Components/StudioLemusBrand.vue';

const showPassword = ref(false);
const form = useForm({ email: '', password: '', remember: false });
const credentialError = computed(() => {
    const error = form.errors.email ?? '';
    return error.includes('credenciales') || error.includes('desactivada') ? error : '';
});
const emailError = computed(() => credentialError.value ? '' : form.errors.email);

const submit = () => {
    if (form.processing) return;
    form.post('/login', { onFinish: () => form.reset('password') });
};
</script>

<template>
    <Head title="Iniciar sesión" />
    <VApp>
        <VMain class="login-shell">
            <VRow no-gutters class="login-grid">
                <VCol cols="12" md="6" class="login-visual">
                    <div class="login-visual-content">
                        <StudioLemusBrand tone="dark" />
                        <div class="login-message">
                            <div class="login-kicker">El pulso de tu estudio</div>
                            <h1>Todo el día de tu salón,<br>en calma.</h1>
                            <p>
                                Agenda, cobros y decisiones claras en un espacio hecho para trabajar con intención.
                            </p>
                        </div>
                        <p class="login-visual-footer text-body-2 mb-0" style="opacity: .62">
                            Studio Lemus · operación con tacto
                        </p>
                    </div>
                </VCol>

                <VCol cols="12" md="6" class="login-form-panel">
                    <div class="login-form-wrap">
                        <VCard class="login-card pa-2 pa-sm-5">
                            <VCardText>
                                <div class="mb-7">
                                    <div class="login-form-kicker">Acceso privado</div>
                                    <h2 class="text-h4 font-weight-bold mb-2">Bienvenida de vuelta</h2>
                                    <p class="text-body-1 text-medium-emphasis mb-0">Ingresa para continuar con tu jornada.</p>
                                </div>

                                <VAlert
                                    v-if="credentialError"
                                    type="error"
                                    variant="tonal"
                                    density="comfortable"
                                    class="mb-5"
                                >
                                    {{ credentialError }}
                                </VAlert>

                                <VForm @submit.prevent="submit">
                                    <VTextField
                                        v-model="form.email"
                                        label="Correo electrónico"
                                        type="email"
                                        name="email"
                                        autocomplete="username"
                                        prepend-inner-icon="mdi-email-outline"
                                        :error-messages="emailError"
                                        autofocus
                                        class="mb-2"
                                    />
                                    <VTextField
                                        v-model="form.password"
                                        label="Contraseña"
                                        :type="showPassword ? 'text' : 'password'"
                                        name="password"
                                        autocomplete="current-password"
                                        prepend-inner-icon="mdi-lock-outline"
                                        :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                                        :error-messages="form.errors.password"
                                        @click:append-inner="showPassword = !showPassword"
                                    />
                                    <div class="d-flex align-center justify-space-between mb-5">
                                        <VCheckbox
                                            v-model="form.remember"
                                            label="Recordarme"
                                            color="primary"
                                            density="comfortable"
                                            hide-details
                                        />
                                    </div>
                                    <VBtn
                                        type="submit"
                                        block
                                        color="primary"
                                        size="large"
                                        prepend-icon="mdi-login"
                                        :loading="form.processing"
                                        :disabled="form.processing"
                                    >
                                        Iniciar sesión
                                    </VBtn>
                                </VForm>
                            </VCardText>
                        </VCard>
                        <p class="text-caption text-medium-emphasis text-center mt-6 mb-0">
                            Acceso exclusivo para el equipo de Studio Lemus
                        </p>
                    </div>
                </VCol>
            </VRow>
        </VMain>
    </VApp>
</template>

<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
const props = defineProps<{ mode: string; token?: string; email?: string }>();
const form = useForm({
    name: '',
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
    phone: '',
    token: props.token ?? '',
});
const page = usePage();
const titles: Record<string, string> = {
    login: 'Bienvenida a Lemus',
    register: 'Crea tu perfil Lemus',
    forgot: 'Recuperar contraseña',
    reset: 'Nueva contraseña',
};
function submit() {
    form.post(
        props.mode === 'register'
            ? '/clientes/registro'
            : props.mode === 'forgot'
              ? '/clientes/olvide-contrasena'
              : props.mode === 'reset'
                ? '/clientes/restablecer-contrasena'
                : '/clientes/login',
        { onFinish: () => form.reset('password', 'password_confirmation') },
    );
}
</script>
<template>
    <Head :title="titles[mode]" /><VApp class="loyalty-auth"
        ><main>
            <p class="brand">STUDIO LEMUS</p>
            <h1>{{ titles[mode] }}</h1>
            <p>Tu belleza tiene recompensas.</p>
            <VAlert v-if="(page.props.flash as any)?.success" type="success">{{
                (page.props.flash as any).success
            }}</VAlert>
            <VForm @submit.prevent="submit" class="mt-6">
                <VTextField
                    v-if="mode === 'register'"
                    v-model="form.name"
                    label="Nombre"
                    autocomplete="name"
                    :error-messages="form.errors.name"
                />
                <VTextField
                    v-model="form.email"
                    label="Correo electrónico"
                    type="email"
                    autocomplete="email"
                    :error-messages="form.errors.email"
                />
                <VTextField
                    v-if="mode !== 'forgot'"
                    v-model="form.password"
                    label="Contraseña"
                    type="password"
                    :autocomplete="mode === 'login' ? 'current-password' : 'new-password'"
                    :error-messages="form.errors.password"
                />
                <VTextField
                    v-if="mode === 'register' || mode === 'reset'"
                    v-model="form.password_confirmation"
                    label="Confirmar contraseña"
                    type="password"
                    autocomplete="new-password"
                />
                <VBtn block type="submit" color="primary" :loading="form.processing">{{
                    mode === 'login' ? 'Entrar' : mode === 'register' ? 'Crear cuenta' : 'Continuar'
                }}</VBtn>
            </VForm>
            <div class="auth-links">
                <Link href="/clientes/login">Iniciar sesión</Link><Link href="/clientes/registro">Registrarme</Link
                ><Link href="/clientes/olvide-contrasena">Olvidé mi contraseña</Link>
            </div>
        </main></VApp
    >
</template>
<style scoped>
.loyalty-auth {
    background: #faf7f2;
}
.loyalty-auth main {
    width: 100%;
    max-width: 460px;
    margin: auto;
    padding: 32px 22px;
}
.brand,
h1 {
    font-family: Georgia, serif;
}
.brand {
    font-size: 30px;
    letter-spacing: 2px;
    margin-bottom: 28px;
}
h1 {
    font-size: 28px;
    margin-bottom: 12px;
}
.auth-links {
    display: grid;
    gap: 14px;
    margin-top: 28px;
}
.auth-links a {
    color: #853e55;
}
</style>

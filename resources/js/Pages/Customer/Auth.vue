<script setup lang="ts">
// Approved Stitch access screens: same exported materials, measures and hierarchy.
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import '../../../css/stitch/base.css';
import '../../../css/stitch/login.css';
import '../../../css/stitch/register.css';
import '../../../css/stitch/recovery.css';
import '../../../css/stitch/motion.css';
import { useCustomerMotion } from '../../composables/useCustomerMotion';
const props = defineProps<{ mode: string; token?: string; email?: string }>();
const motionRoot = ref<HTMLElement>();
useCustomerMotion(motionRoot, () => props.mode);
const page = usePage();
const form = useForm({ name: '', email: props.email ?? '', password: '', password_confirmation: '', token: props.token ?? '' });
const showPassword = ref(false);
const showConfirm = ref(false);
const help = ref(false);
const kind = computed(() => (props.mode === 'login' ? 'login' : props.mode === 'register' ? 'register' : 'recovery'));
const title = computed(() => (kind.value === 'login' ? 'Bienvenida' : kind.value === 'register' ? 'Crear cuenta' : 'Recuperar contraseña'));
const fields = computed(() => [
    ...(kind.value === 'register' ? [{ name: 'name' as const, label: 'Nombre completo', icon: 'person', placeholder: 'Tu nombre completo' }] : []),
    { name: 'email' as const, label: 'Correo electrónico', icon: 'mail', placeholder: 'tu@correo.com' },
    { name: 'password' as const, label: kind.value === 'recovery' ? 'Nueva contraseña' : 'Contraseña', icon: 'lock', placeholder: '••••••••' },
    ...(kind.value !== 'login'
        ? [
              {
                  name: 'password_confirmation' as const,
                  label: kind.value === 'recovery' ? 'Confirmar nueva contraseña' : 'Confirmar contraseña',
                  icon: 'lock',
                  placeholder: '••••••••',
              },
          ]
        : []),
]);
function submit() {
    const endpoint =
        props.mode === 'register'
            ? '/clientes/registro'
            : props.mode === 'forgot'
              ? '/clientes/olvide-contrasena'
              : props.mode === 'reset'
                ? '/clientes/restablecer-contrasena'
                : '/clientes/login';
    form.post(endpoint, { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head :title="title" />
    <div ref="motionRoot" :class="`stitch-screen stitch-${kind} dark access-screen`">
        <div class="access-aura" aria-hidden="true">
            <i /><i /><i />
            <div v-if="kind === 'recovery'" class="recovery-grid" />
        </div>
        <header class="access-topbar">
            <Link href="/clientes/login" aria-label="Volver a iniciar sesión" class="access-icon"><span class="material-symbols-outlined">close</span></Link>
            <span>Studio Lemus</span>
            <button type="button" class="access-icon" aria-label="Ayuda" :aria-expanded="help" @click="help = !help">
                <span class="material-symbols-outlined">help_outline</span>
            </button>
        </header>
        <main class="access-main">
            <div class="access-heading" data-motion-reveal>
                <div class="access-seal"><span>SL</span></div>
                <p class="access-brand">STUDIO LEMUS</p>
                <h1>{{ title }}</h1>
                <p class="access-description">
                    {{
                        kind === 'login'
                            ? 'Accede a tu espacio exclusivo'
                            : kind === 'register'
                              ? 'Únete a nuestro atelier exclusivo'
                              : 'Ingresa tus datos para restablecer el acceso a tu cuenta.'
                    }}
                </p>
            </div>
            <p v-if="help" class="feedback" role="status">Usa el correo de tu cuenta Lemus. Para una nueva cuenta, elige Crear cuenta.</p>
            <p v-if="(page.props.flash as any)?.success" class="feedback" role="status">{{ (page.props.flash as any).success }}</p>
            <section
                data-motion-reveal
                :class="['access-form-panel', kind === 'login' ? 'liquid-glass-panel' : kind === 'register' ? 'glass-card-substrate' : 'liquid-glass-card']"
            >
                <span data-motion-sheen aria-hidden="true" />
                <div v-if="kind === 'login'" class="access-specular-beam" aria-hidden="true" />
                <div v-if="kind === 'register'" class="access-specular-rim" aria-hidden="true" />
                <form @submit.prevent="submit">
                    <div v-for="field in fields" :key="field.name" class="access-field">
                        <div class="access-label-row">
                            <label :for="field.name">{{ field.label }}</label
                            ><Link v-if="field.name === 'password' && kind === 'login'" href="/clientes/olvide-contrasena">¿Olvidaste tu contraseña?</Link>
                        </div>
                        <div :class="['access-input', kind === 'register' ? 'glass-input' : kind === 'login' ? 'liquid-glass-input' : 'input-ambient-glow']">
                            <span class="material-symbols-outlined" aria-hidden="true">{{ field.icon }}</span>
                            <input
                                :id="field.name"
                                v-model="form[field.name]"
                                :name="field.name"
                                :placeholder="field.placeholder"
                                :type="
                                    field.name === 'email'
                                        ? 'email'
                                        : field.name === 'password'
                                          ? showPassword
                                              ? 'text'
                                              : 'password'
                                          : field.name === 'password_confirmation'
                                            ? showConfirm
                                                ? 'text'
                                                : 'password'
                                            : 'text'
                                "
                                :autocomplete="
                                    field.name === 'name' ? 'name' : field.name === 'email' ? 'email' : kind === 'login' ? 'current-password' : 'new-password'
                                "
                                :aria-invalid="!!form.errors[field.name]"
                                :aria-describedby="form.errors[field.name] ? `${field.name}-error` : undefined"
                                required
                            />
                            <button
                                v-if="field.name === 'password' || field.name === 'password_confirmation'"
                                class="reveal-password"
                                type="button"
                                :aria-label="`Mostrar u ocultar ${field.label.toLowerCase()}`"
                                @click="field.name === 'password' ? (showPassword = !showPassword) : (showConfirm = !showConfirm)"
                            >
                                <span class="material-symbols-outlined">{{
                                    (field.name === 'password' ? showPassword : showConfirm) ? 'visibility_off' : 'visibility'
                                }}</span>
                            </button>
                        </div>
                        <p v-if="form.errors[field.name]" :id="`${field.name}-error`" class="field-error" role="alert">{{ form.errors[field.name] }}</p>
                    </div>
                    <button
                        :class="['access-submit', kind === 'login' ? 'btn-couture' : kind === 'register' ? 'couture-button' : 'couture-button-gradient']"
                        type="submit"
                        :disabled="form.processing"
                    >
                        <span>{{
                            form.processing ? 'Procesando…' : kind === 'login' ? 'Iniciar sesión' : kind === 'register' ? 'Crear cuenta' : 'Cambiar contraseña'
                        }}</span
                        ><span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                    </button>
                </form>
            </section>
            <footer class="access-links">
                <p v-if="kind === 'login'"><span>¿Aún no tienes cuenta?</span><Link href="/clientes/registro">Crear cuenta</Link></p>
                <p v-else-if="kind === 'register'">¿Ya tienes cuenta? <Link href="/clientes/login">Iniciar sesión</Link></p>
                <Link v-else href="/clientes/login" class="recovery-back"
                    ><span class="material-symbols-outlined">arrow_back</span>¿Recordaste tu contraseña? Volver a iniciar sesión</Link
                >
                <div v-if="kind === 'login'" class="login-signature"><i />ATELIER PRIVÉ<i /></div>
            </footer>
        </main>
        <footer v-if="kind !== 'login'" class="atelier-signature">• ATELIER PRIVÉ •</footer>
    </div>
</template>

<style scoped>
.access-screen {
    min-height: max(884px, 100dvh);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    color: #e9dfec;
    background: #16121a;
    overflow-x: clip;
    position: relative;
}
.stitch-login.access-screen {
    background: #120e16;
}
.stitch-register.access-screen {
    background: #110d15;
}
.access-aura {
    position: fixed;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
    z-index: -1;
}
.access-aura i {
    position: absolute;
    display: block;
    border-radius: 50%;
    filter: blur(90px);
}
.access-aura i:nth-child(1) {
    width: 340px;
    height: 340px;
    top: -128px;
    left: calc(50% - 170px);
    background: #d88b9e1a;
}
.access-aura i:nth-child(2) {
    width: 280px;
    height: 280px;
    top: 33%;
    right: -96px;
    background: #50445f33;
    filter: blur(100px);
}
.access-aura i:nth-child(3) {
    width: 300px;
    height: 300px;
    bottom: 48px;
    left: -80px;
    background: #39081a40;
    filter: blur(110px);
}
.stitch-login .access-aura i:first-child {
    top: -15%;
    background: linear-gradient(#d88b9e4d, #b8a9c926, transparent);
    opacity: 0.4;
    width: 340px;
    height: 340px;
}
.stitch-register .access-aura i:first-child {
    left: -96px;
    width: 384px;
    height: 384px;
}
.stitch-register .access-aura i:nth-child(2) {
    width: 380px;
    height: 380px;
    right: -112px;
    background: #d0c0e11a;
}
.stitch-register .access-aura i:nth-child(3) {
    bottom: -96px;
    left: 25%;
    background: #ffb1c31a;
}
.recovery-grid {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(#d6c1c5 1px, transparent 1px);
    background-size: 24px 24px;
    opacity: 0.035;
}
.access-topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    margin: auto;
    height: 56px;
    max-width: 448px;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #16121acc;
    backdrop-filter: blur(24px);
    border-bottom: 1px solid #52434633;
    z-index: 50;
}
.access-topbar > span {
    font:
        600 20px/28px 'Syne',
        sans-serif;
    letter-spacing: 0.05em;
}
.access-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    color: #d6c1c5;
}
.access-icon .material-symbols-outlined {
    font-size: 22px;
}
.access-main {
    width: 100%;
    max-width: 448px;
    margin: auto;
    padding: 80px 20px 32px;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
}
.access-heading {
    text-align: center;
    display: flex;
    align-items: center;
    flex-direction: column;
    margin-bottom: 24px;
}
.access-seal {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    padding: 1px;
    background: linear-gradient(#ffb1c380, #5243464d, transparent);
    margin-bottom: 10px;
}
.access-seal span {
    display: grid;
    place-items: center;
    width: 100%;
    height: 100%;
    border-radius: inherit;
    background: #221e26e6;
    border: 1px solid #ffffff1a;
    font:
        600 20px/28px 'Syne',
        sans-serif;
    color: #ffb1c3;
    letter-spacing: -0.05em;
}
.access-brand {
    font-size: 10px;
    font-weight: 600;
    line-height: 14px;
    letter-spacing: 0.22em;
    color: #d0c0e1;
}
.access-heading h1 {
    font:
        600 26px/34px 'Syne',
        sans-serif;
    margin-top: 8px;
    letter-spacing: -0.025em;
}
.access-description {
    font-size: 13px;
    line-height: 20px;
    color: #d6c1c5;
    max-width: 320px;
    margin-top: 4px;
}
.access-form-panel {
    border-radius: 24px;
    padding: 24px;
    position: relative;
    overflow: hidden;
}
.access-specular-beam {
    position: absolute;
    top: -48px;
    left: 25%;
    width: 50%;
    height: 80px;
    background: #ffffff1a;
    filter: blur(24px);
    pointer-events: none;
}
.access-specular-rim {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, #ffffff66, transparent);
    pointer-events: none;
}
.access-field + .access-field {
    margin-top: 16px;
}
.access-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    margin-bottom: 6px;
    font-size: 12px;
    line-height: 16px;
    letter-spacing: 0.06em;
    color: #d6c1c5;
    font-weight: 500;
}
.access-label-row a {
    color: #ffb1c3;
    font-size: 12px;
    letter-spacing: 0.06em;
}
.access-input {
    min-height: 52px;
    display: flex;
    align-items: center;
    padding: 0 16px;
    border-radius: 16px;
    gap: 12px;
}
.access-input > .material-symbols-outlined {
    color: #9f8c8f;
    font-size: 20px;
}
.access-input input {
    width: 100%;
    padding: 0;
    background: transparent;
    border: 0;
    outline: none;
    font-size: 15px;
    line-height: 24px;
}
.access-input input::placeholder {
    color: #d6c1c566;
    opacity: 1;
}
.access-input input:focus-visible {
    outline: 0;
}
.access-input:focus-within {
    outline: 1px solid #ffb1c3a6;
}
.reveal-password {
    flex: none;
    display: grid;
    place-items: center;
    padding: 4px;
    color: #9f8c8f;
}
.reveal-password .material-symbols-outlined {
    font-size: 20px;
}
.access-submit {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    height: 54px;
    border-radius: 999px;
    font-size: 14px;
    line-height: 20px;
    font-weight: 600;
    letter-spacing: 0.04em;
    margin-top: 28px;
    color: #39081a;
}
.access-submit .material-symbols-outlined {
    font-size: 18px;
}
.access-links {
    margin-top: 24px;
    text-align: center;
    color: #d6c1c5;
    font-size: 13px;
    line-height: 20px;
}
.access-links a {
    color: #ffb1c3;
    font-size: 14px;
    line-height: 20px;
    font-weight: 600;
    margin-left: 4px;
}
.atelier-signature {
    text-align: center;
    padding: 16px;
    color: #9f8c8f99;
    font-size: 10px;
    letter-spacing: 0.25em;
    line-height: 14px;
}
.stitch-login .access-main {
    padding-top: 96px;
    padding-bottom: 8px;
    justify-content: space-between;
}
.stitch-login .access-seal {
    border-radius: 16px;
    margin-bottom: 16px;
    background: linear-gradient(45deg, #ffffff4d, #d88b9e4d, #ffffff1a);
}
.stitch-login .access-seal span {
    background: #110d15cc;
    letter-spacing: 0.1em;
}
.stitch-login .access-heading {
    margin-top: 8px;
}
.stitch-login .access-heading h1 {
    margin: 6px 0 0;
}
.stitch-login .access-brand {
    margin-bottom: 0;
}
.stitch-login .access-form-panel {
    margin: auto 0;
}
.stitch-login .access-field + .access-field {
    margin-top: 20px;
}
.stitch-login .access-label-row {
    color: #e9dfec;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 0.04em;
    margin-bottom: 8px;
}
.stitch-login .access-label-row a {
    font-size: 12px;
    letter-spacing: 0.06em;
    font-weight: 500;
}
.stitch-login .access-input {
    min-height: 56px;
}
.stitch-login .access-input input {
    font-size: 15px;
}
.stitch-login .access-submit {
    height: 56px;
    color: white;
    margin-top: 28px;
}
.stitch-login .access-links {
    padding-top: 24px;
    margin: 0;
}
.stitch-login .access-links p {
    display: inline-block;
    padding: 8px 16px;
    border: 1px solid #ffffff0d;
    border-radius: 999px;
    background: #110d154d;
}
.stitch-login .atelier-signature {
    padding-top: 8px;
}
.stitch-recovery .access-heading {
    margin-top: 12px;
}
.stitch-recovery .access-seal {
    width: 64px;
    height: 64px;
    margin-bottom: 12px;
    background: #1e1a22;
    border: 1px solid #ffffff33;
    box-shadow: 0 8px 20px #0007;
}
.stitch-recovery .access-heading h1 {
    margin-top: 6px;
    margin-bottom: 8px;
}
.stitch-recovery .access-input {
    border: 1px solid #ffffff1a;
    border-radius: 8px;
    background: #ffffff09;
    padding: 0 14px;
}
.stitch-recovery .access-field + .access-field {
    margin-top: 20px;
}
.stitch-recovery .access-label-row {
    color: #e9dfec;
    margin-bottom: 8px;
}
.stitch-recovery .access-form-panel {
    margin-bottom: 24px;
}
.stitch-recovery .access-links {
    margin: 0;
}
.stitch-recovery .access-links .recovery-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 48px;
    padding: 10px 20px;
    border: 1px solid #ffffff26;
    background: linear-gradient(135deg, #ffffff0a, #d0c0e105);
    border-radius: 999px;
    font-size: 12px;
    line-height: 16px;
    color: #d0c0e1;
    font-weight: 500;
    margin: 0;
}
.recovery-back .material-symbols-outlined {
    font-size: 18px;
}
@media (max-width: 360px) {
    .access-form-panel {
        padding: 20px;
    }
    .access-label-row a {
        max-width: 125px;
        text-align: right;
    }
}
.stitch-login .access-main {
    min-height: max(884px, 100dvh);
    padding-bottom: 40px;
}
.stitch-login .access-description {
    font-size: 15px;
    line-height: 24px;
}
.stitch-login .access-label-row {
    line-height: 20px;
}
.stitch-login .access-label-row a {
    padding: 4px 0;
    line-height: 16px;
}
.stitch-login .access-links {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-top: 24px;
    padding-bottom: 8px;
}
.stitch-login .access-links > p {
    padding: 8px 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.stitch-login .access-links a {
    margin-left: 0;
}
.login-signature {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 16px;
    color: #9f8c8f;
    opacity: 0.4;
    font-size: 10px;
    line-height: 14px;
    letter-spacing: 0.1em;
}
.login-signature i {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #ffb1c3cc;
}
.stitch-login .access-seal span {
    background: linear-gradient(#ffb1c31a, transparent), #110d15cc;
}
.stitch-register .access-links {
    margin-top: 24px;
}
.stitch-register .access-links p {
    line-height: 20px;
}
.stitch-register .access-label-row {
    margin-left: 4px;
}
.stitch-recovery .access-aura {
    z-index: 0;
}
.stitch-recovery .access-main {
    z-index: 1;
}
.stitch-recovery .atelier-signature {
    z-index: 1;
}
.stitch-recovery .access-seal {
    padding: 0;
}
.stitch-recovery .access-seal span {
    background: transparent;
    border: 0;
}
.stitch-recovery .access-submit {
    margin-top: 20px;
    color: #541d2e;
}
.stitch-recovery .access-input > .material-symbols-outlined,
.stitch-recovery .reveal-password {
    color: #d6c1c5;
}
.atelier-signature {
    line-height: 24px;
    min-height: 56px;
}
.stitch-login .access-label-row {
    letter-spacing: 0.025em;
}
.access-submit {
    letter-spacing: 0.025em;
}
.access-links a {
    letter-spacing: 0.04em;
}
.stitch-login .access-brand {
    opacity: 0.9;
}
.stitch-login .access-form-panel {
    backdrop-filter: blur(40px);
}
.stitch-login .access-seal {
    background: linear-gradient(45deg, #ffffff4d, #ffb1c34d, #ffffff1a);
    box-shadow:
        0 10px 15px -3px #0006,
        0 4px 6px -4px #0006;
}
.stitch-login .access-seal span {
    border: 0;
    border-radius: 15px;
    padding-left: 2px;
}
.stitch-login .reveal-password {
    padding: 6px;
}
.login-signature {
    font-weight: 600;
}
.atelier-signature {
    font-weight: 600;
}
.stitch-register .access-icon {
    width: 30px;
    height: 30px;
}
.stitch-recovery .access-main {
    padding-top: 88px;
}
.stitch-recovery .access-description {
    line-height: 1.625;
    margin-top: 0;
}
.stitch-recovery .access-brand {
    opacity: 0.9;
}
.stitch-recovery .atelier-signature {
    color: #d6c1c566;
    letter-spacing: 0.2em;
}
@media (min-width: 640px) {
    .stitch-login .access-form-panel {
        padding: 28px;
    }
}
@media (min-width: 768px) {
    .stitch-recovery .access-heading h1 {
        font-size: 32px;
        line-height: 40px;
    }
}
</style>

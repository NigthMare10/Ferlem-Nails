<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
const props = defineProps<{ customer: any; hasAvatar: boolean; level: any; qrBusy: boolean }>();
defineEmits<{ qr: [] }>();
const editing = ref('');
const form = useForm({ name: props.customer.name, phone: props.customer.phone ?? '', birthday: props.customer.birthday ?? '' });
const photo = useForm({ photo: null as File | null });
const photoVersion = ref(0);
function save() {
    // Submit only the visible editor. Abandoned birthday edits must never be saved
    // with a different field, because birthdays become immutable once registered.
    form.transform((data) => ({
        name: editing.value === 'name' ? data.name : props.customer.name,
        phone: editing.value === 'phone' ? data.phone : (props.customer.phone ?? ''),
        birthday: editing.value === 'birthday' ? data.birthday : (props.customer.birthday ?? ''),
    })).patch('/clientes/perfil', {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = '';
            form.name = props.customer.name;
            form.phone = props.customer.phone ?? '';
            form.birthday = props.customer.birthday ?? '';
        },
    });
}
function upload(event: Event) {
    photo.photo = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (photo.photo) photo.post('/clientes/foto', { forceFormData: true, preserveScroll: true, onSuccess: () => photoVersion.value++ });
}
const birthday = (value: string) =>
    new Intl.DateTimeFormat('es-HN', { day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(value.slice(0, 10) + 'T12:00:00Z'));
</script>
<template>
    <div class="profile-stream">
        <section class="flex flex-col items-center text-center pt-2" data-motion-reveal>
            <label class="relative group mb-4 profile-photo" title="Cambiar foto"
                ><div
                    class="w-24 h-24 rounded-full p-[1.5px] bg-gradient-to-b from-primary-fixed/60 via-primary-container/40 to-outline-variant/20 shadow-[0_0_24px_rgba(255,174,220,0.18)]"
                >
                    <div class="w-full h-full rounded-full bg-surface-container-low flex items-center justify-center relative overflow-hidden backdrop-blur-md">
                        <div class="absolute inset-0 bg-gradient-to-tr from-primary-container/30 to-transparent" />
                        <img v-if="hasAvatar" class="profile-photo-image" :src="'/clientes/foto?v=' + photoVersion" alt="Mi foto de perfil" /><span
                            v-else
                            class="font-headline-xl text-headline-xl text-primary font-bold tracking-tight z-10"
                            >{{ customer.name.slice(0, 1) }}</span
                        >
                    </div>
                </div>
                <input
                    type="file"
                    class="photo-input"
                    accept="image/jpeg,image/png,image/webp"
                    aria-label="Cambiar foto de perfil"
                    :disabled="photo.processing"
                    @change="upload" />
                <div
                    class="absolute bottom-1 right-1 w-5 h-5 rounded-full bg-surface-container-lowest flex items-center justify-center border border-outline-variant/50"
                >
                    <div class="w-2.5 h-2.5 rounded-full bg-primary" /></div
            ></label>
            <p v-if="photo.errors.photo" class="field-error" role="alert">{{ photo.errors.photo }}</p>
            <h1 class="font-headline-md text-headline-md font-semibold text-on-surface tracking-tight mb-1">{{ customer.name }}</h1>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary-container/20 border border-primary-fixed/20 shadow-sm mb-2">
                <span class="material-symbols-outlined text-primary text-[14px]" style="font-variation-settings: 'FILL' 1">workspace_premium</span
                ><span class="font-label-sm text-label-sm text-primary tracking-wider uppercase font-semibold">{{ level?.name ?? 'Miembro Lemus' }}</span>
            </div>
            <p class="font-body-md text-body-md text-on-surface-variant font-normal profile-email">{{ customer.email }}</p>
        </section>
        <section class="liquid-glass-tier-1 rounded-lg p-5 flex flex-col gap-4" data-motion-reveal>
            <div class="flex items-center justify-between pb-1 border-b border-outline-variant/25">
                <span class="font-label-sm text-label-sm text-on-surface-variant tracking-wider uppercase">Información de la cuenta</span
                ><span class="font-label-sm text-label-sm text-outline">Datos personales</span>
            </div>
            <div
                v-for="field in [
                    { key: 'name' as const, label: 'Nombre completo' },
                    { key: 'phone' as const, label: 'Teléfono de contacto' },
                ]"
                :key="field.key"
                class="liquid-glass-well rounded-md p-3.5"
            >
                <div class="flex items-center justify-between profile-field-row">
                    <div class="flex flex-col gap-0.5 profile-field-value">
                        <span class="font-label-sm text-label-sm text-outline uppercase tracking-wider">{{ field.label }}</span
                        ><span class="font-body-md text-body-md text-on-surface font-medium">{{ customer[field.key] || 'Sin registrar' }}</span>
                    </div>
                    <button
                        :aria-label="`Editar ${field.label.toLowerCase()}`"
                        class="flex items-center gap-1 px-2.5 py-1 rounded-full bg-surface-variant/40 text-primary font-label-sm text-label-sm"
                        type="button"
                        @click="editing = editing === field.key ? '' : field.key"
                    >
                        <span class="material-symbols-outlined text-[14px]">edit</span><span>Editar</span>
                    </button>
                </div>
                <form v-if="editing === field.key" class="profile-edit" @submit.prevent="save">
                    <label :for="field.key">{{ field.label }}</label
                    ><input :id="field.key" v-model="form[field.key]" :type="field.key === 'phone' ? 'tel' : 'text'" :required="field.key === 'name'" />
                    <p v-if="form.errors[field.key]" class="field-error">{{ form.errors[field.key] }}</p>
                    <button type="submit" :disabled="form.processing">{{ form.processing ? 'Guardando…' : 'Guardar cambios' }}</button>
                </form>
            </div>
            <div class="liquid-glass-well rounded-md p-3.5 flex flex-col gap-2.5 border-outline-variant/20">
                <div class="flex items-center justify-between profile-field-row">
                    <div class="flex flex-col gap-0.5 profile-field-value">
                        <span class="font-label-sm text-label-sm text-outline uppercase tracking-wider">Fecha de nacimiento</span
                        ><span class="font-body-md text-body-md text-on-surface font-medium">{{
                            customer.birthday ? birthday(customer.birthday) : 'Sin registrar'
                        }}</span>
                    </div>
                    <div
                        v-if="customer.birthday"
                        class="flex items-center gap-1 text-outline px-2 py-0.5 rounded-full bg-surface-container/60 border border-outline-variant/30"
                    >
                        <span class="material-symbols-outlined text-[13px]">lock</span><span class="font-label-sm text-label-sm">Registrada</span>
                    </div>
                    <button v-else type="button" class="profile-register-date" @click="editing = editing === 'birthday' ? '' : 'birthday'">Registrar</button>
                </div>
                <form v-if="editing === 'birthday' && !customer.birthday" class="profile-edit" @submit.prevent="save">
                    <label for="birthday">Fecha de nacimiento</label><input id="birthday" v-model="form.birthday" type="date" required />
                    <p v-if="form.errors.birthday" class="field-error">{{ form.errors.birthday }}</p>
                    <button type="submit" :disabled="form.processing">Guardar fecha</button>
                </form>
                <div class="flex items-start gap-2 pt-2 border-t border-outline-variant/20">
                    <span class="material-symbols-outlined text-secondary text-[16px] shrink-0 mt-0.5">info</span>
                    <p class="font-label-sm text-label-sm text-secondary/90 leading-relaxed font-normal">
                        Una vez registrada tu fecha de nacimiento, no puede modificarse por motivos de seguridad del atelier y beneficios anuales.
                    </p>
                </div>
            </div>
        </section>
        <section class="flex flex-col gap-4 pt-1">
            <div class="liquid-glass-tier-2 rounded-lg p-4 flex flex-col gap-3">
                <button
                    class="w-full liquid-sheen-pill text-on-primary py-3 px-5 rounded-full flex items-center justify-center gap-2.5 font-label-lg text-label-lg tracking-wide profile-qr-trigger"
                    type="button"
                    :disabled="qrBusy"
                    @click="$emit('qr')"
                >
                    <span data-motion-sheen aria-hidden="true" />
                    <span class="material-symbols-outlined text-[20px]">qr_code_2</span><span>{{ qrBusy ? 'Generando…' : 'Mostrar mi QR temporal' }}</span>
                </button>
                <p class="font-label-sm text-label-sm text-on-surface-variant text-center px-2 leading-normal">
                    Código para identificarte en atelier y vincular tu visita o compra. No valida canjes de recompensas.
                </p>
            </div>
            <button
                class="w-full py-3 px-4 rounded-full border border-outline-variant/40 bg-surface-container-low/40 text-on-surface-variant flex items-center justify-center gap-2 font-label-md text-label-md"
                type="button"
                @click="router.post('/clientes/logout')"
            >
                <span class="material-symbols-outlined text-[18px] text-outline">logout</span><span>Cerrar sesión</span>
            </button>
        </section>
    </div>
</template>
<style scoped>
.profile-stream {
    display: flex;
    flex-direction: column;
    gap: 24px;
}
.profile-photo {
    cursor: pointer;
}
.photo-input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}
.profile-photo:focus-within {
    outline: 2px solid #ffaedc;
    outline-offset: 4px;
    border-radius: 50%;
}
.profile-photo-image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.profile-email {
    overflow-wrap: anywhere;
}
.profile-edit {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding-top: 14px;
    font-size: 12px;
}
.profile-edit input {
    background: #17081b;
    border: 1px solid #52434a;
    border-radius: 8px;
    padding: 12px;
    color: #f5daf6;
    color-scheme: dark;
}
.profile-edit button,
.profile-register-date {
    background: #8a3b6e66;
    color: #ffaedc;
    border-radius: 999px;
    padding: 10px 14px;
    font-size: 12px;
}
.profile-field-row {
    gap: 12px;
}
.profile-field-value {
    min-width: 0;
    flex: 1;
    overflow-wrap: anywhere;
}
.profile-field-row > button {
    min-height: 44px;
    flex-shrink: 0;
}
.profile-qr-trigger {
    position: relative;
    overflow: hidden;
    min-height: 52px;
}
@media (max-width: 360px) {
    .profile-field-row {
        flex-wrap: wrap;
    }
    .profile-field-value {
        flex-basis: 100%;
    }
}
.profile-register-date {
    padding: 4px 10px;
}
</style>

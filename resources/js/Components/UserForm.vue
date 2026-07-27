<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';

const props = defineProps<{ modelValue: boolean; user?: any; roles: string[]; canAssignOwner: boolean; canConfigurePayroll: boolean }>();
const emit = defineEmits(['update:modelValue']);
const { xs } = useDisplay();
const roleNames: Record<string, string> = { owner: 'Propietario', administrator: 'Administrador', employee: 'Empleado' };
const roleItems = computed(() => props.roles
    .filter(role => (props.canAssignOwner || role !== 'owner') && (props.canConfigurePayroll || role !== 'employee' || props.user?.role === 'employee'))
    .map(role => ({ title: roleNames[role] ?? role, value: role })));
const form = useForm({
    name: '', email: '', password: '', password_confirmation: '', role: 'employee', is_active: true,
    has_employment_profile: true, monthly_salary: '', contract_start_date: '', contract_end_date: '',
    is_indefinite: true, default_payment_method: 'transfer', auto_generate_payroll_expense: true,
});
const employmentEnabled = computed(() => props.canConfigurePayroll && (form.role === 'employee' || form.has_employment_profile));
const paymentMethods = [
    { title: 'Efectivo', value: 'cash' },
    { title: 'Tarjeta', value: 'card' },
    { title: 'Transferencia', value: 'transfer' },
];

watch(() => form.role, role => {
    if (role === 'employee') form.has_employment_profile = true;
});

watch(() => props.modelValue, open => {
    if (!open) return;
    const profile = props.user?.employment_profile;
    form.defaults(props.user
        ? { name: props.user.name, email: props.user.email, password: '', password_confirmation: '', role: props.user.role, is_active: props.user.is_active, has_employment_profile: props.user.role === 'employee' || Boolean(profile), monthly_salary: profile?.monthly_salary ?? '', contract_start_date: profile?.contract_start_date ?? '', contract_end_date: profile?.contract_end_date ?? '', is_indefinite: profile?.is_indefinite ?? true, default_payment_method: profile?.default_payment_method ?? 'transfer', auto_generate_payroll_expense: profile?.auto_generate_payroll_expense ?? false }
        : { name: '', email: '', password: '', password_confirmation: '', role: 'employee', is_active: true, has_employment_profile: true, monthly_salary: '', contract_start_date: hondurasToday(), contract_end_date: '', is_indefinite: true, default_payment_method: 'transfer', auto_generate_payroll_expense: true })
        .reset()
        .clearErrors();
});

const save = () => {
    if (form.processing) return;
    const options = { preserveScroll: true, onSuccess: () => emit('update:modelValue', false) };
    props.user ? form.put(`/configuration/users/${props.user.id}`, options) : form.post('/configuration/users', options);
};

function hondurasToday(): string {
    const parts = new Intl.DateTimeFormat('en-US', { timeZone: 'America/Tegucigalpa', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
    return `${values.year}-${values.month}-${values.day}`;
}
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
                    <template v-if="canConfigurePayroll">
                        <VSwitch
                            v-if="form.role !== 'employee'"
                            v-model="form.has_employment_profile"
                            label="Es personal operativo con salario"
                            color="primary"
                            inset
                        />
                        <VCard v-if="employmentEnabled" variant="outlined" class="pa-4 mt-2">
                            <div class="text-subtitle-1 font-weight-bold mb-1">Información laboral</div>
                            <div class="text-caption text-medium-emphasis mb-4">Los cambios crean un perfil histórico y no sobrescriben salarios anteriores.</div>
                            <VRow>
                                <VCol cols="12" sm="6"><VTextField v-model="form.monthly_salary" label="Salario mensual" prefix="L" inputmode="decimal" :error-messages="form.errors.monthly_salary" /></VCol>
                                <VCol cols="12" sm="6"><VTextField v-model="form.contract_start_date" type="date" label="Inicio del contrato" :error-messages="form.errors.contract_start_date" /></VCol>
                                <VCol cols="12"><VSwitch v-model="form.is_indefinite" label="Contrato indefinido" color="primary" inset :error-messages="form.errors.is_indefinite" /></VCol>
                                <VCol v-if="!form.is_indefinite" cols="12" sm="6"><VTextField v-model="form.contract_end_date" type="date" label="Fin del contrato" :error-messages="form.errors.contract_end_date" /></VCol>
                                <VCol cols="12" sm="6"><VTextField model-value="Día 15" label="Primer pago" readonly /></VCol>
                                <VCol cols="12" sm="6"><VTextField model-value="Último día del mes" label="Segundo pago" readonly /></VCol>
                                <VCol cols="12" sm="6"><VSelect v-model="form.default_payment_method" label="Método habitual de pago" :items="paymentMethods" :error-messages="form.errors.default_payment_method" /></VCol>
                                <VCol cols="12"><VSwitch v-model="form.auto_generate_payroll_expense" label="Generar gasto de nómina automáticamente" color="primary" inset :error-messages="form.errors.auto_generate_payroll_expense" /></VCol>
                            </VRow>
                        </VCard>
                    </template>
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

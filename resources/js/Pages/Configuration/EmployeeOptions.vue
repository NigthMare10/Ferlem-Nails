<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ConfigurationLayout from '../../Layouts/ConfigurationLayout.vue';
import { permissionLabel } from '../../utils/permissionLabels';
import EmployeeMealsDialog from '../../Components/EmployeeMealsDialog.vue';
import { ref } from 'vue';
import { formatTime } from '../../utils/time';
const mealsOpen = ref(false);
type Break = { weekday: number | null; date: string | null; starts_at: string; ends_at: string };
const props = defineProps<{
    employee: { id: number; name: string; display_order: number; is_owner: boolean };
    catalog: string[];
    direct: string[];
    inherited: string[];
    breaks: Break[];
}>();
const access = useForm({ permissions: [...new Set([...props.direct, ...props.inherited])] });
function savePermissions() {
    access
        .transform((data) => ({ permissions: data.permissions.filter((p) => !props.inherited.includes(p)) }))
        .put(`/configuration/users/${props.employee.id}/permissions`);
}
const schedule = useForm({
    display_order: props.employee.display_order,
    breaks: props.breaks.map((b) => ({ ...b, starts_at: b.starts_at.slice(0, 5), ends_at: b.ends_at.slice(0, 5) })),
});
const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'].map((title, value) => ({
    title,
    value,
}));
</script>
<template>
    <Head :title="`Configurar ${employee.name}`" />
    <ConfigurationLayout>
        <h1 class="mb-5">{{ employee.name }}</h1>
        <VCard class="pa-5 mb-6">
            <h2>Orden y comida</h2>
            <VForm
                @submit.prevent="
                    schedule
                        .transform((data) => ({
                            ...data,
                            breaks: props.breaks.map((b) => ({ ...b, starts_at: b.starts_at.slice(0, 5), ends_at: b.ends_at.slice(0, 5) })),
                        }))
                        .put(`/configuration/users/${employee.id}/breaks`)
                "
            >
                <VTextField
                    v-model.number="schedule.display_order"
                    label="Orden de vista (menor aparece primero)"
                    type="number"
                    min="0"
                    :error-messages="schedule.errors.display_order"
                />
                <VAlert v-if="Object.keys(schedule.errors).length" type="error">{{ Object.values(schedule.errors).join(' ') }}</VAlert>
                <VBtn type="submit" color="primary" :loading="schedule.processing">Guardar orden</VBtn>
            </VForm>
            <div class="mt-6">
                <h3>Comida semanal</h3>
                <p v-if="!breaks.length">Aún no hay horarios de comida configurados.</p>
                <p v-for="(b, i) in breaks" :key="i">
                    {{ b.date || days.find((d) => d.value === b.weekday)?.title }} · {{ formatTime(b.starts_at) }}–{{ formatTime(b.ends_at) }}
                </p>
                <VBtn class="mt-4" variant="tonal" prepend-icon="mdi-food-outline" @click="mealsOpen = true">Configurar comidas</VBtn>
            </div>
            <EmployeeMealsDialog v-model="mealsOpen" :employee="employee" :breaks="breaks" />
        </VCard>
        <VCard class="pa-5">
            <h2>Permisos individuales adicionales</h2>
            <p>Los permisos heredados permanecen activos. Cobrar a otras personas no concede acceso a reportes o configuración.</p>
            <VAlert v-if="access.hasErrors" type="error">{{ Object.values(access.errors).join(' ') }}</VAlert>
            <VForm @submit.prevent="savePermissions">
                <div class="permissions-grid">
                    <VCheckbox
                        v-for="permission in catalog"
                        :key="permission"
                        v-model="access.permissions"
                        :value="permission"
                        :label="permissionLabel(permission) + (inherited.includes(permission) ? ' (heredado)' : '')"
                        :disabled="inherited.includes(permission)"
                        hide-details
                    />
                </div>
                <VBtn v-if="!employee.is_owner" type="submit" class="mt-4" color="primary" :loading="access.processing">Guardar permisos</VBtn>
            </VForm>
        </VCard>
    </ConfigurationLayout>
</template>
<style scoped>
.break-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
    gap: 12px;
    margin-top: 16px;
}
.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr));
}
@media (max-width: 700px) {
    .break-row {
        grid-template-columns: 1fr;
    }
}
</style>

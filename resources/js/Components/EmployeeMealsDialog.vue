<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import AmPmTimeField from './AmPmTimeField.vue';
type Meal = { weekday: number | null; date: string | null; starts_at: string; ends_at: string };
const props = defineProps<{ modelValue: boolean; employee: { id: number; display_order: number }; breaks: Meal[] }>();
const emit = defineEmits<{ 'update:modelValue': [boolean] }>();
const { xs } = useDisplay();
const names = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
const week = ref<Array<Meal & { enabled: boolean }>>([]),
    extras = ref<Meal[]>([]);
const form = useForm({ display_order: 0, breaks: [] as Meal[] });
watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        form.clearErrors();
        form.display_order = props.employee.display_order;
        const seen = new Set<number>();
        extras.value = [];
        week.value = [1, 2, 3, 4, 5, 6, 0].map((weekday) => {
            const b = props.breaks.find((b) => b.date === null && b.weekday === weekday);
            return {
                weekday,
                date: null,
                starts_at: b?.starts_at.slice(0, 5) ?? '12:00',
                ends_at: b?.ends_at.slice(0, 5) ?? '13:00',
                enabled: !!b || !props.breaks.some((meal) => meal.date === null),
            };
        });
        for (const b of props.breaks) {
            if (b.date !== null || seen.has(b.weekday!)) extras.value.push({ ...b, starts_at: b.starts_at.slice(0, 5), ends_at: b.ends_at.slice(0, 5) });
            else seen.add(b.weekday!);
        }
    },
);
function save() {
    form.breaks = [...week.value.filter((b) => b.enabled).map(({ enabled, ...b }) => b), ...extras.value];
    form.put(`/configuration/users/${props.employee.id}/breaks`, { preserveScroll: true, onSuccess: () => emit('update:modelValue', false) });
}
</script>
<template>
    <VDialog
        :model-value="modelValue"
        :fullscreen="xs"
        max-width="820"
        scrollable
        :persistent="form.processing"
        @update:model-value="!form.processing && emit('update:modelValue', false)"
        ><VCard
            ><VCardTitle class="pa-5 d-flex justify-space-between align-center"
                ><h2 class="text-h6">Horarios de comida</h2>
                <VBtn
                    icon="mdi-close"
                    variant="text"
                    aria-label="Cerrar horarios de comida"
                    :disabled="form.processing"
                    @click="emit('update:modelValue', false)" /></VCardTitle
            ><VCardText
                ><p class="mb-5">
                    Configura la comida de lunes a domingo. Se bloqueará ese horario en la agenda; no se cambia el horario de trabajo del estudio.
                </p>
                <VAlert v-if="form.hasErrors" type="error" class="mb-4">{{ Object.values(form.errors).join(' ') }}</VAlert>
                <section v-for="day in week" :key="day.weekday!" class="meal-day">
                    <VCheckbox v-model="day.enabled" :label="names[day.weekday!]" hide-details />
                    <div class="meal-times">
                        <AmPmTimeField
                            v-model="day.starts_at"
                            :label="`${names[day.weekday!]} · Inicio`"
                            :disabled="!day.enabled || form.processing"
                        /><AmPmTimeField v-model="day.ends_at" :label="`${names[day.weekday!]} · Fin`" :disabled="!day.enabled || form.processing" />
                    </div>
                </section>
                <VExpansionPanels class="mt-5"
                    ><VExpansionPanel :title="`Excepciones y otros bloqueos (${extras.length})`"
                        ><VExpansionPanelText
                            ><p>Las excepciones por fecha reemplazan la comida semanal de ese día.</p>
                            <div v-for="(b, i) in extras" :key="i" class="meal-day">
                                <VTextField v-if="b.date !== null" v-model="b.date" type="date" label="Fecha de excepción" />
                                <p v-else>{{ names[b.weekday!] }} · bloqueo adicional</p>
                                <div class="meal-times">
                                    <AmPmTimeField v-model="b.starts_at" label="Inicio de excepción" /><AmPmTimeField
                                        v-model="b.ends_at"
                                        label="Fin de excepción"
                                    />
                                </div>
                                <VBtn variant="text" color="error" @click="extras.splice(i, 1)">Quitar excepción</VBtn>
                            </div>
                            <VBtn
                                variant="tonal"
                                @click="extras.push({ weekday: null, date: new Date().toLocaleDateString('en-CA'), starts_at: '12:00', ends_at: '13:00' })"
                                >Agregar excepción por fecha</VBtn
                            ></VExpansionPanelText
                        ></VExpansionPanel
                    ></VExpansionPanels
                ></VCardText
            ><VCardActions class="pa-5"
                ><VBtn :disabled="form.processing" @click="emit('update:modelValue', false)">Cancelar</VBtn><VSpacer /><VBtn
                    color="primary"
                    variant="flat"
                    :loading="form.processing"
                    @click="save"
                    >Guardar comidas</VBtn
                ></VCardActions
            ></VCard
        ></VDialog
    >
</template>
<style scoped>
.meal-day {
    padding: 16px 0;
    border-bottom: 1px solid var(--sl-border);
}
.meal-times {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-top: 12px;
}
@media (max-width: 600px) {
    .meal-times {
        grid-template-columns: 1fr;
        gap: 4px;
    }
}
</style>

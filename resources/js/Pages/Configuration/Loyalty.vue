<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import ConfigurationLayout from '../../Layouts/ConfigurationLayout.vue';
import LoyaltyRedemptions from '../../Components/LoyaltyRedemptions.vue';
import { redemptionLabels } from '../../utils/loyalty';
import { usePermissions } from '../../composables/usePermissions';
const props = defineProps<{ rewards: any[]; levels: any[]; redemptions: any; services: any[]; status: string }>();
const { can } = usePermissions();
const tab = ref('rewards'),
    search = ref(''),
    open = ref(false),
    selected = ref<number | null>(null);
const visible = computed(() =>
    props.rewards.filter((r) => r.name.toLocaleLowerCase('es').includes(search.value.toLocaleLowerCase('es'))),
);
const form = useForm({
    name: '',
    description: '',
    points_cost: 100,
    benefit_type: 'gift',
    service_id: null as number | null,
    discount_percent: null as number | null,
    stock: null as number | null,
    per_customer_limit: 1,
    birthday_only: false,
    is_active: true,
    starts_on: '',
    ends_on: '',
});
const levels = useForm({ levels: props.levels.map((l) => ({ ...l })) });
function edit(r?: any) {
    form.reset();
    form.clearErrors();
    selected.value = r?.id ?? null;
    if (r) Object.keys(form.data()).forEach((k) => ((form as any)[k] = r[k] ?? (k.endsWith('_on') ? '' : null)));
    open.value = true;
}
function save() {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    selected.value
        ? form.put(`/configuration/loyalty/rewards/${selected.value}`, options)
        : form.post('/configuration/loyalty/rewards', options);
}
function filter(status: string, page = 1) {
    router.get(
        '/configuration/loyalty/rewards',
        { status, canjes: page },
        { preserveState: true, preserveScroll: true },
    );
}
</script>
<template>
    <Head title="Fidelidad y recompensas" /><ConfigurationLayout>
        <header class="loyalty-header">
            <div>
                <p class="text-overline">Programa Lemus</p>
                <h1>Fidelidad y recompensas</h1>
                <p>Cuida cada visita. Consulta perfiles y acompaña cada canje hasta la entrega.</p>
            </div>
            <VBtn href="/configuration/loyalty/customers" color="primary" prepend-icon="mdi-account-search-outline"
                >Ver clientas</VBtn
            >
        </header>
        <VTabs v-model="tab" class="my-6"
            ><VTab value="rewards">Recompensas</VTab><VTab value="redemptions">Historial de canjes</VTab
            ><VTab value="levels">Niveles</VTab></VTabs
        >
        <section v-if="tab === 'rewards'">
            <div class="toolbar">
                <VTextField
                    v-model="search"
                    label="Buscar recompensa"
                    prepend-inner-icon="mdi-magnify"
                    clearable
                /><VBtn color="primary" @click="edit()">Nueva recompensa</VBtn>
            </div>
            <div class="reward-grid">
                <VCard v-for="r in visible" :key="r.id" variant="outlined" class="pa-6"
                    ><div class="d-flex justify-space-between ga-3">
                        <VIcon icon="mdi-gift-outline" color="primary" /><VChip
                            size="small"
                            :color="r.is_active ? 'success' : 'default'"
                            >{{ r.is_active ? 'Activa' : 'Inactiva' }}</VChip
                        >
                    </div>
                    <h2 class="mt-5">{{ r.name }}</h2>
                    <p>{{ r.description || 'Sin descripción' }}</p>
                    <strong class="reward-cost">{{ r.points_cost }} <small>puntos</small></strong>
                    <p>
                        {{ r.stock === null ? 'Sin límite de existencias' : r.stock + ' disponibles' }} · Máximo
                        {{ r.per_customer_limit }} por clienta
                    </p>
                    <p v-if="r.birthday_only" class="mt-2">Solo en mes de cumpleaños</p>
                    <VBtn class="mt-5" variant="tonal" @click="edit(r)">Editar recompensa</VBtn></VCard
                >
            </div>
            <p v-if="!visible.length" class="pa-6">No hay recompensas que coincidan. Crea una o cambia la búsqueda.</p>
        </section>
        <section v-if="tab === 'redemptions'">
            <h2>Todos los canjes</h2>
            <p class="mb-4">
                Reservas creadas por clientas o por el personal. Los beneficios se entregan y confirman por separado del
                cobro.
            </p>
            <VSelect
                :model-value="status"
                label="Estado del canje"
                :items="[
                    { title: 'Todos los estados', value: '' },
                    ...Object.entries(redemptionLabels).map(([value, title]) => ({ title, value })),
                ]"
                @update:model-value="filter"
            /><LoyaltyRedemptions :items="redemptions.data" /><VPagination
                v-if="redemptions.last_page > 1"
                :length="redemptions.last_page"
                :model-value="redemptions.current_page"
                @update:model-value="filter(status, $event)"
            />
        </section>
        <section v-if="tab === 'levels'">
            <h2>Niveles de fidelidad</h2>
            <p class="mb-5">Los puntos calificables determinan el nivel. Canjear puntos no hace perder el nivel.</p>
            <VForm @submit.prevent="levels.put('/configuration/loyalty/settings', { preserveScroll: true })"
                ><VAlert v-if="levels.hasErrors" type="error">{{ Object.values(levels.errors).join(' ') }}</VAlert
                ><VCard v-for="(l, i) in levels.levels" :key="i" variant="outlined" class="pa-5 mb-4"
                    ><VTextField v-model="l.name" label="Nombre del nivel" /><VTextField
                        v-model.number="l.points"
                        type="number"
                        min="0"
                        label="Puntos calificables mínimos" /><VTextarea
                        v-model="l.benefits"
                        label="Beneficios"
                        rows="2" /></VCard
                ><VBtn v-if="can('loyalty.adjust')" type="submit" :loading="levels.processing" color="primary"
                    >Guardar niveles</VBtn
                ></VForm
            >
        </section>
        <VDialog v-model="open" max-width="700" :persistent="form.processing"
            ><VCard
                ><VCardTitle class="pa-5">{{ selected ? 'Editar recompensa' : 'Nueva recompensa' }}</VCardTitle
                ><VCardText
                    ><VForm id="reward-form" @submit.prevent="save"
                        ><VAlert v-if="form.hasErrors" type="error" class="mb-4">{{
                            Object.values(form.errors).join(' ')
                        }}</VAlert
                        ><VTextField v-model="form.name" label="Nombre" :error-messages="form.errors.name" /><VTextarea
                            v-model="form.description"
                            label="Descripción del beneficio"
                            rows="2" /><VTextField
                            v-model.number="form.points_cost"
                            label="Costo en puntos"
                            type="number"
                            min="1" /><VSelect
                            v-model="form.benefit_type"
                            label="Tipo de beneficio"
                            :items="[
                                { title: 'Regalo', value: 'gift' },
                                { title: 'Servicio gratuito', value: 'service' },
                                { title: 'Descuento', value: 'discount' },
                            ]" /><VAutocomplete
                            v-if="form.benefit_type === 'service'"
                            v-model="form.service_id"
                            :items="services"
                            item-title="name"
                            item-value="id"
                            label="Servicio incluido" /><VTextField
                            v-if="form.benefit_type === 'discount'"
                            v-model.number="form.discount_percent"
                            label="Porcentaje de descuento"
                            type="number"
                            min="0.01"
                            max="100" /><VAlert type="info" variant="tonal" class="mb-4"
                            >La confirmación registra la entrega. No altera facturas ya cobradas; aplica el beneficio en
                            caja cuando corresponda.</VAlert
                        ><VTextField
                            v-model.number="form.stock"
                            label="Existencias disponibles (vacío = ilimitadas)"
                            type="number"
                            min="0"
                            clearable /><VTextField
                            v-model.number="form.per_customer_limit"
                            label="Máximo de canjes por clienta"
                            type="number"
                            min="1" /><VTextField
                            v-model="form.starts_on"
                            type="date"
                            label="Disponible desde" /><VTextField
                            v-model="form.ends_on"
                            type="date"
                            label="Disponible hasta" /><VCheckbox
                            v-model="form.birthday_only"
                            label="Solo en mes de cumpleaños" /><VSwitch
                            v-model="form.is_active"
                            label="Recompensa activa"
                            color="primary" /></VForm></VCardText
                ><VCardActions class="pa-5"
                    ><VBtn :disabled="form.processing" @click="open = false">Cancelar</VBtn><VSpacer /><VBtn
                        form="reward-form"
                        type="submit"
                        color="primary"
                        :loading="form.processing"
                        >Guardar recompensa</VBtn
                    ></VCardActions
                ></VCard
            ></VDialog
        >
    </ConfigurationLayout>
</template>
<style scoped>
.loyalty-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}
.loyalty-header p {
    max-width: 65ch;
}
.toolbar {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 24px;
}
.toolbar .v-input {
    flex: 1;
}
.reward-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr));
    gap: 24px;
}
.reward-cost {
    display: block;
    font-size: 32px;
    margin: 24px 0 12px;
}
.reward-cost small {
    font-size: 14px;
    font-weight: 400;
}
h2 {
    font-size: 22px;
    margin-bottom: 12px;
}
@media (max-width: 600px) {
    .toolbar {
        flex-direction: column;
    }
    .toolbar .v-input {
        width: 100%;
    }
}
</style>

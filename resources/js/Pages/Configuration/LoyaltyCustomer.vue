<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import ConfigurationLayout from '../../Layouts/ConfigurationLayout.vue';
import LoyaltyRedemptions from '../../Components/LoyaltyRedemptions.vue';
import { usePermissions } from '../../composables/usePermissions';
import { loyaltyDate } from '../../utils/loyalty';
const props = defineProps<{
    customer: any;
    balance: number;
    qualifying: number;
    visits: number;
    entries: any;
    redemptions: any;
    rewards: any[];
    appointments: any[];
}>();
const { can } = usePermissions();
const tab = ref('redemptions'),
    reward = ref<any>(null),
    adjustOpen = ref(false);
const newToken = () => crypto.randomUUID();
const reserve = useForm({ operation_token: crypto.randomUUID() });
const adjustment = useForm({ points: 0, note: '', operation_token: crypto.randomUUID() });
function select(r: any) {
    reward.value = r;
    reserve.operation_token = crypto.randomUUID();
    reserve.clearErrors();
}
function paginate(key: string, page: number) {
    router.get(
        `/configuration/loyalty/customers/${props.customer.id}`,
        { [key]: page },
        { preserveState: true, preserveScroll: true },
    );
}
</script>
<template>
    <Head :title="customer.name" /><ConfigurationLayout>
        <VBtn href="/configuration/loyalty/customers" variant="text" prepend-icon="mdi-arrow-left" class="mb-4"
            >Todas las clientas</VBtn
        >
        <header class="profile-header">
            <div>
                <h1>{{ customer.name }}</h1>
                <p>{{ customer.email }} · {{ customer.phone || 'Sin teléfono' }}</p>
                <VChip class="mt-3" :color="customer.is_active && customer.email_verified_at ? 'success' : 'warning'">{{
                    !customer.is_active
                        ? 'Cuenta inactiva'
                        : customer.email_verified_at
                          ? 'Cuenta verificada'
                          : 'Correo no verificado'
                }}</VChip>
            </div>
            <VBtn v-if="can('loyalty.adjust')" variant="tonal" @click="adjustOpen = true">Ajustar puntos</VBtn>
        </header>
        <div class="metrics">
            <section>
                <span>Saldo disponible</span><strong>{{ balance }} pts</strong>
            </section>
            <section>
                <span>Puntos para nivel</span><strong>{{ qualifying }}</strong>
            </section>
            <section>
                <span>Visitas completadas</span><strong>{{ visits }}</strong>
            </section>
        </div>
        <VAlert v-if="balance < 0" type="warning" class="mb-4"
            >La cuenta tiene saldo negativo. Revisa las anulaciones y ajustes antes de un nuevo canje.</VAlert
        >
        <VTabs v-model="tab" class="mb-5"
            ><VTab value="redemptions">Canjes</VTab><VTab value="rewards">Canjear recompensa</VTab
            ><VTab value="entries">Movimientos</VTab><VTab value="appointments">Citas</VTab></VTabs
        >
        <section v-if="tab === 'redemptions'">
            <h2>Historial de canjes</h2>
            <LoyaltyRedemptions :items="redemptions.data" /><VPagination
                v-if="redemptions.last_page > 1"
                :length="redemptions.last_page"
                :model-value="redemptions.current_page"
                @update:model-value="paginate('redemptions', $event)"
            />
        </section>
        <section v-if="tab === 'rewards'">
            <h2>Reservar en nombre de {{ customer.name }}</h2>
            <p class="mb-5">
                Los puntos se descuentan al reservar. Confirma después la entrega desde la pestaña Canjes.
            </p>
            <VAlert v-if="!customer.email_verified_at || !customer.is_active" type="warning"
                >La cuenta debe estar activa y verificada para canjear.</VAlert
            >
            <div class="reward-list">
                <VCard v-for="r in rewards" :key="r.id" variant="outlined" class="pa-5"
                    ><h3>{{ r.name }}</h3>
                    <p>{{ r.description }}</p>
                    <p class="my-3">
                        <strong>{{ r.points_cost }} puntos</strong> ·
                        {{ r.stock === null ? 'Sin límite de existencias' : r.stock + ' disponibles' }}
                    </p>
                    <VBtn
                        v-if="can('loyalty.redeem')"
                        color="primary"
                        variant="tonal"
                        :disabled="
                            balance < r.points_cost ||
                            !customer.is_active ||
                            !customer.email_verified_at ||
                            r.stock === 0
                        "
                        @click="select(r)"
                        >Reservar para clienta</VBtn
                    ></VCard
                >
            </div>
            <p v-if="!rewards.length">No hay recompensas activas.</p>
        </section>
        <section v-if="tab === 'entries'">
            <h2>Movimientos de puntos</h2>
            <article v-for="e in entries.data" :key="e.id" class="entry">
                <div>
                    <strong>{{ e.note || e.type }}</strong>
                    <p>{{ loyaltyDate(e.created_at) }}</p>
                </div>
                <strong>{{ e.points > 0 ? '+' : '' }}{{ e.points }} pts</strong>
            </article>
            <p v-if="!entries.data.length">Sin movimientos todavía.</p>
            <VPagination
                v-if="entries.last_page > 1"
                :length="entries.last_page"
                :model-value="entries.current_page"
                @update:model-value="paginate('movements', $event)"
            />
        </section>
        <section v-if="tab === 'appointments'">
            <h2>Citas vinculadas</h2>
            <article v-for="a in appointments" :key="a.id" class="entry">
                <span>#{{ a.id }} · {{ loyaltyDate(a.scheduled_start) }}</span
                ><span>{{ a.status }}</span>
            </article>
            <p v-if="!appointments.length">No hay citas vinculadas con identificación verificada.</p>
        </section>
        <VDialog
            :model-value="!!reward"
            max-width="480"
            :persistent="reserve.processing"
            @update:model-value="!reserve.processing && (reward = null)"
            ><VCard class="pa-5"
                ><h2>Confirmar reserva</h2>
                <p class="my-4">{{ reward?.name }} para {{ customer.name }}</p>
                <p>Se descontarán {{ reward?.points_cost }} puntos. La reserva vence en 24 horas.</p>
                <VAlert v-if="reserve.hasErrors" type="error">{{ Object.values(reserve.errors).join(' ') }}</VAlert
                ><VCardActions
                    ><VBtn :disabled="reserve.processing" @click="reward = null">Volver</VBtn
                    ><VBtn
                        color="primary"
                        :loading="reserve.processing"
                        @click="
                            reserve.post(
                                `/configuration/loyalty/customers/${customer.id}/rewards/${reward.id}/reserve`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        reward = null;
                                        tab = 'redemptions';
                                    },
                                },
                            )
                        "
                        >Confirmar reserva</VBtn
                    ></VCardActions
                ></VCard
            ></VDialog
        >
        <VDialog v-model="adjustOpen" max-width="480" :persistent="adjustment.processing"
            ><VCard class="pa-5"
                ><h2>Ajuste extraordinario</h2>
                <p>Se registra un movimiento permanente, no se modifica el historial.</p>
                <VForm
                    @submit.prevent="
                        adjustment.post(`/loyalty/customers/${customer.id}/adjustments`, {
                            preserveScroll: true,
                            onSuccess: () => {
                                adjustOpen = false;
                                adjustment.reset();
                                adjustment.operation_token = newToken();
                            },
                        })
                    "
                    ><VTextField
                        v-model.number="adjustment.points"
                        type="number"
                        label="Puntos (positivo o negativo)"
                    /><VTextarea v-model="adjustment.note" label="Motivo obligatorio" /><VAlert
                        v-if="adjustment.hasErrors"
                        type="error"
                        >{{ Object.values(adjustment.errors).join(' ') }}</VAlert
                    ><VCardActions
                        ><VBtn :disabled="adjustment.processing" @click="adjustOpen = false">Cancelar</VBtn
                        ><VBtn type="submit" :loading="adjustment.processing">Registrar ajuste</VBtn></VCardActions
                    ></VForm
                ></VCard
            ></VDialog
        >
    </ConfigurationLayout>
</template>
<style scoped>
.profile-header,
.entry {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}
.profile-header p {
    overflow-wrap: anywhere;
}
.metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24px;
    margin: 32px 0;
}
.metrics section {
    padding: 24px;
    background: var(--sl-surface-soft);
    border-radius: 12px;
}
.metrics strong {
    display: block;
    font-size: var(--sl-metric-size);
}
.reward-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
    gap: 20px;
}
.entry {
    padding: 24px 0;
    border-bottom: 1px solid var(--sl-border);
}
@media (max-width: 600px) {
    .metrics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }
    .metrics section {
        padding: 12px 8px;
    }
    .metrics span {
        font-size: var(--sl-label-size);
    }
    .metrics strong {
        font-size: var(--sl-metric-size);
    }
}
</style>

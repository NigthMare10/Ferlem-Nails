<script setup lang="ts">
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { usePermissions } from '../composables/usePermissions';
import { redemptionLabels, redemptionColors, loyaltyDate } from '../utils/loyalty';
defineProps<{ items: any[] }>();
const { can } = usePermissions();
const pending = ref<any>(null),
    action = ref('confirm');
const form = useForm({});
function submit() {
    form.post(`/loyalty/redemptions/${pending.value.id}/${action.value}`, {
        preserveScroll: true,
        onSuccess: () => (pending.value = null),
    });
}
</script>
<template>
    <p v-if="!items.length" class="pa-6 text-medium-emphasis">No hay canjes para mostrar.</p>
    <article v-for="r in items" :key="r.id" class="redemption-row">
        <div>
            <div class="d-flex align-center flex-wrap ga-2">
                <strong>#{{ r.id }} · {{ r.reward_snapshot.name }}</strong
                ><VChip size="small" :color="redemptionColors[r.status]">{{ redemptionLabels[r.status] }}</VChip>
            </div>
            <Link v-if="r.customer" :href="`/configuration/loyalty/customers/${r.customer_id}`">{{
                r.customer.name
            }}</Link>
            <p>{{ r.points_cost }} puntos · {{ loyaltyDate(r.created_at) }}</p>
            <small v-if="r.status === 'reserved'">Vence {{ loyaltyDate(r.expires_at) }}</small>
            <small v-if="r.reserved_by" class="d-block">Reservó: {{ r.reserved_by.name }}</small
            ><small v-if="r.confirmed_by" class="d-block">Entregó: {{ r.confirmed_by.name }}</small>
        </div>
        <div v-if="r.status === 'reserved' && can('loyalty.redeem')" class="d-flex flex-wrap ga-2">
            <VBtn
                size="small"
                color="primary"
                variant="tonal"
                @click="
                    pending = r;
                    action = 'confirm';
                    form.clearErrors();
                "
                >Confirmar entrega</VBtn
            ><VBtn
                size="small"
                variant="text"
                @click="
                    pending = r;
                    action = 'cancel';
                    form.clearErrors();
                "
                >Cancelar reserva</VBtn
            >
        </div>
    </article>
    <VDialog
        :model-value="!!pending"
        max-width="480"
        :persistent="form.processing"
        @update:model-value="!form.processing && (pending = null)"
        ><VCard class="pa-5"
            ><h2>{{ action === 'confirm' ? 'Confirmar entrega' : 'Cancelar reserva' }}</h2>
            <p class="my-4">{{ pending?.reward_snapshot.name }} · {{ pending?.points_cost }} puntos</p>
            <p>
                {{
                    action === 'confirm'
                        ? 'Confirma solo si la clienta recibió el beneficio. Esta acción no modifica una venta ni puede cancelarse como reserva.'
                        : 'Se devolverán los puntos y se liberará la existencia reservada.'
                }}
            </p>
            <VAlert v-if="form.hasErrors" type="error">{{ Object.values(form.errors).join(' ') }}</VAlert
            ><VCardActions
                ><VBtn :disabled="form.processing" @click="pending = null">Volver</VBtn><VSpacer /><VBtn
                    color="primary"
                    :loading="form.processing"
                    @click="submit"
                    >Confirmar</VBtn
                ></VCardActions
            ></VCard
        ></VDialog
    >
</template>
<style scoped>
.redemption-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 24px 0;
    border-bottom: 1px solid var(--sl-border);
}
.redemption-row p {
    margin-top: 8px;
}
.redemption-row a {
    color: var(--sl-primary);
}
@media (max-width: 700px) {
    .redemption-row {
        align-items: stretch;
        flex-direction: column;
    }
}
</style>

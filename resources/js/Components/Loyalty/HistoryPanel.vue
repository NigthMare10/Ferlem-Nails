<script setup lang="ts">
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import Modal from './LoyaltyModal.vue';
import { redemptionLabels, loyaltyDate } from '../../utils/loyalty';
import { usePermissions } from '../../composables/usePermissions';
const props = defineProps<{ redemptions: any; filters: any }>();
const { can } = usePermissions();
const search = ref(props.filters.history_search ?? ''),
    status = ref(props.filters.status ?? ''),
    detail = ref<any>(null),
    action = ref('');
const form = useForm({ note: '', verified: false });
function filter() {
    router.get(
        '/configuration/loyalty/rewards',
        { tab: 'history', history_search: search.value, status: status.value },
        { preserveState: true, preserveScroll: true },
    );
}
function choose(r: any) {
    detail.value = r;
    action.value = '';
    form.reset();
    form.clearErrors();
}
function submit() {
    form.post(`/loyalty/redemptions/${detail.value.id}/${action.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            action.value = '';
            detail.value = null;
        },
    });
}
</script>
<template>
    <section>
        <h2>Historial de canjes</h2>
        <p class="muted">Los puntos se descuentan al reservar. Si vence o se cancela la reserva, se devuelven una sola vez.</p>
        <form class="toolbar" @submit.prevent="filter">
            <input v-model="search" type="search" aria-label="Buscar canje" placeholder="Nombre, correo o código" /><select
                v-model="status"
                aria-label="Estado del canje"
                @change="filter"
            >
                <option value="">Todos los estados</option>
                <option value="reserved">Pendientes</option>
                <option value="scheduled">Cita agendada</option>
                <option value="confirmed">Entregados</option>
                <option value="cancelled">Cancelados</option>
                <option value="expired">Vencidos</option></select
            ><button>Buscar</button
            ><button
                type="button"
                @click="
                    search = '';
                    status = '';
                    filter();
                "
            >
                Limpiar filtros
            </button>
        </form>
        <table class="ledger">
            <thead>
                <tr>
                    <th>Canje / servicio</th>
                    <th>Clienta</th>
                    <th>Puntos</th>
                    <th>Vigencia</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in redemptions.data" :key="r.id">
                    <td data-label="Canje / servicio">
                        <strong>#{{ r.id }} · {{ r.reward_snapshot.name }}</strong>
                    </td>
                    <td data-label="Clienta">
                        {{ r.customer?.name }}<small>{{ r.customer?.email }}</small>
                    </td>
                    <td data-label="Puntos">{{ r.points_cost }} pts<small v-if="['expired', 'cancelled'].includes(r.status)">Reintegrados</small></td>
                    <td data-label="Vigencia">
                        {{ loyaltyDate(r.appointment?.scheduled_start || r.expires_at)
                        }}<small>{{ r.appointment ? 'Fecha de la cita' : 'Reserva de 24 horas' }}</small>
                    </td>
                    <td data-label="Estado">
                        <span class="badge" :class="{ good: r.status === 'confirmed' }">{{ redemptionLabels[r.status] }}</span>
                    </td>
                    <td data-label="Acción"><button @click="choose(r)">Ver detalle</button></td>
                </tr>
            </tbody>
        </table>
        <p v-if="!redemptions.data.length" class="empty">No hay canjes con estos filtros.</p>
        <nav class="pagination" aria-label="Páginas de canjes">
            <Link v-if="redemptions.prev_page_url" class="button" :href="redemptions.prev_page_url">Anterior</Link
            ><span>{{ redemptions.current_page }} / {{ redemptions.last_page }}</span
            ><Link v-if="redemptions.next_page_url" class="button" :href="redemptions.next_page_url">Siguiente</Link>
        </nav>
        <Modal :open="!!detail" title="Detalle de canje" drawer :busy="form.processing" @close="detail = null">
            <template v-if="detail"
                ><h3>{{ detail.reward_snapshot.name }}</h3>
                <p>{{ detail.customer?.name }} · {{ detail.customer?.email }}</p>
                <span class="badge">{{ redemptionLabels[detail.status] }}</span>
                <dl class="detail-list">
                    <dt>Código</dt>
                    <dd>#{{ detail.id }}</dd>
                    <dt>Puntos al reservar</dt>
                    <dd>{{ detail.points_cost }}</dd>
                    <dt>Reservado</dt>
                    <dd>{{ loyaltyDate(detail.created_at) }}</dd>
                    <dt>Vence</dt>
                    <dd>{{ loyaltyDate(detail.expires_at) }} (Honduras)</dd>
                    <dt>Reservado por</dt>
                    <dd>{{ detail.reserved_by?.name ?? 'La clienta' }}</dd>
                    <dt>Entrega</dt>
                    <dd>{{ detail.confirmed_by?.name ?? 'Sin entrega confirmada' }}</dd>
                </dl>
                <p v-if="detail.resolved_at">Resuelto: {{ loyaltyDate(detail.resolved_at) }} (Honduras)</p>
                <p v-if="detail.resolution_note">Motivo: {{ detail.resolution_note }}</p>
                <p class="notice">
                    {{
                        detail.status === 'reserved'
                            ? 'La entrega no vuelve a descontar puntos.'
                            : detail.status === 'confirmed'
                              ? 'Entrega confirmada. No se puede cancelar como reserva.'
                              : 'Los puntos ya fueron restituidos. No se puede confirmar una entrega vencida o cancelada.'
                    }}
                </p></template
            >
            <template #footer
                ><button @click="detail = null">Cerrar</button
                ><template v-if="detail?.status === 'reserved' && can('loyalty.redeem')"
                    ><button
                        @click="
                            action = 'cancel';
                            form.clearErrors();
                        "
                    >
                        Cancelar reserva</button
                    ><button
                        class="primary"
                        @click="
                            action = 'confirm';
                            form.clearErrors();
                        "
                    >
                        Confirmar entrega
                    </button></template
                ></template
            >
        </Modal>
        <Modal :open="!!action" :title="action === 'confirm' ? 'Confirmar entrega' : 'Cancelar reserva'" :busy="form.processing" @close="action = ''">
            <form id="redemption-confirm" @submit.prevent="submit">
                <p>{{ detail?.reward_snapshot.name }} para {{ detail?.customer?.name }}.</p>
                <template v-if="action === 'confirm'"
                    ><p>Los puntos ya se descontaron al reservar.</p>
                    <label class="check"
                        ><input v-model="form.verified" type="checkbox" required />Verifiqué a la clienta y la entrega del servicio.</label
                    ></template
                ><template v-else
                    ><p>Se devolverán {{ detail?.points_cost }} puntos y se liberará el cupo retenido, si corresponde.</p>
                    <label>Motivo de cancelación<textarea v-model="form.note" required minlength="5" maxlength="500" /></label
                ></template>
                <p v-if="form.hasErrors" class="error" role="alert">{{ Object.values(form.errors).join(' ') }}</p>
            </form>
            <template #footer
                ><button :disabled="form.processing" @click="action = ''">Volver</button
                ><button type="submit" form="redemption-confirm" class="primary" :disabled="form.processing">
                    {{ form.processing ? 'Procesando…' : action === 'confirm' ? 'Confirmar entrega' : 'Cancelar y devolver puntos' }}
                </button></template
            >
        </Modal>
    </section>
</template>

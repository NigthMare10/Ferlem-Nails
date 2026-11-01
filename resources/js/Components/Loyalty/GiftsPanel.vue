<script setup lang="ts">
import { computed, ref, watch, onUnmounted } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import Modal from './LoyaltyModal.vue';
import { usePermissions } from '../../composables/usePermissions';
import { loyaltyDate } from '../../utils/loyalty';
const props = defineProps<{ batches: any; services: any[]; filters: any }>();
const { can } = usePermissions();
const open = ref(false),
    discard = ref(false),
    step = ref(1),
    search = ref(''),
    serviceSearch = ref(''),
    error = ref(''),
    loading = ref(false);
const clients = ref<any>({ data: [], current_page: 1, last_page: 1 }),
    known = ref<Record<number, any>>({});
const detail = ref<any>(null),
    detailLoading = ref(false),
    delivery = ref<any>(null),
    deliveryAction = ref('confirmed');
let controller: AbortController | undefined;
onUnmounted(() => controller?.abort());
const form = useForm<any>({
    operation_token: crypto.randomUUID(),
    customer_ids: [],
    type: 'points',
    points: 200,
    service_id: null,
    reason: '',
    message: '',
    expires_at: '',
});
const confirm = ref(false),
    retry = useForm({}),
    resolve = useForm({ action: 'confirmed', verified: false, note: '' });
const chosen = computed(() => form.customer_ids.map((id: number) => known.value[id] ?? { id, name: `Clienta #${id}` }));
const service = computed(() => props.services.find((s) => s.id === Number(form.service_id)));
const options = computed(() =>
    props.services.filter((s) => s.is_active && s.name.toLocaleLowerCase('es').includes(serviceSearch.value.toLocaleLowerCase('es'))),
);
const labels: Record<string, string> = {
    pending: 'Pendiente',
    failed: 'No aplicado',
    credited: 'Puntos acreditados',
    available: 'Disponible para entrega',
    confirmed: 'Entregado',
    cancelled: 'Cancelado',
    expired: 'Vencido',
};
async function loadClients(page = 1) {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = '';
    try {
        const r = await fetch(`/configuration/loyalty/gift-customers?search=${encodeURIComponent(search.value)}&page=${page}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (!r.ok) throw Error();
        clients.value = await r.json();
        for (const c of clients.value.data) known.value[c.id] = c;
    } catch (e) {
        if ((e as Error).name !== 'AbortError') error.value = 'No pudimos cargar las clientas. Reintenta la búsqueda.';
    } finally {
        loading.value = false;
    }
}
async function selectAll() {
    loading.value = true;
    error.value = '';
    try {
        const r = await fetch(`/configuration/loyalty/gift-customers?search=${encodeURIComponent(search.value)}&all=1`, {
            headers: { Accept: 'application/json' },
        });
        if (!r.ok) throw Error();
        const data = await r.json();
        for (const c of data.customers) known.value[c.id] = c;
        form.customer_ids = [...new Set([...form.customer_ids, ...data.ids])];
    } catch {
        error.value = 'No se pudo seleccionar el filtro completo. Máximo 500 clientas por lote; refina la búsqueda.';
    } finally {
        loading.value = false;
    }
}
function start() {
    form.reset();
    form.operation_token = crypto.randomUUID();
    form.clearErrors();
    step.value = 1;
    confirm.value = false;
    search.value = '';
    known.value = {};
    open.value = true;
    loadClients();
}
function close() {
    if (!form.processing) discard.value = true;
}
function advance() {
    if (step.value === 1 && form.customer_ids.length > 0 && form.customer_ids.length <= 500) {
        step.value = 2;
        return;
    }
    if (step.value === 2) step.value = 3;
}
function send() {
    if (!confirm.value || form.processing) return;
    form.post('/configuration/loyalty/gifts', {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
        onNetworkError: () => {
            error.value = 'Conexión interrumpida. Reintenta sin cambiar el regalo; se conservará el identificador para no duplicarlo.';
            return false;
        },
    });
}
async function show(id: number) {
    detailLoading.value = true;
    error.value = '';
    try {
        const r = await fetch(`/configuration/loyalty/gifts/${id}`, { headers: { Accept: 'application/json' } });
        if (!r.ok) throw Error();
        detail.value = await r.json();
    } catch {
        error.value = 'No pudimos cargar el detalle del regalo. Vuelve a intentarlo.';
    } finally {
        detailLoading.value = false;
    }
}
watch(
    () => props.filters?.gift,
    (id) => {
        if (id) show(Number(id));
    },
    { immediate: true },
);
function chooseDelivery(gift: any, action: string) {
    delivery.value = gift;
    deliveryAction.value = action;
    resolve.reset();
    resolve.clearErrors();
    resolve.action = action;
}
function finish() {
    resolve.post(`/configuration/loyalty/gift-recipients/${delivery.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            delivery.value = null;
            show(detail.value.id);
        },
        onError: () => show(detail.value.id),
    });
}
</script>
<template>
    <section>
        <div class="toolbar">
            <div>
                <h2>Regalos para tus clientas</h2>
                <p class="muted">Puntos o servicios de cortesía, sin costo de canje.</p>
            </div>
            <button v-if="can('loyalty.adjust')" class="primary" @click="start">Crear regalo</button>
        </div>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <p v-if="detailLoading" role="status">Cargando detalle…</p>
        <div class="toolbar">
            <Link class="button" href="/configuration/loyalty/rewards?tab=gifts">Todos</Link
            ><Link class="button" href="/configuration/loyalty/rewards?tab=gifts&gift_type=points">Puntos</Link
            ><Link class="button" href="/configuration/loyalty/rewards?tab=gifts&gift_type=service">Servicios</Link>
        </div>
        <table class="ledger">
            <thead>
                <tr>
                    <th>Lote / fecha</th>
                    <th>Regalo / valor</th>
                    <th>Destinatarias / total</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="b in batches.data" :key="b.id">
                    <td data-label="Lote / fecha">
                        #RG-{{ b.id }}<small>{{ loyaltyDate(b.created_at) }}</small>
                    </td>
                    <td data-label="Regalo / valor">
                        {{ b.type === 'points' ? `${b.points} pts por clienta` : b.service_snapshot?.name }}<small v-if="b.type === 'service'">Sin costo</small>
                    </td>
                    <td data-label="Destinatarias / total">
                        {{ b.recipients_count }} clientas<small>{{
                            b.type === 'points' ? `${b.points * b.recipients_count} pts en total` : `${b.recipients_count} servicios`
                        }}</small>
                    </td>
                    <td data-label="Motivo">{{ b.reason }}</td>
                    <td data-label="Estado">
                        <span class="badge" :class="{ good: b.applied_count === b.recipients_count }">{{
                            b.applied_count === b.recipients_count ? 'Aplicado' : 'Parcial o pendiente'
                        }}</span
                        ><small>{{ b.applied_count }} de {{ b.recipients_count }}</small>
                    </td>
                    <td data-label="Acción"><button @click="show(b.id)">Ver detalle</button></td>
                </tr>
            </tbody>
        </table>
        <p v-if="!batches.data.length" class="empty">No hay regalos registrados. Crea uno para una o varias clientas.</p>
        <nav class="pagination" aria-label="Páginas de regalos">
            <Link v-if="batches.prev_page_url" class="button" :href="batches.prev_page_url">Anterior</Link
            ><span>{{ batches.current_page }} / {{ batches.last_page }}</span
            ><Link v-if="batches.next_page_url" class="button" :href="batches.next_page_url">Siguiente</Link>
        </nav>
        <Modal :open="open" title="Crear regalo" drawer :busy="form.processing" @close="close">
            <nav class="step-list" aria-label="Pasos del regalo">
                <strong>{{ step }} de 3</strong><span>Clientas → Regalo → Revisar</span>
            </nav>
            <form id="gift-wizard" @submit.prevent="step === 3 ? send() : advance()">
                <section v-if="step === 1">
                    <h3>Elige una o varias clientas</h3>
                    <div class="toolbar">
                        <input v-model="search" type="search" aria-label="Buscar destinatarias" placeholder="Nombre, correo o teléfono" /><button
                            type="button"
                            :disabled="loading"
                            @click="loadClients()"
                        >
                            Buscar clientas
                        </button>
                    </div>
                    <p v-if="loading" role="status">Cargando clientas…</p>
                    <div class="wrap-actions">
                        <button
                            type="button"
                            :disabled="loading"
                            @click="
                                form.customer_ids = [...new Set([...form.customer_ids, ...clients.data.filter((c: any) => c.is_active).map((c: any) => c.id)])]
                            "
                        >
                            Seleccionar esta página</button
                        ><button type="button" :disabled="loading" @click="selectAll">Seleccionar todas las del filtro</button
                        ><button type="button" @click="form.customer_ids = []">Quitar selección</button>
                    </div>
                    <label v-for="c in clients.data" :key="c.id" class="client-row"
                        ><input v-model="form.customer_ids" type="checkbox" :value="c.id" :disabled="!c.is_active" :aria-label="`Seleccionar ${c.name}`" />
                        <div>
                            <strong>{{ c.name }}</strong
                            ><small>{{ c.email }}</small
                            ><small v-if="!c.is_active">Cuenta inactiva</small>
                        </div></label
                    >
                    <p v-if="!clients.data.length && !loading">No hay clientas con esta búsqueda.</p>
                    <nav class="pagination" aria-label="Páginas de destinatarias">
                        <button type="button" :disabled="clients.current_page <= 1 || loading" @click="loadClients(clients.current_page - 1)">Anterior</button
                        ><span>{{ clients.current_page }} / {{ clients.last_page }}</span
                        ><button type="button" :disabled="clients.current_page >= clients.last_page || loading" @click="loadClients(clients.current_page + 1)">
                            Siguiente
                        </button>
                    </nav>
                    <p role="status">
                        <strong>{{ form.customer_ids.length }} clientas seleccionadas</strong> · Máximo 500 por lote.
                    </p>
                </section>
                <section v-if="step === 2">
                    <h3>¿Qué quieres regalar?</h3>
                    <label
                        >Tipo de regalo<select v-model="form.type">
                            <option value="points">Puntos</option>
                            <option value="service">Servicio existente</option>
                        </select></label
                    >
                    <label v-if="form.type === 'points'"
                        >Puntos por clienta<input v-model.number="form.points" type="number" min="1" max="100000" required /><small
                            >Aumentan el saldo disponible, no el nivel.</small
                        ></label
                    >
                    <template v-else
                        ><label>Buscar servicio<input v-model="serviceSearch" type="search" /></label
                        ><label
                            >Servicio a regalar<select v-model="form.service_id" required>
                                <option :value="null" disabled>Selecciona un servicio</option>
                                <option v-for="s in options" :key="s.id" :value="s.id">{{ s.name }} · L {{ s.price }} · {{ s.duration_minutes }} min</option>
                            </select></label
                        >
                        <p class="notice">1 servicio por clienta. Este regalo no cobra ni genera puntos por compra.</p>
                        <label
                            >Vence el (hora de Honduras, opcional)<input v-model="form.expires_at" type="datetime-local" /><small
                                >Déjalo vacío para un vale sin vencimiento.</small
                            ></label
                        ></template
                    >
                    <label>Motivo del regalo<textarea v-model="form.reason" required minlength="5" maxlength="500" /></label
                    ><label
                        >Mensaje para la clienta (opcional)<textarea v-model="form.message" maxlength="500" /><small
                            >Se verá en su portal. No envía mensajes externos.</small
                        ></label
                    >
                </section>
                <section v-if="step === 3">
                    <h3>Revisa antes de enviar</h3>
                    <p>
                        <strong>{{ form.type === 'points' ? `${form.points} pts por clienta` : service?.name }}</strong>
                    </p>
                    <p>
                        Total: {{ form.type === 'points' ? `${form.points * form.customer_ids.length} pts` : `${form.customer_ids.length} servicios` }} para
                        {{ form.customer_ids.length }} clientas.
                    </p>
                    <p>{{ form.reason }}</p>
                    <p v-if="form.message">Mensaje: {{ form.message }}</p>
                    <p v-if="form.type === 'service'">Vigencia (Honduras): {{ form.expires_at || 'Sin vencimiento' }}</p>
                    <ul class="timeline">
                        <li v-for="c in chosen" :key="c.id">
                            {{ c.name }}<small>{{ c.email }}</small>
                        </li>
                    </ul>
                    <label class="check"><input v-model="confirm" type="checkbox" required />Confirmo las clientas y el regalo.</label>
                    <div class="wrap-actions">
                        <button
                            type="button"
                            @click="
                                step = 1;
                                confirm = false;
                            "
                        >
                            Editar clientas</button
                        ><button
                            type="button"
                            @click="
                                step = 2;
                                confirm = false;
                            "
                        >
                            Editar regalo
                        </button>
                    </div>
                </section>
                <p v-if="error" class="error" role="alert">{{ error }}</p>
                <p v-if="form.hasErrors" class="error" role="alert">
                    {{ Object.values(form.errors).join(' ') }} Vuelve al paso correspondiente para corregirlo.
                </p>
            </form>
            <template #footer
                ><button :disabled="form.processing" @click="step > 1 ? step-- : close()">{{ step > 1 ? 'Atrás' : 'Cancelar' }}</button
                ><button
                    type="submit"
                    form="gift-wizard"
                    class="primary"
                    :disabled="form.processing || !form.customer_ids.length || form.customer_ids.length > 500 || (step === 3 && !confirm)"
                >
                    {{
                        form.processing
                            ? 'Enviando…'
                            : step === 3
                              ? `Enviar regalo a ${form.customer_ids.length} ${form.customer_ids.length === 1 ? 'clienta' : 'clientas'}`
                              : step === 2
                                ? 'Revisar regalo'
                                : 'Continuar'
                    }}
                </button></template
            >
        </Modal>
        <Modal :open="discard" title="¿Descartar el regalo?" @close="discard = false"
            ><p>Se perderá la selección y el regalo no guardado.</p>
            <template #footer
                ><button @click="discard = false">Seguir editando</button
                ><button
                    class="primary"
                    @click="
                        discard = false;
                        open = false;
                        form.reset();
                    "
                >
                    Descartar
                </button></template
            ></Modal
        >
        <Modal :open="!!detail" title="Resultado y detalle del regalo" drawer :busy="retry.processing" @close="detail = null"
            ><template v-if="detail"
                ><h3>Lote #RG-{{ detail.id }}</h3>
                <p>{{ detail.type === 'points' ? `${detail.points} puntos por clienta` : detail.service_snapshot?.name }}</p>
                <p>{{ detail.reason }}</p>
                <p class="muted">Enviado por {{ detail.actor?.name }} · {{ loyaltyDate(detail.created_at) }}</p>
                <p v-if="detail.expires_at">Vence: {{ loyaltyDate(detail.expires_at) }} (Honduras)</p>
                <p class="notice">
                    {{ detail.recipients.filter((r: any) => !['pending', 'failed'].includes(r.status)).length }} de {{ detail.recipients.length }} aplicados.
                    Reintentar solo procesa los pendientes, sin duplicar los anteriores.
                </p>
                <article v-for="r in detail.recipients" :key="r.id" class="client-row">
                    <div>
                        <strong>{{ r.customer?.name }} · #RG-{{ r.id }}</strong
                        ><small>{{ r.customer?.email }}</small
                        ><span class="badge">{{ labels[r.status] }}</span>
                        <p v-if="r.error" class="error">{{ r.error }}</p>
                        <small v-if="r.applied_at">Aplicado: {{ loyaltyDate(r.applied_at) }}</small
                        ><small v-if="r.resolved_at">Resuelto: {{ loyaltyDate(r.resolved_at) }}</small
                        ><small v-if="r.resolution_note">{{ r.resolution_note }}</small>
                        <div v-if="r.status === 'available' && can('loyalty.redeem')" class="wrap-actions">
                            <button @click="chooseDelivery(r, 'confirmed')">Confirmar entrega</button
                            ><button @click="chooseDelivery(r, 'cancelled')">Cancelar regalo</button>
                        </div>
                    </div>
                </article>
                <p v-if="retry.hasErrors" class="error" role="alert">{{ Object.values(retry.errors).join(' ') }}</p></template
            ><template #footer
                ><button @click="detail = null">Cerrar</button
                ><button
                    v-if="detail?.recipients.some((r: any) => ['pending', 'failed'].includes(r.status)) && can('loyalty.adjust')"
                    class="primary"
                    :disabled="retry.processing"
                    @click="retry.post(`/configuration/loyalty/gifts/${detail.id}/retry`, { preserveScroll: true, onSuccess: () => show(detail.id) })"
                >
                    {{ retry.processing ? 'Reintentando…' : 'Reintentar pendientes' }}
                </button></template
            ></Modal
        >
        <Modal
            :open="!!delivery"
            :title="deliveryAction === 'confirmed' ? 'Entregar servicio de regalo' : 'Cancelar regalo disponible'"
            :busy="resolve.processing"
            @close="delivery = null"
            ><form id="gift-resolve" @submit.prevent="finish">
                <p>{{ detail?.service_snapshot?.name }} para {{ delivery?.customer?.name }}.</p>
                <p class="notice">Sin puntos cobrados ni generados por compra. No altera una venta ni la caja.</p>
                <label v-if="deliveryAction === 'confirmed'" class="check"
                    ><input v-model="resolve.verified" type="checkbox" required />Verifiqué a la clienta y la entrega del servicio.</label
                ><label v-else>Motivo de cancelación<textarea v-model="resolve.note" required minlength="5" maxlength="500" /></label>
                <p v-if="resolve.hasErrors" class="error" role="alert">{{ Object.values(resolve.errors).join(' ') }}</p>
            </form>
            <template #footer
                ><button :disabled="resolve.processing" @click="delivery = null">Volver</button
                ><button type="submit" form="gift-resolve" class="primary" :disabled="resolve.processing">
                    {{ resolve.processing ? 'Procesando…' : 'Confirmar' }}
                </button></template
            ></Modal
        >
    </section>
</template>

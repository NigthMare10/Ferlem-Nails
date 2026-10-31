<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import Modal from './LoyaltyModal.vue';
const props = defineProps<{ rewards: any[]; services: any[] }>();
const search = ref(''),
    state = ref('all'),
    serviceSearch = ref('');
const selected = ref<number[]>([]),
    editing = ref(false),
    discard = ref(false),
    id = ref<number | null>(null);
const bulkOpen = ref(false);
const original = ref('');
const form = useForm<any>({
    service_id: null,
    name: '',
    description: '',
    points_cost: 500,
    benefit_type: 'service',
    discount_percent: null,
    stock: null,
    per_customer_limit: 1,
    birthday_only: false,
    is_active: true,
    starts_on: '',
    ends_on: '',
    version: 1,
});
const bulk = useForm<any>({ items: [], is_active: false });
const visible = computed(() =>
    props.rewards.filter(
        (r) =>
            (state.value === 'all' || r.is_active === (state.value === 'active')) &&
            `${r.name} ${r.service?.name ?? ''}`.toLocaleLowerCase('es').includes(search.value.toLocaleLowerCase('es')),
    ),
);
const choices = computed(() => props.services.filter((s) => s.name.toLocaleLowerCase('es').includes(serviceSearch.value.toLocaleLowerCase('es'))));
const service = computed(() => props.services.find((s) => s.id === Number(form.service_id)));
watch(
    () => props.rewards,
    () => {
        selected.value = [];
    },
);
function edit(reward?: any) {
    id.value = reward?.id ?? null;
    form.reset();
    form.clearErrors();
    serviceSearch.value = '';
    if (reward) Object.keys(form.data()).forEach((k) => (form[k] = reward[k] ?? (k.endsWith('_on') ? '' : null)));
    form.benefit_type = 'service';
    original.value = JSON.stringify(form.data());
    editing.value = true;
}
function close() {
    if (form.processing) return;
    if (JSON.stringify(form.data()) !== original.value) discard.value = true;
    else editing.value = false;
}
function save() {
    form.name = service.value?.name ?? '';
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
            form.reset();
        },
    };
    id.value ? form.put(`/configuration/loyalty/rewards/${id.value}`, options) : form.post('/configuration/loyalty/rewards', options);
}
function changeStatus(rows: any[], active: boolean) {
    bulk.items = rows.map((r) => ({ id: r.id, version: r.version }));
    bulk.is_active = active;
    bulk.clearErrors();
    bulkOpen.value = true;
}
</script>
<template>
    <section>
        <div class="notice">
            <strong>Convierte tus servicios en premios</strong>
            <p>Los puntos ganados se configuran en Servicios. Aquí defines cuánto cuesta canjear un premio.</p>
        </div>
        <div class="toolbar">
            <input v-model="search" type="search" placeholder="Buscar premio o servicio" aria-label="Buscar premio o servicio" /><select
                v-model="state"
                aria-label="Estado de premios"
            >
                <option value="all">Todos</option>
                <option value="active">Activos</option>
                <option value="paused">Pausados</option></select
            ><button class="primary" @click="edit()">Añadir premio</button>
        </div>
        <div v-if="selected.length" class="toolbar">
            <strong>{{ selected.length }} seleccionados</strong
            ><button
                @click="
                    changeStatus(
                        rewards.filter((r) => selected.includes(r.id)),
                        false,
                    )
                "
            >
                Pausar seleccionados</button
            ><button
                @click="
                    changeStatus(
                        rewards.filter((r) => selected.includes(r.id)),
                        true,
                    )
                "
            >
                Reactivar seleccionados</button
            ><button @click="selected = []">Quitar selección</button>
        </div>
        <table class="ledger">
            <thead>
                <tr>
                    <th style="width: 48px">
                        <input
                            type="checkbox"
                            aria-label="Seleccionar premios visibles"
                            :checked="visible.length > 0 && visible.every((r) => selected.includes(r.id))"
                            @change="selected = ($event.target as HTMLInputElement).checked ? visible.slice(0, 100).map((r) => r.id) : []"
                        />
                    </th>
                    <th>Premio / servicio</th>
                    <th>Costo de canje</th>
                    <th>Disponibilidad</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in visible" :key="r.id">
                    <td><input v-model="selected" type="checkbox" :value="r.id" :aria-label="`Seleccionar ${r.name}`" /></td>
                    <td data-label="Premio / servicio">
                        <strong>{{ r.service?.name ?? r.name }}</strong
                        ><small v-if="r.service">L {{ r.service.price }} · Gana {{ r.service.loyalty_points }} pts al pagar</small
                        ><small v-else>Premio anterior: vincula un servicio al editar</small>
                    </td>
                    <td data-label="Costo de canje">
                        <strong>{{ r.points_cost }} pts</strong>
                    </td>
                    <td data-label="Disponibilidad">
                        {{ r.stock === null ? 'Ilimitada' : `${r.stock} cupos disponibles` }}<small>Máx. {{ r.per_customer_limit }} por clienta (total)</small
                        ><small v-if="r.birthday_only">Solo mes de cumpleaños</small>
                    </td>
                    <td data-label="Estado">
                        <span class="badge" :class="{ good: r.is_active }">{{ r.is_active ? 'Activo' : 'Pausado' }}</span
                        ><small v-if="r.service && !r.service.is_active">Servicio inactivo</small>
                    </td>
                    <td data-label="Acciones">
                        <div class="actions">
                            <button :aria-label="`Editar ${r.name}`" @click="edit(r)">Editar</button
                            ><button @click="changeStatus([r], !r.is_active)">{{ r.is_active ? 'Pausar' : 'Reactivar' }}</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-if="!visible.length" class="empty">
            {{ rewards.length ? 'No hay coincidencias. Cambia la búsqueda o el filtro.' : 'Aún no hay premios. Añade uno desde tus servicios existentes.' }}
        </p>
        <p><Link href="/configuration/services">Ver servicios →</Link></p>
        <Modal :open="editing" drawer :title="id ? 'Editar premio vinculado' : 'Añadir premio'" :busy="form.processing" @close="close">
            <form id="reward-form" @submit.prevent="save">
                <p>Elige un servicio existente. Su precio y puntos por compra no se modifican aquí.</p>
                <label>Buscar servicio<input v-model="serviceSearch" type="search" /></label>
                <label
                    >Servicio existente<select v-model="form.service_id" required>
                        <option :value="null" disabled>Selecciona un servicio</option>
                        <option v-for="s in choices" :key="s.id" :value="s.id" :disabled="!s.is_active">
                            {{ s.name }} · L {{ s.price }} · {{ s.loyalty_points }} pts{{ !s.is_active ? ' · Inactivo' : '' }}
                        </option>
                    </select></label
                >
                <dl v-if="service" class="detail-list">
                    <dt>Duración</dt>
                    <dd>{{ service.duration_minutes }} min</dd>
                    <dt>Precio de venta</dt>
                    <dd>L {{ service.price }}</dd>
                    <dt>Gana al pagar</dt>
                    <dd>{{ service.loyalty_points }} pts por compra</dd>
                </dl>
                <label>Puntos necesarios para canjear<input v-model.number="form.points_cost" type="number" min="1" max="1000000" required /></label>
                <label>Descripción pública para clientas<textarea v-model="form.description" maxlength="2000" /></label>
                <label class="check"
                    ><input type="checkbox" :checked="form.stock === null" @change="form.stock = ($event.target as HTMLInputElement).checked ? null : 0" />Cupos
                    ilimitados</label
                >
                <label v-if="form.stock !== null"
                    >Cupos disponibles ahora<input v-model.number="form.stock" type="number" min="0" required /><small
                        >No incluye los cupos ya retenidos por reservas activas.</small
                    ></label
                >
                <label>Máximo por clienta (total)<input v-model.number="form.per_customer_limit" type="number" min="1" max="1000" required /></label>
                <div class="columns">
                    <label>Disponible desde<input v-model="form.starts_on" type="date" /></label
                    ><label>Disponible hasta<input v-model="form.ends_on" type="date" :min="form.starts_on || undefined" /></label>
                </div>
                <label class="check"><input v-model="form.birthday_only" type="checkbox" />Solo en el mes de cumpleaños</label
                ><label class="check"><input v-model="form.is_active" type="checkbox" />Premio activo</label>
                <div v-if="form.hasErrors" class="error" role="alert">{{ Object.values(form.errors).join(' ') }}</div>
            </form>
            <template #footer
                ><button :disabled="form.processing" @click="close">Cancelar</button
                ><button type="submit" form="reward-form" class="primary" :disabled="form.processing">
                    {{ form.processing ? 'Guardando…' : id ? 'Guardar cambios' : 'Guardar premio' }}
                </button></template
            >
        </Modal>
        <Modal :open="discard" title="¿Descartar cambios?" @close="discard = false"
            ><p>Los cambios no guardados se perderán.</p>
            <template #footer
                ><button @click="discard = false">Seguir editando</button
                ><button
                    class="primary"
                    @click="
                        discard = false;
                        editing = false;
                        form.reset();
                    "
                >
                    Descartar
                </button></template
            ></Modal
        >
        <Modal :open="bulkOpen" :title="bulk.is_active ? 'Reactivar premios' : '¿Pausar estos premios?'" :busy="bulk.processing" @close="bulkOpen = false"
            ><p>Se {{ bulk.is_active ? 'reactivarán' : 'pausarán' }} {{ bulk.items.length }} premios. Las reservas existentes y el historial se conservan.</p>
            <p>Si otro administrador cambió alguno, no se aplicará ningún cambio hasta que recargues la lista.</p>
            <p v-if="bulk.hasErrors" class="error" role="alert">{{ Object.values(bulk.errors).join(' ') }}</p>
            <template #footer
                ><button :disabled="bulk.processing" @click="bulkOpen = false">Volver</button
                ><button
                    class="primary"
                    :disabled="bulk.processing"
                    @click="
                        bulk.post('/configuration/loyalty/rewards/bulk-status', {
                            preserveScroll: true,
                            onSuccess: () => {
                                bulkOpen = false;
                                selected = [];
                            },
                        })
                    "
                >
                    {{ bulk.processing ? 'Actualizando…' : 'Confirmar cambios' }}
                </button></template
            ></Modal
        >
    </section>
</template>

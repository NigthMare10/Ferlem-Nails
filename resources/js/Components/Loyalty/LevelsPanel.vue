<script setup lang="ts">
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from './LoyaltyModal.vue';
const props = defineProps<{ levels: any[]; version: number }>();
const page = usePage();
const hasRole = (role: string) => ((page.props.auth as any)?.roles ?? []).includes(role);
const open = ref(false),
    review = ref(false),
    discard = ref(false),
    index = ref(-1);
const draft = ref({ name: '', points: 0, benefits: '' });
const editingVersion = ref(props.version);
const form = useForm({ levels: props.levels.map((l) => ({ ...l })), version: props.version });
function edit(i = -1) {
    index.value = i;
    editingVersion.value = props.version;
    draft.value = i < 0 ? { name: '', points: 100, benefits: '' } : { ...props.levels[i] };
    form.clearErrors();
    open.value = true;
    review.value = false;
}
function save() {
    form.levels = props.levels.map((l) => ({ ...l }));
    if (index.value < 0) form.levels.push({ ...draft.value });
    else form.levels[index.value] = { ...draft.value };
    form.version = editingVersion.value;
    form.put('/configuration/loyalty/settings', {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            review.value = false;
        },
    });
}
</script>
<template>
    <section>
        <div class="toolbar">
            <h2>Niveles de fidelidad</h2>
            <button v-if="hasRole('owner')" class="primary" @click="edit()">Añadir nivel</button>
        </div>
        <p class="notice">
            El nivel depende de puntos calificables. Gastar puntos o recibir puntos de regalo no cambia el nivel. Los beneficios son informativos; no aplican
            descuentos automáticos.
        </p>
        <table class="ledger">
            <thead>
                <tr>
                    <th>Nivel</th>
                    <th>Puntos calificables</th>
                    <th>Beneficios informativos</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(l, i) in levels" :key="l.name">
                    <td data-label="Nivel">
                        <strong>{{ l.name }}</strong>
                    </td>
                    <td data-label="Puntos calificables">Desde {{ l.points }}</td>
                    <td data-label="Beneficios">{{ l.benefits }}</td>
                    <td data-label="Acciones">
                        <button v-if="hasRole('owner')" :aria-label="`Editar nivel ${l.name}`" @click="edit(i)">Editar</button><span v-else>Solo lectura</span>
                    </td>
                </tr>
            </tbody>
        </table>
        <Modal :open="open" :title="index < 0 ? 'Añadir nivel' : 'Editar nivel'" :busy="form.processing" @close="discard = true">
            <form id="level-form" @submit.prevent="review = true">
                <label>Nombre del nivel<input v-model="draft.name" required maxlength="50" /></label
                ><label
                    >Puntos calificables mínimos<input
                        v-model.number="draft.points"
                        type="number"
                        :min="index === 0 ? 0 : 1"
                        max="10000000"
                        :readonly="index === 0"
                        required
                    /><small v-if="index === 0">El nivel inicial comienza siempre en cero.</small></label
                ><label>Beneficios informativos<textarea v-model="draft.benefits" required maxlength="1000" /></label>
            </form>
            <template #footer
                ><button @click="discard = true">Cancelar</button><button type="submit" form="level-form" class="primary">Revisar cambios</button></template
            >
        </Modal>
        <Modal :open="review" title="Confirmar cambios de nivel" :busy="form.processing" @close="review = false"
            ><p>
                <strong>{{ draft.name }}</strong
                >: desde {{ draft.points }} puntos calificables.
            </p>
            <p v-if="index >= 0">Antes: {{ levels[index].name }}, desde {{ levels[index].points }} puntos.</p>
            <p>{{ draft.benefits }}</p>
            <p class="notice">El nivel se recalcula con los puntos calificables. Los saldos disponibles y los canjes no se modifican.</p>
            <p v-if="form.hasErrors" class="error" role="alert">{{ Object.values(form.errors).join(' ') }}</p>
            <template #footer
                ><button :disabled="form.processing" @click="review = false">Volver</button
                ><button class="primary" :disabled="form.processing" @click="save">{{ form.processing ? 'Guardando…' : 'Guardar cambios' }}</button></template
            ></Modal
        >
        <Modal :open="discard" title="¿Descartar cambios?" @close="discard = false"
            ><p>Se perderán los cambios no guardados.</p>
            <template #footer
                ><button @click="discard = false">Seguir editando</button
                ><button
                    class="primary"
                    @click="
                        discard = false;
                        open = false;
                    "
                >
                    Descartar
                </button></template
            ></Modal
        >
    </section>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ConfigurationLayout from '../../Layouts/ConfigurationLayout.vue';
const props = defineProps<{ customers: any; search: string }>();
const search = ref(props.search);
function load(page = 1) {
    router.get('/configuration/loyalty/customers', { search: search.value, page }, { preserveState: true, preserveScroll: true });
}
</script>
<template>
    <Head title="Clientas Lemus" /><ConfigurationLayout>
        <div class="d-flex justify-space-between flex-wrap ga-4 mb-6">
            <div>
                <h1>Clientas Lemus</h1>
                <p>Perfiles, puntos y recompensas en un solo lugar.</p>
            </div>
            <VBtn href="/configuration/loyalty/rewards" variant="tonal">Recompensas y canjes</VBtn>
        </div>
        <VForm @submit.prevent="load()" class="d-flex ga-3"
            ><VTextField v-model="search" label="Buscar por nombre, correo o teléfono" clearable /><VBtn type="submit" color="primary">Buscar</VBtn></VForm
        >
        <p class="mb-4">{{ customers.total }} clientas</p>
        <VCard variant="outlined"
            ><Link v-for="c in customers.data" :key="c.id" :href="`/configuration/loyalty/customers/${c.id}`" class="customer-row"
                ><div>
                    <strong>{{ c.name }}</strong
                    ><span>{{ c.email }}</span
                    ><small>{{ c.is_active ? 'Cuenta activa' : 'Cuenta inactiva' }}</small>
                </div>
                <div>
                    <strong>{{ Number(c.balance ?? 0) }} puntos</strong><VIcon icon="mdi-chevron-right" /></div
            ></Link>
            <p v-if="!customers.data.length" class="pa-6">No encontramos clientas. Prueba otro nombre o correo.</p></VCard
        >
        <VPagination v-if="customers.last_page > 1" :model-value="customers.current_page" :length="customers.last_page" @update:model-value="load" />
    </ConfigurationLayout>
</template>
<style scoped>
.customer-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 24px;
    color: inherit;
    text-decoration: none;
    border-bottom: 1px solid var(--sl-border);
}
.customer-row:hover {
    background: var(--sl-surface-soft);
}
.customer-row span,
.customer-row small {
    display: block;
    overflow-wrap: anywhere;
}
.customer-row > div {
    min-width: 0;
}
@media (max-width: 500px) {
    .customer-row {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

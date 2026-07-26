<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import EmptyState from '../Components/EmptyState.vue';
import MetricCard from '../Components/MetricCard.vue';
import PageHeader from '../Components/PageHeader.vue';
import { usePermissions } from '../composables/usePermissions';

defineProps<{ metrics: { active_services?: number; active_users?: number } }>();
const page = usePage();
const { can, canAny } = usePermissions();
const userName = computed(() => (page.props.auth as any)?.user?.name ?? '');
</script>

<template>
    <Head title="Inicio" />
    <AppLayout title="Inicio">
        <PageHeader
            eyebrow="Panel principal"
            :title="`Hola, ${userName}`"
            description="Todo lo esencial de Studio Lemus, en un solo lugar."
        />

        <VRow v-if="metrics.active_services !== undefined || metrics.active_users !== undefined" class="mb-5">
            <VCol v-if="metrics.active_services !== undefined" cols="12" sm="6" lg="4">
                <MetricCard label="Servicios activos" :value="metrics.active_services" icon="mdi-hand-heart-outline" href="/configuration/services" />
            </VCol>
            <VCol v-if="metrics.active_users !== undefined" cols="12" sm="6" lg="4">
                <MetricCard label="Usuarios activos" :value="metrics.active_users" icon="mdi-account-check-outline" href="/configuration/users" />
            </VCol>
        </VRow>

        <VCard class="surface-card">
            <VCardText class="pa-6 pa-sm-8">
                <div v-if="can('services.view') || can('users.view') || canAny(['sales.view_own', 'sales.view_all'])">
                    <h2 class="text-h6 font-weight-bold mb-2">Accesos rápidos</h2>
                    <p class="text-body-2 text-medium-emphasis mb-5">Continúa con una de las opciones disponibles para tu cuenta.</p>
                    <div class="d-flex flex-wrap ga-3">
                        <VBtn v-if="can('sales.create')" color="primary" prepend-icon="mdi-receipt-text-plus-outline" @click="router.visit('/sales/new')">
                            Nueva venta
                        </VBtn>
                        <VBtn v-if="canAny(['sales.view_own', 'sales.view_all'])" color="primary" variant="tonal" prepend-icon="mdi-file-document-outline" @click="router.visit('/invoices')">
                            Ver facturas
                        </VBtn>
                        <VBtn v-if="can('services.view')" color="primary" variant="tonal" prepend-icon="mdi-hand-heart-outline" @click="router.visit('/configuration/services')">
                            Ver servicios
                        </VBtn>
                        <VBtn v-if="can('users.view')" color="primary" variant="tonal" prepend-icon="mdi-account-group-outline" @click="router.visit('/configuration/users')">
                            Ver usuarios
                        </VBtn>
                    </div>
                </div>
                <EmptyState
                    v-else
                    icon="mdi-home-heart"
                    title="Bienvenida a Studio Lemus"
                    description="Tu cuenta está lista. Las opciones disponibles aparecerán aquí según tus permisos."
                />
            </VCardText>
        </VCard>
    </AppLayout>
</template>

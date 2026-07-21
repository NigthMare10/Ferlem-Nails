<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import AppLayout from './AppLayout.vue';
import { usePermissions } from '../composables/usePermissions';

const { can } = usePermissions();
const { lgAndUp } = useDisplay();
const page = usePage();
const currentSection = computed(() => page.url.includes('/services') ? 'services' : 'users');
const items = computed(() => [
    can('users.view') ? { value: 'users', title: 'Usuarios', icon: 'mdi-account-group-outline', href: '/configuration/users' } : null,
    can('services.view') ? { value: 'services', title: 'Servicios', icon: 'mdi-hand-heart-outline', href: '/configuration/services' } : null,
].filter(Boolean) as Array<{ value: string; title: string; icon: string; href: string }>);

const navigate = (value: string) => {
    const item = items.value.find(option => option.value === value);
    if (item) router.visit(item.href);
};
</script>

<template>
    <AppLayout title="Configuración">
        <div class="mb-6">
            <div class="text-overline text-primary font-weight-bold mb-1">Studio Lemus</div>
            <h1 class="text-h4 font-weight-bold mb-2">Configuración</h1>
            <p class="text-body-1 text-medium-emphasis mb-0">Administra los accesos y servicios disponibles.</p>
        </div>

        <VTabs
            v-if="!lgAndUp"
            :model-value="currentSection"
            color="primary"
            grow
            show-arrows
            class="configuration-tabs mb-5 bg-surface rounded-lg"
            @update:model-value="navigate(String($event))"
        >
            <VTab v-for="item in items" :key="item.value" :value="item.value" :prepend-icon="item.icon">
                {{ item.title }}
            </VTab>
        </VTabs>

        <VRow>
            <VCol v-if="lgAndUp" cols="12" lg="3">
                <VCard class="configuration-nav pa-2" color="surface">
                    <VList nav density="comfortable">
                        <VListSubheader class="text-overline">Secciones</VListSubheader>
                        <VListItem
                            v-for="item in items"
                            :key="item.value"
                            :title="item.title"
                            :prepend-icon="item.icon"
                            :active="currentSection === item.value"
                            color="primary"
                            rounded="lg"
                            @click="navigate(item.value)"
                        />
                    </VList>
                </VCard>
            </VCol>
            <VCol cols="12" lg="9">
                <slot />
            </VCol>
        </VRow>
    </AppLayout>
</template>

<style scoped>
.configuration-nav :deep(.v-list-item-title),
.configuration-tabs :deep(.v-tab__content) {
    overflow: visible;
    text-overflow: clip;
    white-space: nowrap;
}
</style>

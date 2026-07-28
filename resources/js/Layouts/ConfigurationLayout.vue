<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from './AppLayout.vue';
import { usePermissions } from '../composables/usePermissions';

const { can } = usePermissions();
const page = usePage();
const currentSection = computed(() => page.url.includes('/business-hours') ? 'business-hours' : page.url.includes('/services') ? 'services' : 'users');
const items = computed(() => [
    can('users.view') ? { value: 'users', title: 'Usuarios', icon: 'mdi-account-group-outline', href: '/configuration/users' } : null,
    can('services.view') ? { value: 'services', title: 'Servicios', icon: 'mdi-hand-heart-outline', href: '/configuration/services' } : null,
    can('settings.business_hours.manage') ? { value: 'business-hours', title: 'Horario de atención', icon: 'mdi-clock-outline', href: '/configuration/business-hours' } : null,
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
            :model-value="currentSection"
            color="primary"
            grow
            show-arrows
            class="configuration-tabs mb-6"
            @update:model-value="navigate(String($event))"
        >
            <VTab v-for="item in items" :key="item.value" :value="item.value" :prepend-icon="item.icon">
                {{ item.title }}
            </VTab>
        </VTabs>

        <slot />
    </AppLayout>
</template>

<style scoped>
.configuration-tabs :deep(.v-tab__content) {
    overflow: visible;
    text-overflow: clip;
    white-space: nowrap;
}
.configuration-tabs { background: transparent; border-bottom: 1px solid var(--sl-border); }
.configuration-tabs :deep(.v-tab) { min-height: 44px; border-radius: var(--sl-radius-control) var(--sl-radius-control) 0 0; }
</style>

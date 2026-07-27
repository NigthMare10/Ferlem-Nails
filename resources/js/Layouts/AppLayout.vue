<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import { usePermissions } from '../composables/usePermissions';
import AppSnackbar from '../Components/AppSnackbar.vue';
import NotificationBell from '../Components/Notifications/NotificationBell.vue';
import StudioLemusBrand from '../Components/StudioLemusBrand.vue';
import UserMenu from '../Components/UserMenu.vue';

withDefaults(defineProps<{ title?: string }>(), { title: 'Inicio' });

const page = usePage();
const { mobile } = useDisplay();
const { can, canAny } = usePermissions();
const drawer = ref(!mobile.value);
const sidebarRail = ref(false);
const auth = computed(() => page.props.auth as any);
const currentUrl = computed(() => page.url);
const canNavigateToInvoices = () => canAny(['sales.view_own', 'sales.view_all']);

const navigationGroups = computed(() => [
    {
        title: 'Jornada',
        items: [
            { visible: auth.value?.navigation?.home, icon: 'mdi-home-outline', title: 'Inicio', href: '/', active: currentUrl.value === '/' },
            { visible: auth.value?.navigation?.sales, icon: 'mdi-receipt-text-plus-outline', title: 'Nueva venta', href: '/sales/new', active: currentUrl.value === '/sales/new' },
            { visible: auth.value?.navigation?.appointments, icon: 'mdi-calendar-clock-outline', title: 'Agenda', href: '/appointments', active: currentUrl.value.startsWith('/appointments') && !currentUrl.value.startsWith('/appointments/history') },
        ],
    },
    {
        title: 'Movimiento',
        items: [
            { visible: canNavigateToInvoices(), icon: 'mdi-file-document-outline', title: 'Facturas', href: '/invoices', active: currentUrl.value.startsWith('/invoices') },
            { visible: auth.value?.navigation?.appointments, icon: 'mdi-history', title: 'Historial de citas', href: '/appointments/history', active: currentUrl.value.startsWith('/appointments/history') },
            { visible: auth.value?.navigation?.expenses, icon: 'mdi-cash-minus', title: 'Gastos', href: '/expenses', active: currentUrl.value.startsWith('/expenses') },
            { visible: auth.value?.navigation?.earnings, icon: 'mdi-chart-box-outline', title: 'Ganancias generales', href: '/earnings', active: currentUrl.value.startsWith('/earnings') },
        ],
    },
    {
        title: 'El estudio',
        items: [
            { visible: can('settings.access') && canAny(['users.view', 'services.view']), icon: 'mdi-tune-variant', title: 'Configuración', href: '/configuration', active: currentUrl.value.startsWith('/configuration') },
        ],
    },
]);

watch(mobile, value => {
    drawer.value = !value;
    if (value) sidebarRail.value = false;
});

const navigate = (href: string) => {
    if (mobile.value) drawer.value = false;
    router.visit(href);
};

const toggleNavigation = () => {
    if (mobile.value) {
        drawer.value = !drawer.value;
        return;
    }

    sidebarRail.value = !sidebarRail.value;
};

const navigationLabel = computed(() => {
    if (mobile.value) return drawer.value ? 'Cerrar navegación' : 'Abrir navegación';
    return sidebarRail.value ? 'Expandir navegación' : 'Colapsar navegación';
});
</script>

<template>
    <VApp class="app-shell">
        <VNavigationDrawer
            v-model="drawer"
            :temporary="mobile"
            :rail="sidebarRail && !mobile"
            :width="272"
            :rail-width="76"
            class="app-sidebar"
            :scrim="mobile"
        >
            <div class="d-flex align-center justify-space-between px-4 py-4" :class="{ 'justify-center px-2': sidebarRail && !mobile }">
                <StudioLemusBrand :variant="sidebarRail && !mobile ? 'compact' : 'full'" tone="dark" />
                <VBtn
                    v-if="!mobile && !sidebarRail"
                    icon="mdi-chevron-left"
                    variant="text"
                    size="small"
                    aria-label="Colapsar navegación"
                    @click="sidebarRail = true"
                />
            </div>

                <div class="app-sidebar__rule" />

            <VList nav class="px-3 py-4" density="comfortable">
                <template v-for="group in navigationGroups" :key="group.title">
                    <VListSubheader v-if="group.items.some(item => item.visible) && !(sidebarRail && !mobile)" class="app-nav-label">
                        {{ group.title }}
                    </VListSubheader>
                    <template v-for="item in group.items" :key="item.href">
                        <VTooltip :disabled="!(sidebarRail && !mobile)" location="end" :text="item.title">
                            <template #activator="{ props: tooltipProps }">
                                <VListItem
                                    v-if="item.visible"
                                    v-bind="tooltipProps"
                                    :prepend-icon="item.icon"
                                    :title="sidebarRail && !mobile ? undefined : item.title"
                                    :aria-label="item.title"
                                    :active="item.active"
                                    rounded="lg"
                                    @click="navigate(item.href)"
                                />
                            </template>
                        </VTooltip>
                    </template>
                </template>
            </VList>

            <template #append>
                <div class="pa-3">
                    <div class="app-session" :class="{ 'app-session--rail': sidebarRail && !mobile }">
                        <VAvatar color="primary" size="32" aria-hidden="true">{{ auth?.user?.name?.slice(0, 1)?.toUpperCase() }}</VAvatar>
                        <div v-if="!(sidebarRail && !mobile)" class="min-width-0">
                            <div class="text-caption text-medium-emphasis">Sesión activa</div>
                            <div class="text-body-2 font-weight-bold text-truncate">{{ auth?.user?.name }}</div>
                        </div>
                    </div>
                </div>
            </template>
        </VNavigationDrawer>

        <VMain class="app-content">
            <a class="skip-link" href="#app-main">Saltar al contenido</a>
            <VContainer id="app-main" class="page-wrap pa-4 pa-sm-6 pa-lg-8" tabindex="-1">
                <header class="app-contextbar" aria-label="Contexto de página">
                    <VBtn icon="mdi-menu" variant="text" size="small" :aria-label="navigationLabel" :aria-expanded="mobile ? drawer : !sidebarRail" @click="toggleNavigation" />
                    <span class="app-contextbar__title">{{ title }}</span>
                    <div class="app-contextbar__actions"><NotificationBell /><UserMenu v-if="auth?.user" :user="auth.user" :role="auth.roles?.[0]" /></div>
                </header>
                <Transition name="sl-page" mode="out-in"><div :key="currentUrl"><slot /></div></Transition>
            </VContainer>
        </VMain>
        <AppSnackbar />
    </VApp>
</template>

<style scoped>
.app-nav-label { color: var(--sl-sidebar-muted); font-size: var(--sl-label-size); font-weight: 650; letter-spacing: 0.12em; text-transform: uppercase; }
.app-sidebar__rule { height: 1px; margin: 0 20px; background: var(--sl-border); }
.app-session { display: flex; align-items: center; gap: 10px; min-height: 52px; padding: 10px; border-radius: var(--sl-radius-compact); background: var(--sl-surface); box-shadow: var(--sl-shadow-inset); }
.app-session--rail { justify-content: center; padding: 8px; }
.app-contextbar { display: flex; align-items: center; min-height: 48px; margin-bottom: 24px; padding: 4px 6px 4px 4px; border: 1px solid var(--sl-glass-border); border-radius: var(--sl-radius-pill); background: var(--sl-glass); box-shadow: var(--sl-shadow-raised); backdrop-filter: blur(18px) saturate(130%); }
.app-contextbar__title { margin-left: 6px; font-size: 1rem; font-weight: 700; letter-spacing: -.01em; }
.app-contextbar__actions { display: flex; align-items: center; gap: 2px; margin-left: auto; }
.min-width-0 { min-width: 0; }
</style>

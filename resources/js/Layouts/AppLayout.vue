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
const auth = computed(() => page.props.auth as any);
const currentUrl = computed(() => page.url);

watch(mobile, value => { drawer.value = !value; });

const navigate = (href: string) => {
    router.visit(href);
    if (mobile.value) drawer.value = false;
};
</script>

<template>
    <VApp>
        <VNavigationDrawer
            v-model="drawer"
            :temporary="mobile"
            width="252"
            color="surface"
            class="app-sidebar"
        >
            <div class="px-5 py-5">
                <StudioLemusBrand />
            </div>
            <VDivider />
            <VList nav class="px-3 py-4" density="comfortable">
                <VListSubheader class="text-overline">Principal</VListSubheader>
                <VListItem
                    v-if="auth?.navigation?.home"
                    prepend-icon="mdi-home-outline"
                    title="Inicio"
                    :active="currentUrl === '/'"
                    color="primary"
                    rounded="lg"
                    @click="navigate('/')"
                />
                <VListItem
                    v-if="auth?.navigation?.sales"
                    prepend-icon="mdi-receipt-text-plus-outline"
                    title="Nueva venta"
                    :active="currentUrl === '/sales/new'"
                    color="primary"
                    rounded="lg"
                    @click="navigate('/sales/new')"
                />
                <VListItem
                    v-if="auth?.navigation?.appointments"
                    prepend-icon="mdi-calendar-clock-outline"
                    title="Agenda"
                    :active="currentUrl.startsWith('/appointments') && !currentUrl.startsWith('/appointments/history')"
                    color="primary"
                    rounded="lg"
                    @click="navigate('/appointments')"
                />
                <VListItem
                    v-if="auth?.navigation?.appointments"
                    prepend-icon="mdi-history"
                    title="Historial de citas"
                    :active="currentUrl.startsWith('/appointments/history')"
                    color="primary"
                    rounded="lg"
                    @click="navigate('/appointments/history')"
                />
                <VListItem
                    v-if="auth?.navigation?.earnings"
                    prepend-icon="mdi-chart-box-outline"
                    title="Ganancias Generales"
                    :active="currentUrl.startsWith('/earnings')"
                    color="primary"
                    rounded="lg"
                    @click="navigate('/earnings')"
                />
                <VListItem
                    v-if="can('settings.access') && canAny(['users.view', 'services.view'])"
                    prepend-icon="mdi-cog-outline"
                    title="Configuración"
                    :active="currentUrl.startsWith('/configuration')"
                    color="primary"
                    rounded="lg"
                    @click="navigate('/configuration')"
                />
            </VList>
            <template #append>
                <div class="pa-4">
                    <VCard color="surface-variant" class="pa-3" rounded="lg">
                        <div class="text-caption text-medium-emphasis">Sesión activa</div>
                        <div class="text-body-2 font-weight-bold text-truncate">{{ auth?.user?.name }}</div>
                    </VCard>
                </div>
            </template>
        </VNavigationDrawer>

        <VAppBar color="surface" elevation="0" border="b" height="64">
            <VAppBarNavIcon aria-label="Abrir navegación" @click="drawer = !drawer" />
            <VAppBarTitle class="font-weight-bold">{{ title }}</VAppBarTitle>
            <template #append>
                <NotificationBell class="mr-1" />
                <UserMenu
                    v-if="auth?.user"
                    :user="auth.user"
                    :role="auth.roles?.[0]"
                    class="mr-1 mr-sm-3"
                />
            </template>
        </VAppBar>

        <VMain class="app-content">
            <VContainer class="page-wrap pa-4 pa-sm-6 pa-lg-8">
                <slot />
            </VContainer>
        </VMain>
        <AppSnackbar />
    </VApp>
</template>

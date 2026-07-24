<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import NotificationListItem from './NotificationListItem.vue';
import type { AuthNotifications, NotificationItem } from '../../types/notifications';

const page = usePage();
const { mobile } = useDisplay();
const menuOpen = ref(false);
const polling = ref(false);
let pollTimer: ReturnType<typeof setInterval> | undefined;

const notifications = computed<AuthNotifications | undefined>(() => {
    const auth = page.props.auth as { notifications?: AuthNotifications } | undefined;
    return auth?.notifications;
});
const authorized = computed(() => notifications.value !== undefined);
const unreadLabel = computed(() => {
    const count = notifications.value?.unread_count ?? 0;
    return count > 99 ? '99+' : String(count);
});

function visitNotification(notification: NotificationItem): void {
    menuOpen.value = false;
    if (notification.read_at) {
        router.visit(notification.url);
        return;
    }

    router.patch(`/notifications/${encodeURIComponent(notification.id)}/read`, {}, {
        preserveScroll: true,
        onSuccess: () => router.visit(notification.url),
    });
}

function pollNotifications(): void {
    if (!authorized.value || document.hidden || polling.value) return;
    polling.value = true;
    router.reload({
        only: ['auth'],
        onFinish: () => { polling.value = false; },
    });
}

function stopPolling(): void {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = undefined;
}

function startPolling(): void {
    stopPolling();
    if (!authorized.value || document.hidden) return;
    pollTimer = setInterval(pollNotifications, 60_000);
}

function handleVisibilityChange(): void {
    if (document.hidden) {
        stopPolling();
        return;
    }
    pollNotifications();
    startPolling();
}

function openNotifications(): void {
    if (mobile.value) router.visit('/notifications');
}

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    startPolling();
});
watch(authorized, startPolling);
onBeforeUnmount(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <VMenu v-if="authorized && !mobile" v-model="menuOpen" location="bottom end" :close-on-content-click="false" offset="6">
        <template #activator="{ props: activatorProps }">
            <VBadge :model-value="(notifications?.unread_count ?? 0) > 0" :content="unreadLabel" color="primary" offset-x="5" offset-y="5">
                <VBtn v-bind="activatorProps" icon="mdi-bell-outline" variant="text" aria-label="Abrir notificaciones" />
            </VBadge>
        </template>

        <VCard class="notification-menu" width="390" max-width="calc(100vw - 24px)">
            <VCardItem class="px-4 py-3">
                <VCardTitle class="text-subtitle-1 font-weight-bold">Notificaciones</VCardTitle>
                <template #append><VChip v-if="notifications!.unread_count" color="primary" variant="tonal" size="small">{{ notifications!.unread_count }} nuevas</VChip></template>
            </VCardItem>
            <VDivider />
            <div class="notification-recents">
                <NotificationListItem
                    v-for="notification in notifications!.recent"
                    :key="notification.id"
                    :notification="notification"
                    compact
                    @open="visitNotification"
                />
                <div v-if="!notifications!.recent.length" class="pa-6 text-center text-body-2 text-medium-emphasis">No hay notificaciones recientes.</div>
            </div>
            <VDivider />
            <VCardActions class="pa-2">
                <VBtn block variant="text" color="primary" append-icon="mdi-arrow-right" href="/notifications" @click="menuOpen = false">Ver todas</VBtn>
            </VCardActions>
        </VCard>
    </VMenu>

    <VBadge v-else-if="authorized" :model-value="(notifications?.unread_count ?? 0) > 0" :content="unreadLabel" color="primary" offset-x="5" offset-y="5">
        <VBtn icon="mdi-bell-outline" variant="text" aria-label="Ver notificaciones" @click="openNotifications" />
    </VBadge>
</template>

<style scoped>
.notification-menu { overflow: hidden; border: 1px solid rgba(var(--v-theme-on-surface), .09); box-shadow: 0 18px 55px rgba(55, 38, 44, .16) !important; }
.notification-recents { max-height: min(440px, calc(100vh - 190px)); overflow-y: auto; }
</style>

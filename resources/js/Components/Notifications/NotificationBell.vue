<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import NotificationListItem from './NotificationListItem.vue';
import type { AuthNotifications, NotificationItem } from '../../types/notifications';
import { useNotifications } from '../../composables/useNotifications';

const page = usePage();
const { mobile } = useDisplay();
const menuOpen = ref(false);
const notificationPulse = ref(false);
const { initializeNotifications, loading, markRead, recent, refreshNotifications, unreadCount } = useNotifications();
let pollTimer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;

const notifications = computed<AuthNotifications | undefined>(() => {
    const auth = page.props.auth as { notifications?: AuthNotifications } | undefined;
    return auth?.notifications;
});
const authorized = computed(() => notifications.value !== undefined);
const unreadLabel = computed(() => {
    const count = unreadCount.value;
    return count > 99 ? '99+' : String(count);
});

async function visitNotification(notification: NotificationItem): Promise<void> {
    menuOpen.value = false;
    const read = await markRead(notification);
    if (read.url.startsWith('/') && !read.url.startsWith('//')) {
        router.visit(read.url);
    } else {
        router.visit('/notifications');
    }
}

function pollNotifications(): void {
    if (!authorized.value || document.hidden || loading.value) return;
    controller?.abort();
    controller = new AbortController();
    void refreshNotifications(controller.signal).catch(() => undefined).finally(schedulePolling);
}

function stopPolling(): void {
    if (pollTimer) clearTimeout(pollTimer);
    pollTimer = undefined;
}

function startPolling(): void {
    stopPolling();
    if (!authorized.value || document.hidden) return;
    schedulePolling();
}

function schedulePolling(): void {
    stopPolling();
    if (!authorized.value || document.hidden) return;
    pollTimer = setTimeout(pollNotifications, 60_000);
}

function handleVisibilityChange(): void {
    if (document.hidden) {
        stopPolling();
        controller?.abort();
        return;
    }
    pollNotifications();
    startPolling();
}

function openNotifications(): void {
    if (mobile.value) router.visit('/notifications');
}

onMounted(() => {
    initializeNotifications(notifications.value);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    startPolling();
    pollNotifications();
});
watch(notifications, initializeNotifications, { immediate: true });
watch(authorized, startPolling);
watch(unreadCount, () => {
    notificationPulse.value = true;
    window.setTimeout(() => { notificationPulse.value = false; }, 220);
});
onBeforeUnmount(() => {
    stopPolling();
    controller?.abort();
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <VMenu v-if="authorized && !mobile" v-model="menuOpen" location="bottom end" :close-on-content-click="false" offset="6">
        <template #activator="{ props: activatorProps }">
            <VBadge :model-value="unreadCount > 0" :content="unreadLabel" color="primary" offset-x="5" offset-y="5">
                <VBtn v-bind="activatorProps" icon="mdi-bell-outline" variant="text" aria-label="Abrir notificaciones" :class="{ 'notification-bell--feedback': notificationPulse }" />
            </VBadge>
        </template>

        <VCard class="notification-menu" width="390" max-width="calc(100vw - 24px)">
            <VCardItem class="px-4 py-3">
                <VCardTitle class="text-subtitle-1 font-weight-bold">Notificaciones</VCardTitle>
                <template #append><VChip v-if="unreadCount" color="primary" variant="tonal" size="small">{{ unreadCount }} nuevas</VChip></template>
            </VCardItem>
            <VDivider />
            <div class="notification-recents">
                <NotificationListItem
                    v-for="notification in recent"
                    :key="notification.id"
                    :notification="notification"
                    compact
                    @open="visitNotification"
                />
                <div v-if="!recent.length" class="pa-6 text-center text-body-2 text-medium-emphasis">No hay notificaciones recientes.</div>
            </div>
            <VDivider />
            <VCardActions class="pa-2">
                <VBtn block variant="text" color="primary" append-icon="mdi-arrow-right" @click="menuOpen = false; router.visit('/notifications')">Ver todas</VBtn>
            </VCardActions>
        </VCard>
    </VMenu>

    <VBadge v-else-if="authorized" :model-value="unreadCount > 0" :content="unreadLabel" color="primary" offset-x="5" offset-y="5">
        <VBtn icon="mdi-bell-outline" variant="text" aria-label="Ver notificaciones" :class="{ 'notification-bell--feedback': notificationPulse }" @click="openNotifications" />
    </VBadge>
</template>

<style scoped>
.notification-menu { overflow: hidden; background: var(--sl-glass-strong); border: 1px solid var(--sl-glass-border); box-shadow: var(--sl-shadow-overlay) !important; backdrop-filter: blur(20px); }
.notification-recents { max-height: min(440px, calc(100vh - 190px)); overflow-y: auto; }
.notification-bell--feedback { animation: notification-feedback 220ms var(--sl-ease); }
@keyframes notification-feedback { 50% { transform: translateY(-2px) scale(1.06); } }
@media (prefers-reduced-motion: reduce) { .notification-bell--feedback { animation: none; } }
</style>

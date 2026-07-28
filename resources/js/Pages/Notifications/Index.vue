<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import EmptyState from '../../Components/EmptyState.vue';
import NotificationListItem from '../../Components/Notifications/NotificationListItem.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import type { NotificationAuthContext, NotificationFilter, NotificationItem, NotificationsPage } from '../../types/notifications';
import { safeNotificationUrl, useNotifications } from '../../composables/useNotifications';

const props = withDefaults(
    defineProps<{
        notifications: NotificationsPage;
        filters?: { filter?: NotificationFilter };
    }>(),
    {
        filters: () => ({ filter: 'all' }),
    },
);

const loading = ref(false);
const page = usePage();
const {
    clearNotificationError,
    error,
    initializeNotifications,
    markAllRead: markAll,
    markRead: readNotification,
    markingAll,
    markingIds,
    unreadCount,
} = useNotifications();
const records = ref<NotificationItem[]>([...(props.notifications.data ?? [])]);
const total = ref(props.notifications.meta.total);
const auth = computed<NotificationAuthContext | undefined>(() => page.props.auth as NotificationAuthContext | undefined);
const activeFilter = computed<NotificationFilter>(() => (props.filters.filter === 'unread' ? 'unread' : 'all'));
const hasUnread = computed(() => {
    return auth.value?.notifications ? unreadCount.value > 0 : records.value.some((notification) => !notification.read_at);
});

function load(filter: NotificationFilter, page = 1): void {
    if (loading.value) return;
    loading.value = true;
    router.get(
        '/notifications',
        { filter, page },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}

async function markRead(notification: NotificationItem, visitAfter = false): Promise<void> {
    if (notification.read_at) {
        if (visitAfter) router.visit(safeNotificationUrl(notification.url));
        return;
    }

    try {
        const read = await readNotification(notification);
        if (activeFilter.value === 'unread') {
            records.value = records.value.filter((record) => record.id !== read.id);
            total.value = Math.max(0, total.value - 1);
        } else {
            records.value = records.value.map((record) => (record.id === read.id ? read : record));
        }
        if (visitAfter) router.visit(safeNotificationUrl(read.url));
    } catch {
        // Keep the unread item in place; the composable exposes the action error.
    }
}

async function markAllRead(): Promise<void> {
    if (markingAll.value || !hasUnread.value) return;
    try {
        const result = await markAll();
        if (!result) return;
        if (activeFilter.value === 'unread') {
            records.value = [];
            total.value = 0;
        } else {
            records.value = records.value.map((notification) => ({
                ...notification,
                read_at: notification.read_at ?? result.as_of,
            }));
        }
    } catch {
        // Keep local records unchanged; the composable exposes the action error.
    }
}

watch(
    () => props.notifications,
    (notifications) => {
        records.value = [...(notifications.data ?? [])];
        total.value = notifications.meta.total;
    },
);
watch([() => auth.value?.user?.id, () => auth.value?.notifications], ([userId, notifications]) => initializeNotifications(notifications, userId), {
    immediate: true,
});
</script>

<template>
    <Head title="Notificaciones" />
    <AppLayout title="Notificaciones">
        <PageHeader eyebrow="Actividad reciente" title="Notificaciones" description="Revisa novedades y accede directamente al registro relacionado.">
            <template #actions>
                <VBtn color="primary" variant="tonal" prepend-icon="mdi-check-all" :disabled="!hasUnread" :loading="markingAll" @click="markAllRead">
                    Marcar todas como leídas
                </VBtn>
            </template>
        </PageHeader>

        <VCard class="surface-card notifications-card" :class="{ 'notifications-loading': loading }">
            <VProgressLinear v-if="loading" indeterminate color="primary" aria-label="Cargando notificaciones" />
            <VAlert v-if="error" type="error" variant="tonal" density="compact" class="ma-4 mb-0" closable @click:close="clearNotificationError">
                {{ error }}
            </VAlert>
            <div class="notifications-toolbar">
                <VTabs :model-value="activeFilter" color="primary" density="comfortable" @update:model-value="load($event as NotificationFilter)">
                    <VTab value="all">Todas</VTab>
                    <VTab value="unread">No leídas</VTab>
                </VTabs>
                <span class="text-caption text-medium-emphasis" aria-live="polite">{{ total }} en total</span>
            </div>
            <VDivider />

            <section aria-label="Lista de notificaciones">
                <NotificationListItem
                    v-for="notification in records"
                    :key="notification.id"
                    :notification="notification"
                    :marking="markingIds.has(notification.id)"
                    @open="markRead($event, true)"
                    @read="markRead"
                />
                <EmptyState
                    v-if="!records.length"
                    icon="mdi-bell-sleep-outline"
                    :title="activeFilter === 'unread' ? 'No tienes notificaciones pendientes' : 'Aún no hay notificaciones'"
                    :description="
                        activeFilter === 'unread'
                            ? 'Todo está al día. Las nuevas notificaciones aparecerán aquí.'
                            : 'Cuando haya actividad relevante, podrás consultarla en este espacio.'
                    "
                />
            </section>

            <VDivider v-if="notifications.meta.last_page > 1 && total > 0" />
            <VPagination
                v-if="notifications.meta.last_page > 1 && total > 0"
                :model-value="notifications.meta.current_page"
                :length="notifications.meta.last_page"
                :disabled="loading"
                class="my-4 px-2"
                @update:model-value="load(activeFilter, $event)"
            />
        </VCard>
    </AppLayout>
</template>

<style scoped>
.notifications-card {
    min-width: 0;
    overflow: hidden;
}
.notifications-loading {
    pointer-events: none;
    opacity: 0.68;
    transition: opacity 160ms ease;
}
.notifications-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    min-width: 0;
    padding: 6px 20px 6px 8px;
}
@media (max-width: 600px) {
    .notifications-toolbar {
        align-items: stretch;
        flex-direction: column;
        gap: 0;
        padding: 4px 8px 10px;
    }
    .notifications-toolbar > span {
        padding: 0 12px;
    }
    :deep(.v-pagination__list) {
        flex-wrap: wrap;
    }
}
</style>

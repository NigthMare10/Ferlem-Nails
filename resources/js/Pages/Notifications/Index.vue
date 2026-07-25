<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import EmptyState from '../../Components/EmptyState.vue';
import NotificationListItem from '../../Components/Notifications/NotificationListItem.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import type { AuthNotifications, NotificationFilter, NotificationItem, NotificationsPage } from '../../types/notifications';
import { useNotifications } from '../../composables/useNotifications';

const props = withDefaults(defineProps<{
    notifications: NotificationsPage;
    filters?: { filter?: NotificationFilter };
}>(), {
    filters: () => ({ filter: 'all' }),
});

const loading = ref(false);
const page = usePage();
const { initializeNotifications, markAllRead: markAll, markRead: readNotification, markingAll, markingIds, unreadCount } = useNotifications();
const records = computed(() => props.notifications.data ?? []);
const activeFilter = computed<NotificationFilter>(() => props.filters.filter === 'unread' ? 'unread' : 'all');
const hasUnread = computed(() => {
    const auth = page.props.auth as { notifications?: AuthNotifications } | undefined;
    return auth?.notifications
        ? unreadCount.value > 0
        : records.value.some(notification => !notification.read_at);
});

function load(filter: NotificationFilter, page = 1): void {
    if (loading.value) return;
    loading.value = true;
    router.get('/notifications', { filter, page }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => { loading.value = false; },
    });
}

async function markRead(notification: NotificationItem, visitAfter = false): Promise<void> {
    if (notification.read_at) {
        if (visitAfter) router.visit(notification.url);
        return;
    }

    const read = await readNotification(notification);
    if (visitAfter) {
        router.visit(read.url.startsWith('/') && !read.url.startsWith('//') ? read.url : '/notifications');
        return;
    }

    load(activeFilter.value, props.notifications.meta.current_page);
}

async function markAllRead(): Promise<void> {
    if (markingAll.value || !hasUnread.value) return;
    await markAll();
    load(activeFilter.value, props.notifications.meta.current_page);
}

initializeNotifications((page.props.auth as { notifications?: AuthNotifications } | undefined)?.notifications);
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
            <div class="notifications-toolbar">
                <VTabs :model-value="activeFilter" color="primary" density="comfortable" @update:model-value="load($event as NotificationFilter)">
                    <VTab value="all">Todas</VTab>
                    <VTab value="unread">No leídas</VTab>
                </VTabs>
                <span class="text-caption text-medium-emphasis">{{ notifications.meta.total }} en total</span>
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
                    :description="activeFilter === 'unread' ? 'Todo está al día. Las nuevas notificaciones aparecerán aquí.' : 'Cuando haya actividad relevante, podrás consultarla en este espacio.'"
                />
            </section>

            <VDivider v-if="notifications.meta.last_page > 1" />
            <VPagination
                v-if="notifications.meta.last_page > 1"
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
.notifications-card { min-width: 0; overflow: hidden; }
.notifications-loading { pointer-events: none; opacity: .68; transition: opacity 160ms ease; }
.notifications-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-width: 0; padding: 6px 20px 6px 8px; }
@media (max-width: 600px) {
    .notifications-toolbar { align-items: stretch; flex-direction: column; gap: 0; padding: 4px 8px 10px; }
    .notifications-toolbar > span { padding: 0 12px; }
    :deep(.v-pagination__list) { flex-wrap: wrap; }
}
</style>

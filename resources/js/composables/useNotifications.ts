import { ref } from 'vue';
import type { AuthNotifications, NotificationItem, NotificationSnapshot } from '../types/notifications';

const unreadCount = ref(0);
const recent = ref<NotificationItem[]>([]);
const loading = ref(false);
const markingIds = ref(new Set<string>());
const markingAll = ref(false);

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function mergeRecent(items: NotificationItem[]): void {
    const merged = new Map(recent.value.map(item => [item.id, item]));
    items.forEach(item => merged.set(item.id, item));
    recent.value = [...merged.values()]
        .sort((left, right) => right.occurred_at.localeCompare(left.occurred_at) || right.id.localeCompare(left.id))
        .slice(0, 10);
}

function initializeNotifications(snapshot?: AuthNotifications): void {
    if (!snapshot) return;
    unreadCount.value = snapshot.unread_count;
    mergeRecent(snapshot.recent);
}

async function request<T>(url: string, method = 'GET', signal?: AbortSignal): Promise<T> {
    const response = await fetch(url, {
        method,
        signal,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(method === 'GET' ? {} : { 'X-CSRF-TOKEN': csrfToken() }),
        },
    });
    if (!response.ok) throw new Error(`Notification request failed: ${response.status}`);

    return response.json() as Promise<T>;
}

async function refreshNotifications(signal?: AbortSignal): Promise<void> {
    if (loading.value) return;
    loading.value = true;
    try {
        const response = await request<{ data: NotificationSnapshot }>('/notifications/recent', 'GET', signal);
        unreadCount.value = response.data.unread_count;
        mergeRecent(response.data.recent);
    } finally {
        loading.value = false;
    }
}

async function markRead(notification: NotificationItem): Promise<NotificationItem> {
    if (notification.read_at) return notification;
    if (markingIds.value.has(notification.id)) return notification;

    markingIds.value = new Set(markingIds.value).add(notification.id);
    try {
        const response = await request<{ data: { notification: NotificationItem; unread_count: number; changed: boolean } }>(
            `/notifications/${encodeURIComponent(notification.id)}/read`,
            'PATCH',
        );
        unreadCount.value = response.data.unread_count;
        mergeRecent([response.data.notification]);

        return response.data.notification;
    } finally {
        const next = new Set(markingIds.value);
        next.delete(notification.id);
        markingIds.value = next;
    }
}

async function markAllRead(): Promise<void> {
    if (markingAll.value || unreadCount.value === 0) return;
    markingAll.value = true;
    try {
        const response = await request<{ data: { unread_count: number } }>('/notifications/read-all', 'PATCH');
        unreadCount.value = response.data.unread_count;
        recent.value = recent.value.map(notification => ({ ...notification, read_at: notification.read_at ?? new Date().toISOString() }));
    } finally {
        markingAll.value = false;
    }
}

export function useNotifications() {
    return {
        unreadCount,
        recent,
        loading,
        markingIds,
        markingAll,
        initializeNotifications,
        refreshNotifications,
        markRead,
        markAllRead,
    };
}

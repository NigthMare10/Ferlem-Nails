import { ref } from 'vue';
import type { AuthNotifications, NotificationBulkReadResult, NotificationItem, NotificationSnapshot } from '../types/notifications';

const unreadCount = ref(0);
const recent = ref<NotificationItem[]>([]);
const loading = ref(false);
const markingIds = ref(new Set<string>());
const markingAll = ref(false);
const error = ref<string | null>(null);
let stateUserId: string | null = null;

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function mergeRecent(items: NotificationItem[]): void {
    const merged = new Map(recent.value.map((item) => [item.id, item]));
    items.forEach((item) => merged.set(item.id, item));
    recent.value = [...merged.values()]
        .sort((left, right) => right.occurred_at.localeCompare(left.occurred_at) || right.id.localeCompare(left.id))
        .slice(0, 10);
}

function resetNotifications(): void {
    unreadCount.value = 0;
    recent.value = [];
    loading.value = false;
    markingIds.value = new Set();
    markingAll.value = false;
    error.value = null;
}

function initializeNotifications(snapshot?: AuthNotifications, userId?: number | string): void {
    const nextUserId = userId === undefined ? null : String(userId);
    if (nextUserId !== stateUserId) {
        stateUserId = nextUserId;
        resetNotifications();
    }

    if (!snapshot) {
        resetNotifications();
        return;
    }
    unreadCount.value = snapshot.unread_count;
    recent.value = [...snapshot.recent];
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
    const requestUserId = stateUserId;
    loading.value = true;
    error.value = null;
    try {
        const response = await request<{ data: NotificationSnapshot }>('/notifications/recent', 'GET', signal);
        if (requestUserId !== stateUserId) return;
        unreadCount.value = response.data.unread_count;
        recent.value = response.data.recent;
    } catch (requestError) {
        if (requestUserId === stateUserId && !(requestError instanceof DOMException && requestError.name === 'AbortError')) {
            error.value = 'No se pudieron actualizar las notificaciones. Revisa tu conexión e inténtalo de nuevo.';
        }
        throw requestError;
    } finally {
        if (requestUserId === stateUserId) loading.value = false;
    }
}

async function markRead(notification: NotificationItem): Promise<NotificationItem> {
    if (notification.read_at) return notification;
    if (markingIds.value.has(notification.id)) return notification;

    const requestUserId = stateUserId;
    markingIds.value = new Set(markingIds.value).add(notification.id);
    error.value = null;
    try {
        const response = await request<{ data: { notification: NotificationItem; unread_count: number; changed: boolean } }>(
            `/notifications/${encodeURIComponent(notification.id)}/read`,
            'PATCH',
        );
        if (requestUserId !== stateUserId) return notification;
        unreadCount.value = response.data.unread_count;
        mergeRecent([response.data.notification]);

        return response.data.notification;
    } catch (requestError) {
        if (requestUserId === stateUserId) {
            error.value = 'No se pudo marcar la notificación como leída. Inténtalo de nuevo.';
        }
        throw requestError;
    } finally {
        if (requestUserId === stateUserId) {
            const next = new Set(markingIds.value);
            next.delete(notification.id);
            markingIds.value = next;
        }
    }
}

async function markAllRead(): Promise<NotificationBulkReadResult | null> {
    if (markingAll.value || unreadCount.value === 0) return null;
    const requestUserId = stateUserId;
    markingAll.value = true;
    error.value = null;
    try {
        const response = await request<{ data: NotificationBulkReadResult }>('/notifications/read-all', 'PATCH');
        if (requestUserId !== stateUserId) return null;
        unreadCount.value = response.data.unread_count;
        recent.value = recent.value.map((notification) => ({ ...notification, read_at: notification.read_at ?? response.data.as_of }));

        return response.data;
    } catch (requestError) {
        if (requestUserId === stateUserId) {
            error.value = 'No se pudieron marcar las notificaciones como leídas. Inténtalo de nuevo.';
        }
        throw requestError;
    } finally {
        if (requestUserId === stateUserId) markingAll.value = false;
    }
}

function clearNotificationError(): void {
    error.value = null;
}

export function safeNotificationUrl(url: string): string {
    return url.startsWith('/') && !url.startsWith('//') ? url : '/notifications';
}

export function useNotifications() {
    return {
        unreadCount,
        recent,
        loading,
        markingIds,
        markingAll,
        error,
        initializeNotifications,
        refreshNotifications,
        markRead,
        markAllRead,
        clearNotificationError,
    };
}

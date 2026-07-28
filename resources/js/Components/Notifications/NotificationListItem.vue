<script setup lang="ts">
import type { NotificationItem } from '../../types/notifications';
import { safeNotificationUrl } from '../../composables/useNotifications';

withDefaults(
    defineProps<{
        notification: NotificationItem;
        compact?: boolean;
        marking?: boolean;
    }>(),
    {
        compact: false,
        marking: false,
    },
);

defineEmits<{
    open: [notification: NotificationItem];
    read: [notification: NotificationItem];
}>();

function formatOccurredAt(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('es-HN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}
</script>

<template>
    <article
        class="notification-item"
        :class="{ 'notification-item--unread': !notification.read_at, 'notification-item--compact': compact }"
        :aria-busy="marking"
    >
        <a :href="safeNotificationUrl(notification.url)" class="notification-main" @click.prevent="$emit('open', notification)">
            <span class="notification-marker" :aria-label="notification.read_at ? 'Leída' : 'No leída'" />
            <span class="notification-copy">
                <strong class="notification-title">{{ notification.title }}</strong>
                <span class="notification-message">{{ notification.message }}</span>
                <time class="notification-time" :datetime="notification.occurred_at">{{ formatOccurredAt(notification.occurred_at) }}</time>
            </span>
            <VProgressCircular v-if="marking" indeterminate color="primary" size="18" width="2" aria-label="Marcando como leída" />
            <VIcon v-else icon="mdi-chevron-right" size="18" class="notification-chevron" />
        </a>
        <VBtn
            v-if="!compact && !notification.read_at"
            variant="text"
            size="small"
            color="primary"
            :loading="marking"
            class="notification-read"
            @click="$emit('read', notification)"
        >
            Marcar como leída
        </VBtn>
    </article>
</template>

<style scoped>
.notification-item {
    position: relative;
    display: flex;
    align-items: center;
    min-width: 0;
    border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}
.notification-item:last-child {
    border-bottom: 0;
}
.notification-item--unread {
    background: rgba(var(--v-theme-primary), 0.045);
}
.notification-main {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1 1 auto;
    min-width: 0;
    padding: 18px 20px;
    color: inherit;
    text-align: left;
    text-decoration: none;
    border: 0;
    background: transparent;
    cursor: pointer;
}
.notification-main:hover {
    background: rgba(var(--v-theme-on-surface), 0.035);
}
.notification-marker {
    flex: 0 0 auto;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: transparent;
}
.notification-item--unread .notification-marker {
    background: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 4px rgba(var(--v-theme-primary), 0.1);
}
.notification-copy {
    display: grid;
    flex: 1 1 auto;
    min-width: 0;
    gap: 3px;
}
.notification-title,
.notification-message {
    overflow-wrap: anywhere;
}
.notification-title {
    font-size: 1rem;
    line-height: 1.35;
}
.notification-message {
    color: rgba(var(--v-theme-on-surface), 0.7);
    font-size: 1rem;
    line-height: 1.45;
}
.notification-time {
    color: rgba(var(--v-theme-on-surface), 0.55);
    font-size: 0.75rem;
}
.notification-chevron {
    flex: 0 0 auto;
    color: rgba(var(--v-theme-on-surface), 0.45);
}
.notification-read {
    flex: 0 0 auto;
    margin-right: 14px;
}
.notification-item--compact .notification-main {
    padding: 13px 16px;
    gap: 10px;
}
.notification-item--compact .notification-message {
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}
@media (max-width: 600px) {
    .notification-item {
        align-items: stretch;
        flex-direction: column;
    }
    .notification-main {
        width: 100%;
        padding: 16px 14px;
    }
    .notification-read {
        align-self: flex-start;
        margin: -8px 0 8px 34px;
    }
}
</style>

<?php

namespace Tests\Feature;

use Tests\TestCase;

class NotificationFrontendStructureTest extends TestCase
{
    public function test_app_layout_exposes_the_notification_bell(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
        $bell = file_get_contents(resource_path('js/Components/Notifications/NotificationBell.vue'));

        $this->assertNotFalse($layout);
        $this->assertNotFalse($bell);
        $this->assertStringContainsString('<NotificationBell', $layout);
        $this->assertStringContainsString('auth.value?.notifications', $bell);
        $this->assertStringContainsString('refreshNotifications', $bell);
        $this->assertStringNotContainsString("only: ['auth']", $bell);
        $this->assertStringContainsString('60_000', $bell);
        $this->assertStringContainsString("document.addEventListener('visibilitychange'", $bell);
        $this->assertStringContainsString('stopPolling()', $bell);
        $this->assertStringContainsString('Marcar todo como leído', $bell);
        $this->assertStringContainsString('Ver todas', $bell);
        $this->assertStringContainsString('notification-recents', $bell);
        $this->assertStringContainsString('VProgressLinear', $bell);
        $this->assertStringContainsString('VAlert', $bell);
        $this->assertStringNotContainsString('WebSocket', $bell);
    }

    public function test_notification_frontend_uses_the_documented_contract_and_urls(): void
    {
        $types = file_get_contents(resource_path('js/types/notifications.ts'));
        $bell = file_get_contents(resource_path('js/Components/Notifications/NotificationBell.vue'));
        $page = file_get_contents(resource_path('js/Pages/Notifications/Index.vue'));

        $this->assertNotFalse($types);
        $this->assertNotFalse($bell);
        $this->assertNotFalse($page);
        foreach (['id:', 'type:', 'title:', 'message:', 'url:', 'occurred_at:', 'read_at:'] as $field) {
            $this->assertStringContainsString($field, $types);
        }
        $this->assertStringContainsString('unread_count: number', $types);
        $this->assertStringContainsString('recent: NotificationItem[]', $types);
        $composable = file_get_contents(resource_path('js/composables/useNotifications.ts'));
        $this->assertNotFalse($composable);
        $this->assertStringContainsString('/notifications/${encodeURIComponent(notification.id)}/read', $composable);
        $this->assertStringContainsString("'/notifications/read-all', 'PATCH'", $composable);
        $this->assertStringContainsString('safeNotificationUrl', $composable);
        $this->assertStringContainsString('stateUserId', $composable);
        $this->assertStringContainsString('fetch(url', $composable);
        $this->assertStringNotContainsString('router.patch', $bell);
        $this->assertStringNotContainsString('router.patch', $page);
        $this->assertStringContainsString('router.get(', $page);
        $this->assertStringContainsString("'/notifications'", $page);
    }

    public function test_notifications_page_has_filters_actions_empty_state_and_responsive_pagination(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Notifications/Index.vue'));

        $this->assertNotFalse($page);
        $this->assertStringContainsString('<VTab value="all">Todas</VTab>', $page);
        $this->assertStringContainsString('<VTab value="unread">No leídas</VTab>', $page);
        $this->assertStringContainsString('Marcar todas como leídas', $page);
        $this->assertStringContainsString('<NotificationListItem', $page);
        $this->assertStringContainsString('<EmptyState', $page);
        $this->assertStringContainsString('<VPagination', $page);
        $this->assertStringContainsString('overflow: hidden', $page);
        $this->assertStringContainsString('@media (max-width: 600px)', $page);
        $this->assertStringNotContainsString('load(activeFilter.value, props.notifications.meta.current_page)', $page);
    }
}

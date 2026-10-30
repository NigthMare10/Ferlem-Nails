export interface NotificationItem {
    id: string;
    type: string;
    title: string;
    message: string;
    url: string;
    occurred_at: string;
    read_at: string | null;
}

export interface AuthNotifications {
    unread_count: number;
    recent: NotificationItem[];
}

export interface NotificationAuthContext {
    user?: { id: number | string };
    notifications?: AuthNotifications;
}

export interface NotificationSnapshot extends AuthNotifications {
    as_of: string;
}

export interface NotificationBulkReadResult {
    updated_count: number;
    unread_count: number;
    as_of: string;
}

export interface NotificationsPage {
    data: NotificationItem[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
    };
}

export type NotificationFilter = 'all' | 'unread';

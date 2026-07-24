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

export interface NotificationsPage {
    data: NotificationItem[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
    };
}

export type NotificationFilter = 'all' | 'unread';

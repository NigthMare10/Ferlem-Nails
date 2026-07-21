export interface AppointmentService {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    default_duration_minutes: number;
    position: number;
    scheduled_start: string;
    scheduled_end: string;
    start_time: string;
    end_time: string;
    assigned_to: AppointmentAssignee;
    price: string;
}

export interface AppointmentAssignee {
    id: number;
    name: string;
}

export interface AppointmentItem {
    id: number;
    service_id: number | null;
    service_name: string;
    duration_minutes: number;
    unit_price: string;
    quantity: number;
    line_total: string;
    default_duration_minutes: number;
    position: number;
    scheduled_start: string;
    scheduled_end: string;
    start_time: string;
    end_time: string;
    assigned_to: AppointmentAssignee;
}

export interface Appointment {
    id: number;
    client_name: string;
    client_phone: string | null;
    status: 'scheduled' | 'completed' | 'canceled' | 'no_show';
    status_label: string;
    notes: string | null;
    is_shared: boolean;
    visible_start: string;
    visible_end: string;
    visible_start_time: string;
    visible_end_time: string;
    visible_duration_minutes: number;
    visible_total: string;
    can_reschedule: boolean;
    can_change_status: boolean;
    can_mark_no_show_now: boolean;
    status_reason: string | null;
    visible_items: AppointmentItem[];
}

export interface AppointmentEvent {
    id: number;
    type: 'created' | 'updated' | 'rescheduled' | 'canceled' | 'no_show';
    type_label: string;
    changes: Array<{
        label: string;
        previous: string;
        new: string;
    }>;
    performed_by?: AppointmentAssignee;
    occurred_at: string;
    occurred_at_display: string;
    notes: string | null;
}

export interface AppointmentDetails extends Appointment {
    date: string;
    created_by?: AppointmentAssignee;
    created_at: string;
    created_at_display: string;
    status_changed_at: string | null;
    status_changed_at_display: string | null;
    status_changed_by: AppointmentAssignee | null;
    events: AppointmentEvent[];
}

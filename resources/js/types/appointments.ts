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

export interface AppointmentActor {
    id?: number;
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
    can_cancel: boolean;
    can_change_status: boolean;
    can_mark_no_show_now: boolean;
    can_record_deposit: boolean;
    has_pending_deposit: boolean;
    can_resolve_deposit: boolean;
    can_checkout: boolean;
    operational_status: 'scheduled' | 'in_service' | 'pending_checkout' | 'completed' | 'canceled' | 'no_show';
    status_reason: string | null;
    visible_items: AppointmentItem[];
}

export interface AppointmentEvent {
    id: number;
    type: 'created' | 'updated' | 'rescheduled' | 'canceled' | 'no_show' | 'deposit_recorded' | 'deposit_resolved' | 'deposit_excess_refunded' | 'completed';
    type_label: string;
    changes: Array<{
        label: string;
        previous: string;
        new: string;
    }>;
    performed_by?: AppointmentActor;
    occurred_at: string;
    occurred_at_display: string;
    notes: string | null;
}

export interface AppointmentDeposit {
    id: number;
    amount: string;
    available_amount?: string;
    payment_method: 'cash' | 'card';
    payment_method_label: string;
    status: 'pending' | 'applied' | 'refunded' | 'partially_refunded' | 'retained';
    status_label: string;
    applied_amount: string;
    refunded_amount?: string;
    retained_amount?: string;
    estimated_balance: string;
    paid_at: string;
    paid_at_display: string;
    card_fee_rate?: string;
    card_fee_amount?: string;
    net_amount?: string;
    resolved_at?: string | null;
    resolved_at_display?: string | null;
    resolved_by?: AppointmentAssignee | null;
    resolution_notes?: string | null;
}

export interface AppointmentDetails extends Appointment {
    date: string;
    outside_business_hours: boolean;
    created_by?: AppointmentAssignee;
    created_at: string;
    created_at_display: string;
    status_changed_at: string | null;
    status_changed_at_display: string | null;
    status_changed_by: AppointmentActor | null;
    can_manage_deposit: boolean;
    can_resolve_deposit: boolean;
    deposit: AppointmentDeposit | null;
    completed_at: string | null;
    completed_at_display: string | null;
    linked_sale: null | { id: number; sale_number: string; total: string; receipt_url: string; can_view_receipt: boolean };
    events: AppointmentEvent[];
}

export interface AppointmentHistoryItem {
    id: number;
    client_name: string;
    status: Appointment['status'];
    status_label: string;
    date: string;
    date_display: string;
    start_time: string;
    end_time: string;
    visible_services: Array<{ name: string; duration_minutes: number; quantity: number }>;
    personnel?: string[];
    visible_total: string;
    deposit: null | {
        status: string;
        status_label: string;
        amount?: string;
        available_amount?: string;
    };
    linked_sale: null | { sale_number: string; total: string; receipt_url: string };
    completed_at_display: string | null;
}

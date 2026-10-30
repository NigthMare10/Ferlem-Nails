export type SaleService = {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    price: string;
    is_promotion?: boolean;
    loyalty_points?: number;
};

export type SaleCartItem = SaleService & {
    quantity: number;
    discount_type?: 'percent' | 'fixed' | null;
    discount_value?: string;
    discount_reason?: string;
};

export type PaymentMethod = 'cash' | 'card' | 'transfer';

export type SaleAdditionalCharge = {
    name: string;
    amount: string;
    performed_by: number | null;
};

export type AppointmentCheckoutContext = {
    id: number;
    client_name: string;
    client_phone: string | null;
    scheduled_start: string;
    scheduled_end: string;
    reserved_duration_minutes: number;
    reserved_total: string;
    pending_balance: string;
    can_assign: boolean;
    can_resolve_deposit: boolean;
    can_apply_discount: boolean;
    deposit: null | { id: number; amount: string; available_amount: string; payment_method: PaymentMethod; payment_method_label: string; card_fee_amount: string };
    items: Array<{
        appointment_item_id: number;
        service_id: number | null;
        name: string;
        description: string | null;
        duration_minutes: number;
        price: string;
        quantity: number;
        position: number;
        performed_by: { id: number; name: string };
        reserved: true;
    }>;
};

export type AppointmentSaleCartItem = {
    discount_type?: 'percent' | 'fixed' | null;
    discount_value?: string;
    discount_reason?: string;
    key: string;
    appointment_item_id: number | null;
    service_id: number | null;
    name: string;
    description: string | null;
    duration_minutes: number;
    price: string;
    quantity: number;
    performed_by: number;
    performer_name: string;
    reserved: boolean;
};

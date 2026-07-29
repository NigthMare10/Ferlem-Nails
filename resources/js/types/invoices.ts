export type InvoiceStatus = 'completed' | 'canceled';

export type InvoiceTransferPayment = {
    id: number;
    amount: string;
    proof_url: string | null;
    can_upload_proof: boolean;
};

export type InvoiceListItem = {
    id: number;
    sale_number: string;
    client_name: string;
    sold_at_display: string;
    sold_by: { id: number; name: string };
    total: string;
    status: InvoiceStatus;
    status_label: string;
    payment_method_label: string;
    proof_status: 'with_proof' | 'pending' | 'not_applicable';
    proof_status_label: string;
    show_url: string;
    receipt_url: string;
    can_cancel: boolean;
    transfer_payment: InvoiceTransferPayment | null;
};

export type InvoiceDetail = {
    id: number;
    sale_number: string;
    status: InvoiceStatus;
    status_label: string;
    client_name: string;
    sold_at_display: string;
    sold_by: { name: string };
    payment_method_label: string;
    total: string;
    subtotal: string;
    discount_amount: string;
    total_services: number;
    receipt_url: string;
    related_appointment: { label: string; url: string } | null;
    items: Array<{ service_name: string; quantity: number; unit_price: string; line_total: string; performed_by: string | null }>;
    additional_charges: Array<{ name: string; amount: string }>;
    payments: Array<{ id: number; type: string; type_label: string; method: 'cash' | 'card' | 'transfer'; method_label: string; amount: string; proof_status_label: string; proof_url: string | null; can_upload_proof: boolean }>;
    cancellation: { canceled_at_display: string; canceled_by: string | null; reason: string } | null;
    can_cancel: boolean;
};

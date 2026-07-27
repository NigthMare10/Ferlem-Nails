export type ExpenseStatus = 'recorded' | 'canceled';
export type ExpensePaymentMethod = 'cash' | 'card' | 'transfer';

export type ExpenseOption = { id: number; name: string; is_active?: boolean };
export type ExpenseCategory = ExpenseOption & { expenses_count?: number };
export type ExpenseListItem = {
    id: number;
    expense_number: string;
    expense_date: string;
    expense_date_display: string;
    category: ExpenseOption;
    description: string;
    amount: string;
    payment_method: ExpensePaymentMethod;
    payment_method_label: string;
    vendor: string | null;
    employee: ExpenseOption | null;
    recorded_by: ExpenseOption;
    status: ExpenseStatus;
    status_label: string;
    origin: 'manual' | 'payroll_automatic';
    origin_label: string;
    payroll: { installment: 'first' | 'second'; installment_label: string; scheduled_date_display: string } | null;
    has_attachment: boolean;
    show_url: string;
    attachment_url: string | null;
    can_edit: boolean;
    can_cancel: boolean;
};

export type ExpenseEvent = {
    id: number;
    type: 'created' | 'updated' | 'canceled';
    type_label: string;
    performed_by: string;
    occurred_at_display: string;
    notes: string | null;
    changes: Array<{ field: string; previous: string; current: string }>;
};

export type ExpenseDetail = ExpenseListItem & {
    notes: string | null;
    created_at_display: string;
    attachment: { original_name: string; mime: string; size: number; uploaded_at_display: string } | null;
    cancellation: { canceled_at_display: string; canceled_by: string; reason: string } | null;
    events: ExpenseEvent[];
};

export type ExpenseFilters = {
    section?: 'all' | 'payroll' | null;
    search?: string | null;
    date_from?: string | null;
    date_to?: string | null;
    category_id?: number | null;
    status?: ExpenseStatus | null;
    payment_method?: ExpensePaymentMethod | null;
    employee_id?: number | null;
    recorded_by?: number | null;
};

export type ExpensePage = {
    data: ExpenseListItem[];
    meta: { current_page: number; last_page: number; total: number };
};

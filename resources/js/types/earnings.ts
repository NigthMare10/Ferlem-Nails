export type EarningsMode = 'actual' | 'projection' | 'both';

export type EarningsFilters = {
    period: 'today' | 'week' | 'month' | 'custom';
    mode: EarningsMode;
    date: string | null;
    date_from: string | null;
    date_to: string | null;
    employee_id: number | null;
    payment_method: 'cash' | 'card' | null;
};

export type EarningsPeriod = {
    label: string;
    start_date: string;
    end_date: string;
    timezone: string;
    week_starts_on: 'monday';
};

export type EarningsSummary = {
    total_sold: string;
    card_fee_amount: string;
    net_amount: string;
    sales_count: number;
    services_count: number;
    average_sale: string;
};

export type ActualResults = {
    gross_revenue: string;
    pos_fee: string;
    net_income: string;
    completed_sales_count: number;
    performed_services_count: number;
    average_sale: string;
};

export type AppointmentProjection = {
    appointments_count: number;
    services_count: number;
    projected_gross: string;
    deposits_received: string;
    pending_balance: string;
};

export type OtherIncome = {
    retained_deposits_count: number;
    retained_deposits: string;
};

export type Outflows = {
    refunds_count: number;
    refunded_deposits: string;
};

export type EmployeeSummary = EarningsSummary & {
    id: number;
    name: string;
    projected_appointments_count?: number;
    projected_services_count?: number;
    projected_income?: string;
    projected_pending_balance?: string;
};

export type DailySummary = Omit<EarningsSummary, 'average_sale'> & {
    date: string;
    date_label: string;
};

export type EmployeeOption = {
    id: number;
    name: string;
};

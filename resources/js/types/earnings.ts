export type EarningsFilters = {
    period: 'today' | 'week' | 'month' | 'custom';
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

export type EmployeeSummary = EarningsSummary & {
    id: number;
    name: string;
};

export type DailySummary = Omit<EarningsSummary, 'average_sale'> & {
    date: string;
    date_label: string;
};

export type EmployeeOption = {
    id: number;
    name: string;
};

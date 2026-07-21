export type SaleService = {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    price: string;
};

export type SaleCartItem = SaleService & {
    quantity: number;
};

export type PaymentMethod = 'cash' | 'card';

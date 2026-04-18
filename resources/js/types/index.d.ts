export interface User {
    public_id: string;
    name: string;
    email: string;
    phone?: string | null;
    roles: string[];
    permissions: string[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
        activeBranch?: {
            public_id?: string;
            name?: string;
            data?: {
                public_id: string;
                name: string;
            };
        } | null;
    };
    flash?: {
        success?: string;
        error?: string;
    };
    app: {
        name: string;
        currency: string;
        currencySymbol: string;
    };
};

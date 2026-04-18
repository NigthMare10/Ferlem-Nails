export const formatMoney = (amount = 0) => {
    const value = Number(amount) / 100;

    return `L ${value.toLocaleString('es-HN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
};

export const formatDateTime = (value?: string | null) => {
    if (!value) return 'N/D';

    return new Intl.DateTimeFormat('es-HN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

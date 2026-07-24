export function decimalToCents(value: string): number {
    const [whole, fraction = ''] = value.split('.');
    return (Number(whole) * 100) + Number(fraction.padEnd(2, '0').slice(0, 2));
}

export function formatHnl(cents: number): string {
    return new Intl.NumberFormat('es-HN', {
        style: 'currency',
        currency: 'HNL',
    }).format(cents / 100);
}

export function percentageOfCents(amountCents: number, percentage: number): number {
    return Math.floor(((amountCents * percentage) + 50) / 100);
}

export function centsToDecimal(cents: number): string {
    return `${Math.floor(cents / 100)}.${String(cents % 100).padStart(2, '0')}`;
}

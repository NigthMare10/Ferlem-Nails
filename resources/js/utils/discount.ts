import { decimalToCents, percentageOfCents } from './money';
export type DiscountLine = { price: string; quantity: number; discount_type?: string | null; discount_value?: string };
export function itemDiscountCents(line: DiscountLine): number {
    const base = decimalToCents(line.price) * line.quantity;
    const value =
        line.discount_type === 'percent'
            ? percentageOfCents(base, Math.min(100, Math.max(0, Number(line.discount_value) || 0)))
            : line.discount_type === 'fixed'
              ? decimalToCents(line.discount_value || '0')
              : 0;
    return Math.min(base, Math.max(0, value));
}
export function discountedLineTotal(line: DiscountLine): number {
    return decimalToCents(line.price) * line.quantity - itemDiscountCents(line);
}

<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ItemDiscount
{
    public static function apply(User $user, array $line, array $input, bool $globalDiscount = false): array
    {
        $type = $input['discount_type'] ?? null;
        $value = (string) ($input['discount_value'] ?? '0');
        $reason = trim((string) ($input['discount_reason'] ?? ''));
        if (! $type || $value === '0' || $value === '0.00' || $value === '') {
            return $line;
        }
        abort_unless($user->can(Permissions::SALES_APPLY_ITEM_DISCOUNT), 403);
        if (! in_array($type, ['percent', 'fixed'], true) || ! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value) || mb_strlen($reason) < 3 || mb_strlen($reason) > 250) {
            throw ValidationException::withMessages(['items' => 'Descuento inválido: indica tipo, valor y motivo (3–250 caracteres).']);
        }
        if ($globalDiscount) {
            throw ValidationException::withMessages(['items' => 'No combines descuento individual y descuento frecuente.']);
        }
        $base = $line['unit_price_cents'] * $line['quantity'];
        if ($type === 'percent' && (float) $value > 100) {
            throw ValidationException::withMessages(['items' => 'El porcentaje no puede superar 100.']);
        }
        $discount = $type === 'percent' ? Money::percentageOfCents($base, $value) : Money::toCents($value);
        if ($discount < 0 || $discount > $base) {
            throw ValidationException::withMessages(['items' => 'El descuento supera el valor del servicio.']);
        }

        return [...$line, 'line_total_cents' => $base - $discount, 'item_discount_cents' => $discount, 'discount_type' => $type, 'discount_value' => $value, 'discount_reason' => $reason];
    }

    public static function rules(): array
    {
        return ['items.*.expected_price' => ['nullable', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'], 'items.*.discount_type' => ['nullable', 'in:percent,fixed'], 'items.*.discount_value' => ['nullable', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'], 'items.*.discount_reason' => ['nullable', 'string', 'max:250']];
    }

    public static function checkPrice(array $input, int $price): void
    {
        if (isset($input['expected_price']) && Money::toCents((string) $input['expected_price']) !== $price) {
            throw ValidationException::withMessages(['items' => 'El precio cambió desde que abriste la venta. Recarga y revisa el nuevo importe antes de confirmar.']);
        }
    }
}

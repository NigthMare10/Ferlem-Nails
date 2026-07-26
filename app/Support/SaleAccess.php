<?php

namespace App\Support;

use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class SaleAccess
{
    public static function canList(User $user): bool
    {
        return $user->is_active
            && ($user->can(Permissions::SALES_VIEW_ALL) || $user->can(Permissions::SALES_VIEW_OWN));
    }

    public static function canView(User $user, Sale $sale): bool
    {
        return $user->is_active
            && ($user->can(Permissions::SALES_VIEW_ALL)
                || ($user->can(Permissions::SALES_VIEW_OWN) && $sale->sold_by === $user->getKey()));
    }

    public static function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can(Permissions::SALES_VIEW_ALL)) {
            return $query;
        }

        return $query->where('sales.sold_by', $user->getKey());
    }

    public static function canUploadProof(User $user, Sale $sale, SalePayment $payment): bool
    {
        return self::canView($user, $sale)
            && $user->can(Permissions::SALES_UPLOAD_TRANSFER_PROOF)
            && $sale->status === Sale::STATUS_COMPLETED
            && $payment->sale_id === $sale->getKey()
            && $payment->method === Sale::PAYMENT_METHOD_TRANSFER
            && $payment->proof_path === null;
    }
}

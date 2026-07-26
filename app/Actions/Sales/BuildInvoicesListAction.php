<?php

namespace App\Actions\Sales;

use App\Models\Sale;
use App\Models\User;
use App\Support\SaleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuildInvoicesListAction
{
    public const TIMEZONE = 'America/Tegucigalpa';

    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        $query = SaleAccess::scopeVisibleTo(Sale::query(), $user)
            ->select([
                'id', 'appointment_id', 'client_name', 'sale_number', 'sold_by',
                'sold_at', 'total', 'status', 'canceled_at',
            ])
            ->with([
                'soldBy:id,name',
                'payments:id,sale_id,type,method,amount,proof_path',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(function ($query) use ($escaped): void {
                    $query->where('sale_number', 'like', "%{$escaped}%")
                        ->orWhere('client_name', 'like', "%{$escaped}%");
                });
            })
            ->when($filters['date_from'] ?? null, function ($query, string $date): void {
                $start = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE);
                $query->where('sold_at', '>=', $start->utc());
            })
            ->when($filters['date_to'] ?? null, function ($query, string $date): void {
                $end = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE)->addDay();
                $query->where('sold_at', '<', $end->utc());
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['employee_id'] ?? null, fn ($query, int|string $id) => $query->where('sold_by', (int) $id))
            ->when($filters['method'] ?? null, function ($query, string $method): void {
                if ($method === 'mixed') {
                    $query->whereRaw('(select count(distinct sp.method) from sale_payments sp where sp.sale_id = sales.id) > 1');

                    return;
                }
                $query->whereHas('payments', fn ($payments) => $payments->where('method', $method));
            })
            ->when($filters['proof_status'] ?? null, function ($query, string $status): void {
                $query->whereHas('payments', function ($payments) use ($status): void {
                    $payments->where('method', Sale::PAYMENT_METHOD_TRANSFER);
                    $status === 'with_proof'
                        ? $payments->whereNotNull('proof_path')
                        : $payments->whereNull('proof_path');
                });
            });

        return $query->orderByDesc('sold_at')->orderByDesc('id')->paginate(20)->withQueryString();
    }
}

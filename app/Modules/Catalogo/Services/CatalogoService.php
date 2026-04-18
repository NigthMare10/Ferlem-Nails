<?php

namespace App\Modules\Catalogo\Services;

use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Database\Eloquent\Collection;

class CatalogoService
{
    public function listarActivosParaSucursal(Sucursal $sucursal): Collection
    {
        return Servicio::query()
            ->with(['categoria', 'precios' => fn ($query) => $query
                ->where(function ($inner) use ($sucursal) {
                    $inner->whereNull('sucursal_id')->orWhere('sucursal_id', $sucursal->id);
                })
                ->where('is_active', true)
                ->orderByDesc('sucursal_id')
                ->orderByDesc('effective_from')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function precioVigente(Servicio $servicio, Sucursal $sucursal): int
    {
        $today = now()->toDateString();

        $branchPrice = $servicio->precios()
            ->where('sucursal_id', $sucursal->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($branchPrice) {
            return (int) $branchPrice->amount;
        }

        $defaultPrice = $servicio->precios()
            ->whereNull('sucursal_id')
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today);
            })
            ->orderByDesc('effective_from')
            ->first();

        return (int) ($defaultPrice?->amount ?? $servicio->base_price_amount);
    }
}

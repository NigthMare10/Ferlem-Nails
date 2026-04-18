<?php

namespace App\Modules\Sucursales\Support;

use App\Modules\Sucursales\Models\Sucursal;

class SucursalContext
{
    protected ?Sucursal $sucursal = null;

    public function set(?Sucursal $sucursal): void
    {
        $this->sucursal = $sucursal;
    }

    public function get(): ?Sucursal
    {
        return $this->sucursal;
    }

    public function id(): ?int
    {
        return $this->sucursal?->id;
    }

    public function required(): Sucursal
    {
        return $this->sucursal ?? throw new \RuntimeException('No hay una sucursal activa en el contexto actual.');
    }
}

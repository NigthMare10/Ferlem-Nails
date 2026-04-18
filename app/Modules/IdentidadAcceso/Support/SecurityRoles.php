<?php

namespace App\Modules\IdentidadAcceso\Support;

final class SecurityRoles
{
    public static function administrativeRoles(): array
    {
        return [
            'super_admin',
            'admin_negocio',
            'gerente_sucursal',
            // Compatibilidad temporal con datos previos.
            'administrador',
            'gerencia',
        ];
    }

    public static function crossCashClosingRoles(): array
    {
        return [
            'super_admin',
            'admin_negocio',
            'gerente_sucursal',
        ];
    }
}

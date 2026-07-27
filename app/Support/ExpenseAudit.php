<?php

namespace App\Support;

use App\Models\Expense;

final class ExpenseAudit
{
    public static function values(Expense $expense): array
    {
        return [
            'expense_date' => $expense->expense_date?->format('Y-m-d'),
            'category' => $expense->category_name_snapshot,
            'description' => $expense->description,
            'amount' => $expense->amount,
            'payment_method' => $expense->payment_method,
            'vendor' => $expense->vendor,
            'employee' => $expense->employee ? ['id' => $expense->employee->id, 'name' => $expense->employee->name] : null,
        ];
    }

    public static function labels(): array
    {
        return [
            'expense_date' => 'Fecha',
            'category' => 'Categoría',
            'description' => 'Descripción',
            'amount' => 'Monto',
            'payment_method' => 'Método',
            'vendor' => 'Proveedor o destinatario',
            'employee' => 'Empleado relacionado',
        ];
    }
}

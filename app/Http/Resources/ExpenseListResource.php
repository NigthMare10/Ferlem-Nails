<?php

namespace App\Http\Resources;

use App\Models\Expense;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payroll = $this->relationLoaded('payrollObligation') ? $this->payrollObligation : null;

        return [
            'id' => $this->id,
            'expense_number' => $this->expense_number,
            'expense_date' => $this->expense_date?->format('Y-m-d'),
            'expense_date_display' => $this->expense_date?->translatedFormat('d/m/Y'),
            'category' => ['id' => $this->category_id, 'name' => $this->category_name_snapshot],
            'description' => $this->description,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->methodLabel(),
            'vendor' => $this->vendor,
            'employee' => $this->employee ? ['id' => $this->employee->id, 'name' => $this->employee->name] : null,
            'recorded_by' => ['id' => $this->recordedBy->id, 'name' => $this->recordedBy->name],
            'status' => $this->status,
            'status_label' => $this->status === Expense::STATUS_CANCELED ? 'Anulado' : 'Registrado',
            'origin' => $payroll ? 'payroll_automatic' : 'manual',
            'origin_label' => $payroll ? 'Nómina automática' : 'Manual',
            'payroll' => $payroll ? [
                'installment' => $payroll->installment,
                'installment_label' => $payroll->installment === 'first' ? 'Día 15' : 'Último día',
                'scheduled_date_display' => $payroll->scheduled_date?->format('d/m/Y'),
            ] : null,
            'has_attachment' => $this->attachment_path !== null,
            'show_url' => route('expenses.show', $this->resource),
            'attachment_url' => $this->attachment_path && $request->user()->can(Permissions::EXPENSES_VIEW_ATTACHMENT)
                ? route('expenses.attachment', $this->resource)
                : null,
            'can_edit' => ! $payroll && $this->status === Expense::STATUS_RECORDED && $request->user()->can(Permissions::EXPENSES_UPDATE),
            'can_cancel' => ! $payroll && $this->status === Expense::STATUS_RECORDED && $request->user()->can(Permissions::EXPENSES_CANCEL),
        ];
    }

    private function methodLabel(): string
    {
        return match ($this->payment_method) {
            Expense::PAYMENT_METHOD_CARD => 'Tarjeta',
            Expense::PAYMENT_METHOD_TRANSFER => 'Transferencia',
            default => 'Efectivo',
        };
    }
}

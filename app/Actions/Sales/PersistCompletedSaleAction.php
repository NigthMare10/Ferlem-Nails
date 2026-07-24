<?php

namespace App\Actions\Sales;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use App\Support\Money;
use App\Support\SaleFinancials;

class PersistCompletedSaleAction
{
    public function __construct(private PublishInternalNotificationAction $publishNotification) {}

    public function execute(
        User $seller,
        array $lines,
        array $payments,
        string $checkoutToken,
        string $requestHash,
        ?int $appointmentId = null,
    ): Sale {
        $financials = SaleFinancials::summarize($lines, $payments);
        $summaryMethod = $payments[array_key_last($payments)]['method'];
        $hasCardPayment = collect($payments)->contains(fn (array $payment) => $payment['method'] === Sale::PAYMENT_METHOD_CARD);

        $sale = new Sale;
        $sale->appointment_id = $appointmentId;
        $sale->sold_by = $seller->getKey();
        $sale->sold_at = now('UTC');
        $sale->subtotal = Money::fromCents($financials['total_cents']);
        $sale->total = Money::fromCents($financials['total_cents']);
        $sale->total_services = $financials['total_services'];
        $sale->status = Sale::STATUS_COMPLETED;
        $sale->payment_method = $summaryMethod;
        $sale->card_fee_rate = $hasCardPayment ? Sale::CARD_FEE_RATE : '0.00';
        $sale->card_fee_amount = Money::fromCents($financials['fee_cents']);
        $sale->net_amount = Money::fromCents($financials['net_cents']);
        $sale->checkout_token = $checkoutToken;
        $sale->request_hash = $requestHash;
        $sale->save();

        $sale->sale_number = 'SL-'.str_pad((string) $sale->getKey(), 6, '0', STR_PAD_LEFT);
        $sale->save();

        foreach ($lines as $index => $line) {
            $allocatedFeeCents = $financials['fee_allocations'][$index];
            $item = new SaleItem;
            $item->sale_id = $sale->getKey();
            $item->service_id = $line['service_id'];
            $item->performed_by = $line['performed_by'];
            $item->appointment_item_id = $line['appointment_item_id'];
            $item->position = $index + 1;
            $item->service_name = $line['service_name'];
            $item->service_description = $line['service_description'];
            $item->duration_minutes = $line['duration_minutes'];
            $item->unit_price = Money::fromCents($line['unit_price_cents']);
            $item->quantity = $line['quantity'];
            $item->line_total = Money::fromCents($line['line_total_cents']);
            $item->allocated_card_fee_amount = Money::fromCents($allocatedFeeCents);
            $item->net_line_amount = Money::fromCents($line['line_total_cents'] - $allocatedFeeCents);
            $item->save();
        }

        foreach ($payments as $paymentData) {
            $payment = new SalePayment;
            $payment->sale_id = $sale->getKey();
            $payment->type = $paymentData['type'];
            $payment->method = $paymentData['method'];
            $payment->amount = Money::fromCents($paymentData['amount_cents']);
            $payment->card_fee_rate = $paymentData['fee_rate'];
            $payment->card_fee_amount = Money::fromCents($paymentData['fee_cents']);
            $payment->net_amount = Money::fromCents($paymentData['net_cents']);
            $payment->appointment_deposit_id = $paymentData['appointment_deposit_id'];
            $payment->save();
        }

        $fromAppointment = $appointmentId !== null;
        $this->publishNotification->execute(
            $seller,
            $fromAppointment ? 'sale.from_appointment' : 'sale.completed',
            $fromAppointment ? 'Venta desde cita registrada' : 'Venta registrada',
            "Se registró la venta {$sale->sale_number}.",
            "/sales/{$sale->getKey()}/receipt",
            ['type' => 'sale', 'id' => $sale->getKey(), 'appointment_id' => $appointmentId],
            "sale:{$sale->getKey()}:completed",
            $sale->sold_at,
        );
        if ($hasCardPayment) {
            $this->publishNotification->execute(
                $seller,
                'sale.card_payment_recorded',
                'Pago con tarjeta registrado',
                "La venta {$sale->sale_number} incluye un pago con tarjeta.",
                "/sales/{$sale->getKey()}/receipt",
                ['type' => 'sale', 'id' => $sale->getKey(), 'appointment_id' => $appointmentId],
                "sale:{$sale->getKey()}:card-payment",
                $sale->sold_at,
            );
        }

        return $sale->load(['soldBy:id,name', 'appointment', 'items.performedBy:id,name', 'payments']);
    }
}

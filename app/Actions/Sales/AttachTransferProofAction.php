<?php

namespace App\Actions\Sales;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Support\Permissions;
use App\Support\SaleAccess;
use App\Support\TransferProofStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AttachTransferProofAction
{
    public function __construct(
        private readonly TransferProofStorage $proofStorage,
        private readonly PublishInternalNotificationAction $publishNotification,
    ) {}

    public function execute(User $user, Sale $sale, SalePayment $payment, UploadedFile $file): SalePayment
    {
        if ($payment->sale_id !== $sale->getKey()) {
            abort(404);
        }
        if (! SaleAccess::canView($user, $sale)) {
            abort(403);
        }
        if (! SaleAccess::canUploadProof($user, $sale, $payment)) {
            if ($payment->proof_path !== null) {
                throw ValidationException::withMessages(['payment_proof' => 'Este pago ya tiene una captura registrada.']);
            }
            abort(403);
        }

        $proof = $this->proofStorage->store($file, $user);

        try {
            return DB::transaction(function () use ($user, $sale, $payment, $proof): SalePayment {
                $lockedSale = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());
                $lockedPayment = SalePayment::query()->lockForUpdate()->findOrFail($payment->getKey());

                if ($lockedPayment->sale_id !== $lockedSale->getKey()) {
                    abort(404);
                }
                if (! SaleAccess::canView($user, $lockedSale)) {
                    abort(403);
                }
                if (! SaleAccess::canUploadProof($user, $lockedSale, $lockedPayment)) {
                    if ($lockedPayment->proof_path !== null) {
                        throw ValidationException::withMessages(['payment_proof' => 'Este pago ya tiene una captura registrada.']);
                    }
                    abort(403);
                }

                foreach ($proof as $field => $value) {
                    $lockedPayment->{$field} = $value;
                }
                $lockedPayment->save();

                $this->publishNotification->execute(
                    $user,
                    'sale.transfer_proof_added',
                    'Comprobante de transferencia agregado',
                    "Se agregó el comprobante de la factura {$lockedSale->sale_number} por {$user->name}.",
                    "/invoices/{$lockedSale->getKey()}",
                    ['type' => 'sale', 'id' => $lockedSale->getKey(), 'payment_id' => $lockedPayment->getKey()],
                    "sale-payment-proof:{$lockedPayment->getKey()}",
                    $lockedPayment->proof_uploaded_at,
                    Permissions::SALES_VIEW_TRANSFER_PROOF,
                );

                return $lockedPayment;
            }, 3);
        } catch (Throwable $exception) {
            $this->proofStorage->delete($proof);

            throw $exception;
        }
    }
}

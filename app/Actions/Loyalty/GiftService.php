<?php

namespace App\Actions\Loyalty;

use App\Models\Customer;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltyGiftBatch;
use App\Models\LoyaltyGiftRecipient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GiftService
{
    public function authorize(User $actor): void
    {
        abort_unless($actor->is_active && $actor->can('loyalty.manage') && $actor->can('loyalty.adjust'), 403);
    }

    public function send(User $actor, array $data): LoyaltyGiftBatch
    {
        $this->authorize($actor);
        $ids = array_map('intval', $data['customer_ids']);
        sort($ids);
        $payload = [
            'customer_ids' => $ids, 'type' => $data['type'],
            'points' => $data['type'] === 'points' ? (int) $data['points'] : null,
            'service_id' => $data['type'] === 'service' ? (int) $data['service_id'] : null,
            'reason' => trim($data['reason']), 'message' => trim($data['message'] ?? '') ?: null,
            'expires_at' => $data['type'] === 'service' ? ($data['expires_at'] ?? null) : null,
        ];
        $hash = hash('sha256', json_encode($payload));
        try {
        $batch = DB::transaction(function () use ($actor, $data, $payload, $hash, $ids) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $existing = LoyaltyGiftBatch::where('operation_token', $data['operation_token'])->first();
            if ($existing) {
                abort_unless($existing->actor_id === $actor->id && hash_equals($existing->payload_hash, $hash), 422, 'La operación ya existe con otro contenido. Recarga antes de crear otro regalo.');

                return $existing;
            }
            $snapshot = null;
            if ($payload['type'] === 'service') {
                $service = Service::whereKey($payload['service_id'])->lockForUpdate()->firstOrFail();
                if (! $service->is_active) {
                    throw ValidationException::withMessages(['service_id' => 'El servicio está inactivo. Elige uno disponible.']);
                }
                $snapshot = $service->only('id', 'name', 'description', 'duration_minutes', 'price');
            }
            // A unique token and immutable payload protect retries across lost responses.
            $batch = LoyaltyGiftBatch::firstOrCreate(['operation_token' => $data['operation_token']], [
                'actor_id' => $actor->id, 'payload_hash' => $hash, 'type' => $payload['type'],
                'points' => $payload['points'], 'service_id' => $payload['service_id'], 'service_snapshot' => $snapshot,
                'reason' => $payload['reason'], 'message' => $payload['message'], 'expires_at' => $payload['expires_at'],
            ]);
            abort_unless($batch->actor_id === $actor->id && hash_equals($batch->payload_hash, $hash), 422);
            foreach ($ids as $id) {
                $batch->recipients()->firstOrCreate(['customer_id' => $id]);
            }
            $this->audit($actor, 'gift.created', $batch->id, ['type' => $batch->type, 'recipients' => count($ids)]);

            return $batch;
        }, 3);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // A competing transaction can commit after our consistent-read snapshot.
            // Re-read outside that transaction rather than treating a retry as a 500.
            $batch = LoyaltyGiftBatch::where('operation_token', $data['operation_token'])->first();
            if (! $batch) throw $e;
            abort_unless($batch->actor_id === $actor->id && hash_equals($batch->payload_hash, $hash), 422, 'Este identificador ya pertenece a otra operación.');
        }
        $this->process($actor, $batch);

        return $batch->fresh();
    }

    public function process(User $actor, LoyaltyGiftBatch $batch): void
    {
        $this->authorize($actor);
        foreach ($batch->recipients()->whereIn('status', ['pending', 'failed'])->orderBy('customer_id')->pluck('id') as $id) {
            DB::transaction(function () use ($actor, $batch, $id) {
                // Serialize retries for the same batch, then each recipient and ledger account.
                $lockedBatch = LoyaltyGiftBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
                $recipient = LoyaltyGiftRecipient::whereKey($id)->lockForUpdate()->firstOrFail();
                if (! in_array($recipient->status, ['pending', 'failed'], true)) {
                    return;
                }
                $customer = Customer::whereKey($recipient->customer_id)->lockForUpdate()->firstOrFail();
                if (! $customer->is_active || ! $customer->hasVerifiedEmail()) {
                    $recipient->update(['status' => 'failed', 'error' => 'La cuenta debe estar activa y tener el correo verificado.']);

                    return;
                }
                if ($lockedBatch->expires_at && $lockedBatch->expires_at->lessThanOrEqualTo(now())) {
                    $recipient->update(['status' => 'failed', 'error' => 'La vigencia del regalo terminó antes de aplicarlo. Crea otro lote.']);

                    return;
                }
                if ($lockedBatch->type === 'points') {
                    LoyaltyEntry::firstOrCreate(['source_key' => 'gift:'.$recipient->id], [
                        'customer_id' => $customer->id, 'actor_id' => $actor->id, 'type' => 'gift',
                        'points' => $lockedBatch->points, 'qualifying_points' => 0,
                        'note' => mb_substr('Regalo: '.$lockedBatch->reason, 0, 500),
                    ]);
                }
                $recipient->update(['status' => $lockedBatch->type === 'points' ? 'credited' : 'available', 'error' => null, 'applied_at' => now()]);
                $this->audit($actor, 'gift.applied', $recipient->id, ['batch_id' => $batch->id, 'customer_id' => $customer->id]);
            }, 3);
        }
    }

    public function resolve(User $actor, LoyaltyGiftRecipient $gift, string $action, ?string $note = null): void
    {
        abort_unless($actor->is_active && $actor->can('loyalty.manage') && $actor->can('loyalty.redeem'), 403);
        $this->expireDue();
        $expired = DB::transaction(function () use ($actor, $gift, $action, $note) {
            $locked = LoyaltyGiftRecipient::whereKey($gift->id)->lockForUpdate()->firstOrFail();
            $batch = $locked->batch;
            if ($locked->status === 'available' && $batch->expires_at && $batch->expires_at->lessThanOrEqualTo(now())) {
                $locked->update(['status' => 'expired', 'resolved_at' => now()]);
                $this->audit($actor, 'gift.expired', $locked->id, ['batch_id' => $batch->id]);

                return true;
            }
            if ($locked->status === $action) {
                return;
            }
            if ($batch->type !== 'service' || $locked->status !== 'available') {
                throw ValidationException::withMessages(['gift' => 'El regalo ya fue entregado, cancelado o venció. Recarga el detalle.']);
            }
            $customer = Customer::whereKey($locked->customer_id)->lockForUpdate()->firstOrFail();
            if ($action === 'confirmed' && (! $customer->is_active || ! $customer->hasVerifiedEmail())) {
                throw ValidationException::withMessages(['gift' => 'La cuenta no está activa o verificada. No se puede entregar.']);
            }
            $locked->update(['status' => $action, 'resolved_by' => $actor->id, 'resolved_at' => now(), 'resolution_note' => $note]);
            $this->audit($actor, 'gift.'.$action, $locked->id, ['batch_id' => $batch->id, 'note' => $note]);
        }, 3);
        if ($expired) {
            throw ValidationException::withMessages(['gift' => 'El regalo acaba de vencer. No se registró una entrega.']);
        }
    }

    public function expireDue(): void
    {
        LoyaltyGiftRecipient::where('status', 'available')->whereHas('batch', fn ($q) => $q->whereNotNull('expires_at')->where('expires_at', '<=', now()))
            ->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row) {
                        $locked = LoyaltyGiftRecipient::whereKey($row->id)->lockForUpdate()->firstOrFail();
                        if ($locked->status === 'available') {
                            $locked->update(['status' => 'expired', 'resolved_at' => now()]);
                            DB::table('security_audits')->insert(['action' => 'gift.expired', 'subject_type' => 'gift', 'subject_id' => $locked->id, 'after' => json_encode(['batch_id' => $locked->batch_id]), 'created_at' => now(), 'updated_at' => now()]);
                        }
                    }, 3);
                }
            });
    }

    private function audit(User $actor, string $action, int $id, array $data): void
    {
        DB::table('security_audits')->insert(['actor_id' => $actor->id, 'action' => $action, 'subject_type' => 'gift', 'subject_id' => $id, 'after' => json_encode($data), 'created_at' => now(), 'updated_at' => now()]);
    }
}

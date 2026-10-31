<?php

namespace App\Actions\Loyalty;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\Customer;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoyaltyService
{
    public function balance(Customer $customer): int
    {
        return (int) $customer->entries()->sum('points');
    }

    /** Internal checkout hook; never exposed as an unauthenticated operation. */
    public function creditLinkedSale(User $actor, Sale $sale): void
    {
        if (! $sale->customer_id || $sale->status !== Sale::STATUS_COMPLETED) {
            return;
        }
        $customer = Customer::whereKey($sale->customer_id)->lockForUpdate()->firstOrFail();
        if (! $customer->is_active || ! $customer->hasVerifiedEmail()) {
            return;
        }
        foreach ($sale->items()->orderBy('id')->get() as $item) {
            if ($item->loyalty_points < 1 || (float) $item->line_total <= 0) {
                continue;
            }
            LoyaltyEntry::firstOrCreate(['source_key' => 'sale-item:'.$item->id], ['customer_id' => $customer->id, 'sale_item_id' => $item->id, 'actor_id' => $actor->id, 'type' => 'award', 'points' => $item->loyalty_points, 'qualifying_points' => $item->loyalty_points, 'note' => $item->service_name]);
        }
    }

    public function award(User $actor, Sale $sale, string $qrToken): void
    {
        abort_unless($actor->is_active && $actor->can('loyalty.award'), 403);
        DB::transaction(function () use ($actor, $sale, $qrToken) {
            $qr = DB::table('customer_qr_tokens')->where('token_hash', hash('sha256', $qrToken))->lockForUpdate()->first();
            if (! $qr || $qr->used_at || now()->greaterThanOrEqualTo($qr->expires_at)) {
                throw ValidationException::withMessages(['qr' => 'QR vencido o utilizado. Solicita uno nuevo.']);
            }
            $customer = Customer::whereKey($qr->customer_id)->lockForUpdate()->firstOrFail();
            abort_unless($customer->is_active && $customer->hasVerifiedEmail(), 403);
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->can('sales.checkout_any') || $sale->sold_by === $actor->id, 403);
            if ($sale->status !== Sale::STATUS_COMPLETED) {
                throw ValidationException::withMessages(['sale' => 'Solo una venta completada genera puntos.']);
            }
            if ($sale->customer_id && $sale->customer_id !== $customer->id) {
                throw ValidationException::withMessages(['sale' => 'La venta pertenece a otra clienta.']);
            }
            $sale->customer_id = $customer->id;
            $sale->save();
            if ($sale->appointment_id) {
                DB::table('appointments')->where('id', $sale->appointment_id)->whereNull('customer_id')->update(['customer_id' => $customer->id]);
            }
            foreach ($sale->items()->orderBy('id')->lockForUpdate()->get() as $item) {
                if ($item->loyalty_points < 1 || (float) $item->line_total <= 0) {
                    continue;
                }
                LoyaltyEntry::firstOrCreate(['source_key' => 'sale-item:'.$item->id], ['customer_id' => $customer->id, 'sale_item_id' => $item->id, 'actor_id' => $actor->id, 'type' => 'award', 'points' => $item->loyalty_points, 'qualifying_points' => $item->loyalty_points, 'note' => $item->service_name]);
            }
            DB::table('customer_qr_tokens')->where('id', $qr->id)->update(['used_at' => now()]);
        }, 3);
    }

    public function reverseSale(Sale $sale, User $actor): void
    {
        foreach (LoyaltyEntry::whereIn('sale_item_id', $sale->items()->select('id'))->where('type', 'award')->get() as $entry) {
            Customer::whereKey($entry->customer_id)->lockForUpdate()->firstOrFail();
            LoyaltyEntry::firstOrCreate(['source_key' => 'reversal:'.$entry->id], ['customer_id' => $entry->customer_id, 'sale_item_id' => $entry->sale_item_id, 'actor_id' => $actor->id, 'type' => 'reversal', 'points' => -$entry->points, 'qualifying_points' => -$entry->qualifying_points, 'note' => 'Venta cancelada']);
        }
    }

    public function expire(Customer $customer): void
    {
        foreach (LoyaltyRedemption::where('customer_id', $customer->id)->where('status', 'reserved')->where('expires_at', '<=', now())->get() as $redemption) {
            $this->release($redemption, 'expired');
        }
    }

    public function expireDue(): void
    {
        LoyaltyRedemption::where('status', 'reserved')->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $redemption) {
                $this->release($redemption, 'expired');
            }
        });
    }

    public function reserve(Customer $customer, LoyaltyReward $reward, string $token, ?User $actor = null): LoyaltyRedemption
    {
        if ($actor) {
            abort_unless($actor->is_active && $actor->can('loyalty.manage') && $actor->can('loyalty.redeem'), 403);
        }

        $this->expireDue();

        return DB::transaction(function () use ($customer, $reward, $token, $actor) {
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            abort_unless($customer->is_active && $customer->hasVerifiedEmail(), 403);
            $this->expire($customer);
            if ($existing = LoyaltyRedemption::where('operation_token', $token)->first()) {
                abort_unless($existing->customer_id === $customer->id && $existing->loyalty_reward_id === $reward->id, 403);

                return $existing;
            }
            $reward = LoyaltyReward::whereKey($reward->id)->lockForUpdate()->firstOrFail();
            if ($reward->benefit_type === 'service' && ! $reward->service?->is_active) {
                throw ValidationException::withMessages(['reward' => 'El servicio vinculado ya no está disponible.']);
            }
            $today = now(CreateAppointmentAction::TIMEZONE)->toDateString();
            if (! $reward->is_active || $reward->starts_on && $reward->starts_on > $today || $reward->ends_on && $reward->ends_on < $today || $reward->stock !== null && $reward->stock < 1) {
                throw ValidationException::withMessages(['reward' => 'Recompensa no disponible.']);
            }
            if ($reward->birthday_only && (! $customer->birthday || substr($customer->birthday, 5, 2) !== now(CreateAppointmentAction::TIMEZONE)->format('m'))) {
                throw ValidationException::withMessages(['reward' => 'Disponible únicamente en tu mes de cumpleaños.']);
            }
            $used = LoyaltyRedemption::where('customer_id', $customer->id)->where('loyalty_reward_id', $reward->id)->whereIn('status', ['reserved', 'confirmed'])->count();
            if ($used >= $reward->per_customer_limit || $this->balance($customer) < $reward->points_cost) {
                throw ValidationException::withMessages(['reward' => 'Puntos insuficientes o límite de canjes alcanzado.']);
            }
            $snapshot = [...$reward->only('name', 'description', 'benefit_type', 'service_id', 'discount_percent'), 'stock_reserved' => $reward->stock !== null];
            if ($reward->benefit_type === 'service') $snapshot['name'] = $reward->service->name;
            $redemption = LoyaltyRedemption::create(['customer_id' => $customer->id, 'loyalty_reward_id' => $reward->id, 'operation_token' => $token, 'points_cost' => $reward->points_cost, 'status' => 'reserved', 'expires_at' => now()->addHours(24), 'reward_snapshot' => $snapshot]);
            if ($actor) {
                $redemption->update(['reserved_by' => $actor->id]);
                DB::table('security_audits')->insert(['actor_id' => $actor->id, 'action' => 'redemption.reserved', 'subject_type' => 'redemption', 'subject_id' => $redemption->id, 'after' => json_encode(['customer_id' => $customer->id, 'reward_id' => $reward->id, 'points' => $reward->points_cost]), 'created_at' => now(), 'updated_at' => now()]);
            }
            LoyaltyEntry::create(['source_key' => 'redemption:'.$redemption->id, 'customer_id' => $customer->id, 'redemption_id' => $redemption->id, 'type' => 'redeem', 'points' => -$reward->points_cost, 'note' => $reward->name]);
            if ($reward->stock !== null) {
                $reward->decrement('stock');
            }

            return $redemption;
        }, 3);
    }

    public function release(LoyaltyRedemption $redemption, string $status = 'cancelled'): void
    {
        DB::transaction(function () use ($redemption, $status) {
            Customer::whereKey($redemption->customer_id)->lockForUpdate()->firstOrFail();
            $locked = LoyaltyRedemption::whereKey($redemption->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'reserved') {
                return;
            }
            $locked->status = $status === 'cancelled' && $locked->expires_at->lessThanOrEqualTo(now()) ? 'expired' : $status;
            $locked->resolved_at = now();
            $locked->save();
            LoyaltyEntry::firstOrCreate(['source_key' => 'release:'.$locked->id], ['customer_id' => $locked->customer_id, 'redemption_id' => $locked->id, 'type' => 'release', 'points' => $locked->points_cost, 'note' => 'Reserva liberada']);
            if ($locked->reward_snapshot['stock_reserved'] ?? true) {
                LoyaltyReward::whereKey($locked->loyalty_reward_id)->whereNotNull('stock')->increment('stock');
            }
        }, 3);
    }
}

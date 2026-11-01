<?php

namespace App\Http\Controllers;

use App\Actions\Loyalty\GiftService;
use App\Actions\Loyalty\LoyaltyService;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltyGiftBatch;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\Sale;
use App\Models\Service;
use App\Support\RewardImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoyaltyController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user('web')->can('loyalty.manage'), 403);
        app(GiftService::class)->expireDue();
        LoyaltyRedemption::where('status', 'reserved')->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $r) {
                app(LoyaltyService::class)->release($r, 'expired');
            }
        });

        return Inertia::render('Configuration/Loyalty', [
            'rewards' => fn () => LoyaltyReward::with('service:id,name,price,duration_minutes,loyalty_points,is_active')->orderBy('points_cost')->get(),
            'levels' => json_decode(DB::table('loyalty_settings')->where('id', 1)->value('levels'), true),
            'customers' => Customer::query()->when($request->search, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))->select('id', 'name', 'email')->paginate(20),
            'redemptions' => fn () => LoyaltyRedemption::with('customer:id,name,email', 'reservedBy:id,name', 'confirmedBy:id,name', 'appointment:id,loyalty_redemption_id,scheduled_start,status')
                ->when(in_array($request->query('status'), ['reserved', 'scheduled', 'confirmed', 'cancelled', 'expired'], true), fn ($q) => $q->where('status', $request->query('status')))
                ->when($request->query('history_search'), fn ($q, $s) => $q->where(fn ($q) => $q->where('id', $s)->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))))
                ->latest('id')->paginate(20, ['*'], 'canjes')->withQueryString(),
            'status' => $request->query('status', ''),
            'services' => fn () => Service::orderBy('name')->get(['id', 'name', 'is_active', 'price', 'duration_minutes', 'loyalty_points']),
            'settings_version' => fn () => DB::table('loyalty_settings')->where('id', 1)->value('version'),
            'gift_batches' => fn () => LoyaltyGiftBatch::with('actor:id,name')->withCount('recipients')
                ->withCount(['recipients as applied_count' => fn ($q) => $q->whereNotIn('status', ['pending', 'failed'])])
                ->when(in_array($request->query('gift_type'), ['points', 'service'], true), fn ($q) => $q->where('type', $request->query('gift_type')))
                ->latest('id')->paginate(15, ['*'], 'regalos')->withQueryString(),
            'filters' => $request->only('tab', 'status', 'history_search', 'gift_type', 'gift'),
        ]);
    }

    public function reward(Request $request, ?LoyaltyReward $reward = null)
    {
        abort_unless($request->user('web')->can('loyalty.manage'), 403);
        $request->validate(['image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'], 'remove_image' => ['nullable', 'boolean']]);
        if ($request->input('benefit_type') === 'service' && $service = Service::find($request->input('service_id'))) {
            $request->merge(['name' => mb_substr($service->name, 0, 120)]);
            if ($request->boolean('is_active') && ! $service->is_active) {
                throw ValidationException::withMessages(['service_id' => 'El servicio está inactivo. Actívalo en Servicios antes de publicar el premio.']);
            }
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'points_cost' => ['required', 'integer', 'min:1', 'max:1000000'], 'benefit_type' => ['required', Rule::in(['gift', 'service', 'discount'])], 'service_id' => ['nullable', 'required_if:benefit_type,service', 'exists:services,id'], 'discount_percent' => ['nullable', 'required_if:benefit_type,discount', 'numeric', 'gt:0', 'max:100'], 'stock' => ['nullable', 'integer', 'min:0'], 'per_customer_limit' => ['required', 'integer', 'min:1', 'max:1000'], 'birthday_only' => ['boolean'], 'is_active' => ['required', 'boolean'], 'starts_on' => ['nullable', 'date_format:Y-m-d'], 'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on']]);
        if ($data['benefit_type'] !== 'service') {
            $data['service_id'] = null;
        }
        if ($data['benefit_type'] !== 'discount') {
            $data['discount_percent'] = null;
        }
        $newImage = $request->hasFile('image') ? app(RewardImageStorage::class)->store($request->file('image')) : null;
        $oldImage = null;
        try {
            DB::transaction(function () use ($request, $reward, $data, $newImage, &$oldImage) {
                $before = $reward?->toArray();
                if ($reward) {
                    $reward = LoyaltyReward::whereKey($reward->id)->lockForUpdate()->firstOrFail();
                    if ($request->has('version') && (int) $request->version !== $reward->version) {
                        throw ValidationException::withMessages(['version' => 'Otra persona modificó este premio. Recarga y vuelve a revisarlo.']);
                    }
                    $oldImage = $reward->image_path;
                    if ($newImage || $request->boolean('remove_image')) {
                        $data['image_path'] = $newImage;
                    }
                    $reward->update([...$data, 'version' => $reward->version + 1]);
                } else {
                    if ($newImage) {
                        $data['image_path'] = $newImage;
                    }
                    $reward = LoyaltyReward::create($data);
                }
                $this->audit($request, 'reward.saved', 'reward', $reward->id, $before, $data);
            });
        } catch (\Throwable $e) {
            if ($newImage) {
                Storage::disk('local')->delete($newImage);
            }throw $e;
        }
        if ($oldImage && ($newImage || $request->boolean('remove_image'))) {
            Storage::disk('local')->delete($oldImage);
        }

        return back()->with('success', 'Recompensa guardada.');
    }

    public function image(LoyaltyReward $reward)
    {
        abort_unless(auth('web')->user()?->is_active || auth('customer')->user()?->is_active, 403);
        abort_unless($reward->image_path && Storage::disk('local')->exists($reward->image_path), 404);

        return response(Storage::disk('local')->get($reward->image_path))->header('Content-Type', 'image/webp')->header('Cache-Control', 'private, max-age=3600')->header('X-Content-Type-Options', 'nosniff');
    }

    public function settings(Request $request)
    {
        abort_unless($request->user('web')->hasRole('owner'), 403);
        $data = $request->validate(['levels' => ['required', 'array', 'min:1', 'max:10'], 'levels.*.name' => ['required', 'string', 'max:50'], 'levels.*.points' => ['required', 'integer', 'min:0', 'max:10000000', 'distinct'], 'levels.*.benefits' => ['required', 'string', 'max:1000']]);
        usort($data['levels'], fn ($a, $b) => $a['points'] <=> $b['points']);
        $names = array_map(fn ($l) => mb_strtolower(trim($l['name'])), $data['levels']);
        if (count(array_unique($names)) !== count($names)) {
            throw ValidationException::withMessages(['levels' => 'Cada nivel debe tener un nombre distinto.']);
        }
        abort_unless($data['levels'][0]['points'] === 0, 422, 'El primer nivel debe comenzar en cero.');
        DB::transaction(function () use ($request, $data) {
            $before = DB::table('loyalty_settings')->where('id', 1)->lockForUpdate()->first();
            if ($request->has('version') && (int) $request->version !== $before->version) {
                throw ValidationException::withMessages(['version' => 'Los niveles cambiaron en otra sesión. Recarga antes de guardar.']);
            }
            DB::table('loyalty_settings')->where('id', 1)->update(['levels' => json_encode($data['levels']), 'version' => $before->version + 1, 'updated_at' => now()]);
            $this->audit($request, 'levels.saved', 'settings', 1, json_decode($before->levels, true), $data);
        });

        return back()->with('success', 'Niveles actualizados.');
    }

    public function resolve(Request $request)
    {
        abort_unless($request->user('web')->can('loyalty.award'), 403);
        $data = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $qr = DB::table('customer_qr_tokens')->where('token_hash', hash('sha256', $data['token']))->whereNull('used_at')->where('expires_at', '>', now())->first();
        abort_unless($qr, 422, 'QR vencido o inválido.');

        return response()->json(Customer::whereKey($qr->customer_id)->where('is_active', true)->firstOrFail()->only('name'))->header('Cache-Control', 'no-store');
    }

    public function award(Request $request, Sale $sale, LoyaltyService $loyalty)
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $loyalty->award($request->user('web'), $sale, $data['token']);

        return back()->with('success', 'Venta vinculada y puntos asignados sin duplicados.');
    }

    public function confirm(Request $request, LoyaltyRedemption $redemption, LoyaltyService $loyalty)
    {
        abort_unless($request->user('web')->can('loyalty.redeem'), 403);
        $request->validate(['verified' => ['sometimes', 'accepted']]);
        // Commit expiry/refunds before the validation transaction, so a rejected
        // late delivery cannot roll the expiry back into a pending reservation.
        $loyalty->expireDue();
        DB::transaction(function () use ($request, $redemption, $loyalty) {
            $customer = Customer::whereKey($redemption->customer_id)->lockForUpdate()->firstOrFail();
            $locked = LoyaltyRedemption::whereKey($redemption->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'confirmed') {
                return;
            }
            if ($locked->status !== 'reserved' || ! $locked->expires_at->isFuture() || ! $customer->is_active || $loyalty->balance($customer) < 0) {
                throw ValidationException::withMessages(['redemption' => 'El canje ya no está pendiente, venció o la cuenta no es elegible. Recarga el historial.']);
            }
            $locked->update(['status' => 'confirmed', 'confirmed_by' => $request->user('web')->id, 'resolved_at' => now()]);
            $this->audit($request, 'redemption.confirmed', 'redemption', $locked->id, null, $locked->toArray());
        });

        return back()->with('success', 'Entrega de recompensa confirmada.');
    }

    public function linkAppointment(Request $request, Appointment $appointment)
    {
        $actor = $request->user('web');
        abort_unless($actor->can('loyalty.award') && $actor->can('appointments.access'), 403);
        $data = $request->validate(['token' => ['required', 'string', 'size:64']]);
        DB::transaction(function () use ($actor, $appointment, $data) {
            $qr = DB::table('customer_qr_tokens')->where('token_hash', hash('sha256', $data['token']))->whereNull('used_at')->where('expires_at', '>', now())->lockForUpdate()->first();
            abort_unless($qr, 422, 'QR inválido o vencido.');
            $customer = Customer::whereKey($qr->customer_id)->lockForUpdate()->firstOrFail();
            abort_unless($customer->is_active, 403);
            $locked = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->can('sales.checkout_any') || $actor->hasPermissionTo('appointments.view_all') || $locked->items->every(fn ($i) => $i->assigned_to === $actor->id), 403);
            abort_unless($locked->status === 'scheduled' && (! $locked->customer_id || $locked->customer_id === $customer->id), 422, 'La cita no es elegible o pertenece a otra clienta.');
            $locked->forceFill(['customer_id' => $customer->id])->save();
            DB::table('customer_qr_tokens')->where('id', $qr->id)->update(['used_at' => now()]);
        }, 3);

        return back()->with('success', 'Clienta vinculada. Los puntos se acreditarán al completar y cobrar la cita.');
    }

    public function cancel(Request $request, LoyaltyRedemption $redemption, LoyaltyService $loyalty)
    {
        abort_unless($request->user('web')->can('loyalty.redeem'), 403);
        $request->validate(['note' => ['nullable', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($redemption, $loyalty, $request) {
            Customer::whereKey($redemption->customer_id)->lockForUpdate()->firstOrFail();
            $redemption = LoyaltyRedemption::whereKey($redemption->id)->lockForUpdate()->firstOrFail();
            $wasPending = $redemption->status === 'reserved';
            $loyalty->release($redemption);
            if ($wasPending && $request->filled('note')) {
                $redemption->refresh();
                if ($redemption->status === 'cancelled') {
                    $redemption->update(['resolution_note' => $request->note]);
                }
            }
            $this->audit($request, 'redemption.cancelled', 'redemption', $redemption->id, null, $redemption->fresh()->toArray());
        });

        return back()->with('success', 'Reserva liberada si estaba pendiente.');
    }

    public function adjust(Request $request, Customer $customer)
    {
        abort_unless($request->user('web')->can('loyalty.adjust'), 403);
        $data = $request->validate(['points' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'], 'note' => ['required', 'string', 'min:5', 'max:500'], 'operation_token' => ['required', 'uuid']]);
        DB::transaction(function () use ($request, $customer, $data) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $entry = LoyaltyEntry::firstOrCreate(['source_key' => 'adjustment:'.$data['operation_token']], ['customer_id' => $customer->id, 'actor_id' => $request->user('web')->id, 'type' => 'adjustment', 'points' => $data['points'], 'note' => $data['note']]);
            abort_unless($entry->customer_id === $customer->id && $entry->points === $data['points'], 422);
        });

        return back()->with('success', 'Ajuste registrado.');
    }

    private function audit(Request $request, string $action, string $type, int $id, ?array $before, array $after): void
    {
        DB::table('security_audits')->insert(['actor_id' => $request->user('web')->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'before' => json_encode($before), 'after' => json_encode($after), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function bulkStatus(Request $request)
    {
        abort_unless($request->user('web')->can('loyalty.manage'), 403);
        $data = $request->validate(['items' => ['required', 'array', 'min:1', 'max:100'], 'items.*.id' => ['required', 'integer', 'distinct', 'exists:loyalty_rewards,id'], 'items.*.version' => ['required', 'integer', 'min:1'], 'is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $data) {
            $rows = LoyaltyReward::whereIn('id', array_column($data['items'], 'id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($data['items'] as $item) {
                $row = $rows[$item['id']];
                if ($row->version !== $item['version'] || $data['is_active'] && $row->benefit_type === 'service' && ! $row->service?->is_active) {
                    throw ValidationException::withMessages(['items' => 'Algún premio cambió o su servicio está inactivo. No se modificó ninguno. Recarga la lista.']);
                }
            }
            foreach ($rows as $row) {
                $before = $row->toArray();
                $row->update(['is_active' => $data['is_active'], 'version' => $row->version + 1]);
                $this->audit($request, 'reward.status', 'reward', $row->id, $before, $row->toArray());
            }
        }, 3);

        return back()->with('success', 'Premios actualizados. Las reservas existentes se conservan.');
    }
}

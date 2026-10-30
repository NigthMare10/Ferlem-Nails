<?php

namespace App\Http\Controllers;

use App\Actions\Loyalty\LoyaltyService;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LoyaltyController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user('web')->can('loyalty.manage'), 403);
        LoyaltyRedemption::where('status','reserved')->where('expires_at','<=',now())->orderBy('id')->chunkById(100,function($rows){foreach($rows as $r) app(LoyaltyService::class)->release($r,'expired');});

        return Inertia::render('Configuration/Loyalty', [
            'rewards' => LoyaltyReward::orderBy('points_cost')->get(),
            'levels' => json_decode(DB::table('loyalty_settings')->where('id', 1)->value('levels'), true),
            'customers' => Customer::query()->when($request->search, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))->select('id', 'name', 'email')->paginate(20),
            'redemptions' => LoyaltyRedemption::with('customer:id,name')->when(in_array($request->query('status'),['reserved','confirmed','cancelled','expired'],true),fn($q)=>$q->where('status',$request->query('status')))->latest('id')->paginate(20,['*'],'canjes')->withQueryString(),
            'status' => $request->query('status',''),
            'services' => \App\Models\Service::orderBy('name')->get(['id','name','is_active']),
        ]);
    }

    public function reward(Request $request, ?LoyaltyReward $reward = null)
    {
        abort_unless($request->user('web')->can('loyalty.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'points_cost' => ['required', 'integer', 'min:1', 'max:1000000'], 'benefit_type' => ['required', Rule::in(['gift', 'service', 'discount'])], 'service_id' => ['nullable', 'required_if:benefit_type,service', 'exists:services,id'], 'discount_percent' => ['nullable', 'required_if:benefit_type,discount', 'numeric', 'gt:0', 'max:100'], 'stock' => ['nullable', 'integer', 'min:0'], 'per_customer_limit' => ['required', 'integer', 'min:1', 'max:1000'], 'birthday_only' => ['boolean'], 'is_active' => ['required', 'boolean'], 'starts_on' => ['nullable', 'date_format:Y-m-d'], 'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on']]);
        if ($data['benefit_type']!=='service') $data['service_id']=null;
        if ($data['benefit_type']!=='discount') $data['discount_percent']=null;
        DB::transaction(function () use ($request, $reward, $data) {
            $before = $reward?->toArray();
            if ($reward) {
                LoyaltyReward::whereKey($reward->id)->lockForUpdate()->firstOrFail();
                $reward->update($data);
            } else {
                $reward = LoyaltyReward::create($data);
            }
            $this->audit($request, 'reward.saved', 'reward', $reward->id, $before, $data);
        });

        return back()->with('success', 'Recompensa guardada.');
    }

    public function settings(Request $request)
    {
        abort_unless($request->user('web')->hasRole('owner'), 403);
        $data = $request->validate(['levels' => ['required', 'array', 'min:1', 'max:10'], 'levels.*.name' => ['required', 'string', 'max:50'], 'levels.*.points' => ['required', 'integer', 'min:0', 'max:10000000', 'distinct'], 'levels.*.benefits' => ['required', 'string', 'max:1000']]);
        usort($data['levels'], fn ($a, $b) => $a['points'] <=> $b['points']);
        abort_unless($data['levels'][0]['points'] === 0, 422, 'El primer nivel debe comenzar en cero.');
        DB::transaction(function () use ($request, $data) {
            $before = DB::table('loyalty_settings')->where('id', 1)->lockForUpdate()->first();
            DB::table('loyalty_settings')->where('id', 1)->update(['levels' => json_encode($data['levels']), 'updated_at' => now()]);
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
        DB::transaction(function () use ($request, $redemption, $loyalty) {
            $customer = Customer::whereKey($redemption->customer_id)->lockForUpdate()->firstOrFail();
            $locked = LoyaltyRedemption::whereKey($redemption->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'confirmed') {
                return;
            }
            if ($locked->status !== 'reserved' || !$locked->expires_at->isFuture() || !$customer->is_active || !$customer->hasVerifiedEmail() || $loyalty->balance($customer)<0) throw \Illuminate\Validation\ValidationException::withMessages(['redemption'=>'El canje ya no está pendiente, venció o la cuenta no es elegible. Recarga el historial.']);
            $locked->update(['status' => 'confirmed', 'confirmed_by' => $request->user('web')->id]);
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
            abort_unless($customer->is_active && $customer->hasVerifiedEmail(), 403);
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
        DB::transaction(function()use($redemption,$loyalty,$request){
            $loyalty->release($redemption);
            $this->audit($request,'redemption.cancelled','redemption',$redemption->id,null,$redemption->fresh()->toArray());
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
}

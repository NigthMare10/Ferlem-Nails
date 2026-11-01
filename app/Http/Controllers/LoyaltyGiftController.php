<?php

namespace App\Http\Controllers;

use App\Actions\Loyalty\GiftService;
use App\Models\Customer;
use App\Models\LoyaltyGiftBatch;
use App\Models\LoyaltyGiftRecipient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoyaltyGiftController extends Controller
{
    public function customers(Request $request, GiftService $gifts)
    {
        $gifts->authorize($request->user('web'));
        $request->validate(['search' => ['nullable', 'string', 'max:120'], 'all' => ['nullable', 'boolean']]);
        $query = Customer::query()->select('id', 'name', 'email', 'is_active', 'email_verified_at')
            ->when($request->search, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', "%$term%")
                ->orWhere('email', 'like', "%$term%")->orWhere('phone', 'like', "%$term%")))->orderBy('id');
        if ($request->boolean('all')) {
            $customers = $query->where('is_active', true)->limit(501)->get();
            abort_if($customers->count() > 500, 422, 'Hay más de 500 clientas. Refina la búsqueda.');

            return response()->json(['ids' => $customers->pluck('id'), 'customers' => $customers])->header('Cache-Control', 'private, no-store');
        }

        return response()->json($query->paginate(20))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, GiftService $gifts)
    {
        $gifts->authorize($request->user('web'));
        $data = $request->validate([
            'operation_token' => ['required', 'uuid'], 'customer_ids' => ['required', 'array', 'min:1', 'max:500', 'exists:customers,id'],
            'customer_ids.*' => ['required', 'integer', 'distinct'],
            'type' => ['required', Rule::in(['points', 'service'])],
            'points' => ['exclude_unless:type,points', 'required', 'integer', 'between:1,100000'],
            'service_id' => ['exclude_unless:type,service', 'required', 'integer', 'exists:services,id'],
            'reason' => ['required', 'string', 'min:5', 'max:500'], 'message' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['exclude_unless:type,service', 'nullable', 'date_format:Y-m-d\\TH:i'],
        ]);
        if (! empty($data['expires_at'])) {
            $expiry = CarbonImmutable::parse($data['expires_at'], 'America/Tegucigalpa')->utc();
            if (! $expiry->isFuture()) {
                throw ValidationException::withMessages(['expires_at' => 'La vigencia debe ser futura (hora de Honduras).']);
            }
            $data['expires_at'] = $expiry->format('Y-m-d H:i:s');
        }
        $batch = $gifts->send($request->user('web'), $data);

        return redirect('/configuration/loyalty/rewards?tab=gifts&gift='.$batch->id)->with('success', 'Lote procesado. Revisa el resultado por clienta.');
    }

    public function show(Request $request, LoyaltyGiftBatch $batch, GiftService $gifts)
    {
        abort_unless($request->user('web')->can('loyalty.manage'), 403);
        $gifts->expireDue();

        return response()->json($batch->load('actor:id,name', 'recipients.customer:id,name,email'))->header('Cache-Control', 'private, no-store');
    }

    public function retry(Request $request, LoyaltyGiftBatch $batch, GiftService $gifts)
    {
        $gifts->process($request->user('web'), $batch);

        return back()->with('success', 'Reintento terminado sin repetir regalos ya aplicados.');
    }

    public function resolve(Request $request, LoyaltyGiftRecipient $gift, GiftService $gifts)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['confirmed', 'cancelled'])],
            'verified' => ['required_if:action,confirmed', 'accepted_if:action,confirmed'],
            'note' => ['required_if:action,cancelled', 'nullable', 'string', 'min:5', 'max:500']]);
        $gifts->resolve($request->user('web'), $gift, $data['action'], $data['note'] ?? null);

        return back()->with('success', $data['action'] === 'confirmed' ? 'Servicio de regalo entregado. Sin cobro ni puntos de compra.' : 'Regalo cancelado. No hubo puntos cobrados que devolver.');
    }
}

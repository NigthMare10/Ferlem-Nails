<?php

namespace App\Http\Controllers;

use App\Actions\Loyalty\LoyaltyService;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoyaltyCustomerController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user('web')->can('loyalty.manage'), 403);
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Configuration/LoyaltyCustomers', [
            'customers' => Customer::query()->select('id', 'name', 'email', 'phone', 'is_active', 'email_verified_at')->withSum('entries as balance', 'points')
                ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%$search%")->orWhere('email', 'like', "%$search%")->orWhere('phone', 'like', "%$search%")))
                ->orderBy('name')->paginate(20)->withQueryString(), 'search' => $search]);
    }

    public function show(Request $request, Customer $customer, LoyaltyService $loyalty)
    {
        abort_unless($request->user('web')->can('loyalty.manage'), 403);
        $loyalty->expire($customer);

        return Inertia::render('Configuration/LoyaltyCustomer', [
            'customer' => $customer->only('id', 'name', 'email', 'phone', 'birthday', 'is_active', 'email_verified_at', 'created_at'),
            'balance' => $loyalty->balance($customer), 'qualifying' => (int) $customer->entries()->sum('qualifying_points'),
            'entries' => $customer->entries()->latest('id')->paginate(15, ['*'], 'movements'),
            'redemptions' => LoyaltyRedemption::with(['reservedBy:id,name', 'confirmedBy:id,name'])->where('customer_id', $customer->id)->latest('id')->paginate(15, ['*'], 'redemptions'),
            'rewards' => LoyaltyReward::where('is_active', true)->orderBy('points_cost')->get(),
            'visits' => Sale::where('customer_id', $customer->id)->where('status', 'completed')->count(),
            'appointments' => Appointment::where('customer_id', $customer->id)->orderByDesc('scheduled_start')->limit(20)->get(['id', 'scheduled_start', 'status']),
        ]);
    }

    public function reserve(Request $request, Customer $customer, LoyaltyReward $reward, LoyaltyService $loyalty)
    {
        abort_unless($request->user('web')->can('loyalty.manage') && $request->user('web')->can('loyalty.redeem'), 403);
        $data = $request->validate(['operation_token' => ['required', 'uuid']]);
        $reservation = $loyalty->reserve($customer,$reward,$data['operation_token'],$request->user('web'));

        if ($reservation->status !== 'reserved') {
            return back()->with('success', 'Esta operación ya fue procesada. Consulta el estado del canje en el historial.');
        }

        return back()->with('success','Reserva creada para la clienta. Confirma la entrega cuando reciba el beneficio.');
    }
}

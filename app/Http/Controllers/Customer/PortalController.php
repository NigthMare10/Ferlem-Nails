<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Loyalty\LoyaltyService;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\Sale;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PortalController extends Controller
{
    public function index(Request $request, LoyaltyService $loyalty)
    {
        $customer = auth('customer')->user();
        DB::transaction(function () use ($customer, $loyalty) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $loyalty->expire($customer);
        });
        $entries = $customer->entries();

        return Inertia::render('Customer/Portal', [
            'customer' => $customer->only('name', 'email', 'phone', 'birthday', 'created_at', 'email_verified_at'),
            'balance' => $loyalty->balance($customer),
            'qualifying' => (int) (clone $entries)->sum('qualifying_points'),
            'visits' => Sale::where('customer_id', $customer->id)->where('status', 'completed')->count(),
            'entries' => (clone $entries)->latest('id')->paginate(15),
            'rewards' => LoyaltyReward::where('is_active', true)->orderBy('points_cost')->get(),
            'redemptions' => LoyaltyRedemption::where('customer_id', $customer->id)->latest('id')->limit(30)->get(),
            'appointments' => Appointment::where('customer_id', $customer->id)->select('id', 'scheduled_start', 'scheduled_end', 'status')->latest('scheduled_start')->limit(30)->get(),
            'levels' => json_decode(DB::table('loyalty_settings')->where('id', 1)->value('levels'), true),
            'favorites' => Service::whereIn('id', DB::table('customer_favorites')->where('customer_id', $customer->id)->select('service_id'))->get(['id', 'name']),
            'services' => Service::where('is_active', true)->get(['id', 'name']),
            'section' => $request->route()->defaults['section'] ?? 'profile',
            'has_avatar' => (bool) $customer->avatar_path,
        ]);
    }

    public function update(Request $request)
    {
        $customer = auth('customer')->user();
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:30'], 'birthday' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']]);
        // Birthday changes cannot repeatedly unlock birthday benefits.
        if ($customer->birthday) {
            unset($data['birthday']);
        }
        $customer->update($data);

        return back()->with('success', 'Perfil actualizado.');
    }

    public function qr()
    {
        $customer = auth('customer')->user();
        abort_unless($customer->hasVerifiedEmail(), 403);
        $token = Str::random(64);
        DB::transaction(function () use ($customer, $token) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            DB::table('customer_qr_tokens')->where('customer_id', $customer->id)->whereNull('used_at')->delete();
            DB::table('customer_qr_tokens')->insert(['customer_id' => $customer->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['token' => $token, 'expires_in' => 300])->header('Cache-Control', 'no-store');
    }

    public function reserve(Request $request, LoyaltyReward $reward, LoyaltyService $loyalty)
    {
        $data = $request->validate(['operation_token' => ['required', 'uuid']]);
        $loyalty->reserve(auth('customer')->user(), $reward, $data['operation_token']);

        return back()->with('success', 'Recompensa reservada por 24 horas. Preséntala al personal.');
    }

    public function favorite(Request $request, Service $service)
    {
        $key = ['customer_id' => auth('customer')->id(), 'service_id' => $service->id];
        if ($request->isMethod('delete')) {
            DB::table('customer_favorites')->where($key)->delete();
        } else {
            DB::table('customer_favorites')->updateOrInsert($key, []);
        }

        return back();
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096']]);
        abort_unless(function_exists('imagecreatefromstring'), 503, 'El servidor requiere GD para procesar imágenes de forma segura.');
        $source = imagecreatefromstring(file_get_contents($request->file('photo')->getRealPath()));
        abort_unless($source !== false, 422);
        $scaled = imagescale($source, 400);
        ob_start();
        imagewebp($scaled, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($source);
        imagedestroy($scaled);
        $customer = auth('customer')->user();
        $old = $customer->avatar_path;
        $path = 'customer-avatars/'.Str::uuid().'.webp';
        Storage::disk('local')->put($path, $bytes);
        $customer->forceFill(['avatar_path' => $path])->save();
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('success', 'Foto actualizada.');
    }

    public function avatar()
    {
        $path = auth('customer')->user()->avatar_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path))->header('Content-Type', 'image/webp')->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}

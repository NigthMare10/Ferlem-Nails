<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerAccess
{
    public function handle(Request $request, Closure $next)
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return redirect('/clientes/login');
        }
        abort_unless($customer->is_active, 403);
        $signature = hash('sha256', $customer->getAuthPassword());
        $saved = $request->session()->get('customer_password_signature');
        if ($saved !== null && ! hash_equals($saved, $signature)) {
            auth('customer')->logout();
            $request->session()->forget('customer_password_signature');

            return redirect('/clientes/login');
        }
        $request->session()->put('customer_password_signature', $signature);
        Inertia::encryptHistory();
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}

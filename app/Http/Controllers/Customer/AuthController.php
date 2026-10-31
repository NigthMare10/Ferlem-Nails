<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\LoginThrottle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function form(Request $request)
    {
        return Inertia::render('Customer/Auth', ['mode' => $request->route()->defaults['mode'] ?? 'login', 'token' => $request->route('token'), 'email' => $request->query('email', '')]);
    }

    public function register(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->email))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:254', 'unique:customers'], 'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()], 'phone' => ['nullable', 'string', 'max:30']]);
        $data['email'] = strtolower(trim($data['email']));
        $customer = Customer::create($data);
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();
        $request->session()->put('customer_password_signature', hash('sha256', $customer->getAuthPassword()));
        $customer->sendEmailVerificationNotification();

        return redirect('/clientes')->with('success', 'Cuenta creada. Verifica tu correo para usar puntos y recompensas.');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = LoginThrottle::key($request, 'customer');
        LoginThrottle::check($key);
        if (! Auth::guard('customer')->attempt(['email' => strtolower(trim($data['email'])), 'password' => $data['password'], 'is_active' => true])) {
            LoginThrottle::failed($key);
            throw ValidationException::withMessages(['email' => 'Credenciales incorrectas.']);
        }
        LoginThrottle::clear($key);
        $request->session()->regenerate();
        $request->session()->put('customer_password_signature', hash('sha256', Auth::guard('customer')->user()->getAuthPassword()));

        return redirect('/clientes');
    }

    public function logout(Request $request)
    {
        Inertia::clearHistory();
        Auth::guard('customer')->logout();
        $request->session()->forget('customer_password_signature');
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect('/clientes/login');
    }

    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::broker('customers')->sendResetLink(['email' => strtolower(trim($data['email'])), 'is_active' => true]);

        return back()->with('success', 'Si existe una cuenta activa con ese correo, recibirás un enlace para restablecer tu contraseña.');
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()]]);
        $status = Password::broker('customers')->reset($data, function (Customer $customer, string $password) {
            $customer->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'El enlace no es válido o ha vencido.']);
        }

        return redirect('/clientes/login')->with('success', 'Contraseña actualizada.');
    }

    public function verify(Request $request, string $id, string $hash)
    {
        $customer = Auth::guard('customer')->user();
        abort_unless((string) $customer->id === $id && hash_equals(sha1($customer->email), $hash), 403);
        if (! $customer->hasVerifiedEmail()) {
            $customer->markEmailAsVerified();
        }

        return redirect('/clientes')->with('success', 'Correo verificado.');
    }

    public function resend()
    {
        $customer = Auth::guard('customer')->user();
        if (! $customer->hasVerifiedEmail()) {
            $customer->sendEmailVerificationNotification();
        }

        return back()->with('success', 'Revisa tu correo.');
    }
}

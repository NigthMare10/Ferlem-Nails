<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\LandingDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request, LandingDestination $landing): RedirectResponse
    {
        $credentials = $request->validated();
        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Las credenciales no son válidas o la cuenta está desactivada.']);
        }
        $request->session()->regenerate();
        User::whereKey(Auth::id())->update(['last_login_at' => now()]);
        $request->session()->forget('url.intended');

        $destination = $landing->for($request->user());
        abort_if($destination === null, 403);

        return redirect()->to($destination);
    }

    public function destroy(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}

<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class LoginThrottle
{
    public static function key(Request $request, string $guard): string
    {
        return 'login:'.$guard.':'.hash('sha256', strtolower(trim((string) $request->email)).'|'.$request->ip());
    }

    public static function check(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Demasiados intentos fallidos. Espera '.RateLimiter::availableIn($key).' segundos.']);
        }
    }

    public static function failed(string $key): void
    {
        RateLimiter::hit($key, 60);
    }

    public static function clear(string $key): void
    {
        RateLimiter::clear($key);
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PortalLoginLimiter
{
    public static function check(string $identity, string $field = 'username'): void
    {
        $key = 'portal-login:'.hash('sha256', strtolower(trim($identity)).'|'.request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                $field => 'Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }
        RateLimiter::hit($key, 60);
    }
}

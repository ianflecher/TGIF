<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;

class PortalTwoFactor
{
    /** Preserve Fortify's challenge boundary for the custom Volt login forms. */
    public static function challenge(array $credentials, bool $remember): bool
    {
        if (!Features::enabled(Features::twoFactorAuthentication())) return false;
        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials($credentials);
        if (!$user || !$provider->validateCredentials($user, $credentials) || !$user->two_factor_secret) return false;
        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm') && !$user->two_factor_confirmed_at) return false;

        $destination = match ($user->role) {
            'admin' => app(\App\Services\AccessControl::class)->home($user),
            'employee', 'manager' => app(\App\Services\AccessControl::class)->home($user),
            default => 'customer.dashboard',
        };
        Auth::logout(); session()->regenerate(); session()->forget('supplier');
        session(['login.id' => $user->getKey(), 'login.remember' => $remember]);
        if (!session()->has('url.intended')) session(['url.intended' => route($destination)]);
        return true;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalAccess
{
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        if ($portal === 'supplier') {
            $supplierId = data_get($request->session()->get('supplier'), 'supplier_id');
            if (!$supplierId || !DB::table('suppliers')->where('supplier_id', $supplierId)->where('status', 'active')->exists()) {
                $request->session()->forget('supplier');
                return new \Illuminate\Http\RedirectResponse(route('supplier.login'));
            }
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            $module = app(\App\Services\AccessControl::class)->module((string) $request->route()?->getName());
            $login = match ($portal) {
                'admin' => $module && config('portals.'.$module) ? 'portals.'.$module.'.login' : 'admin.login',
                'employee' => 'employee.login',
                'applicant' => 'applicant.login',
                default => 'login',
            };
            return redirect()->guest(route($login));
        }

        // Re-read the identity so role revocation takes effect on Livewire requests too.
        $user = \App\Models\User::find($user->getKey());
        abort_unless($user && !$user->trashed(), 403);
        $access = app(\App\Services\AccessControl::class);
        if ($portal === 'admin') {
            $module = $access->module((string) $request->route()?->getName());
            if ($module) $access->authorize($user, $module);
            else abort_unless($access->isSuperAdmin($user), 403);
            return $next($request);
        }
        $role = $portal === 'applicant' ? 'employee' : $portal;
        $allowedRoles = $portal === 'employee' ? ['employee', 'manager'] : [$role];
        abort_unless(in_array($user->role, $allowedRoles, true) && $user->deleted_at === null, 403);
        if ($portal === 'applicant') abort_unless($access->isApplicant($user), 403);

        // Public applicant registration creates an inactive employee record.
        // Only HR-activated employees may enter the staff portal.
        if ($portal === 'employee') {
            abort_unless(DB::table('employees')->where('user_id', $user->getKey())->where('status', 'active')->exists(), 403);
            if ($request->route()?->getName() === 'employee.warehouse') app(\App\Services\AccessControl::class)->authorize($user, 'inventory');
        }

        return $next($request);
    }
}

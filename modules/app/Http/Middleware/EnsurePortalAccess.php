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
                return redirect()->route('supplier.login');
            }
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            $login = match ($portal) {
                'admin' => 'admin.login',
                'employee' => 'employee.login',
                'applicant' => 'applicant.login',
                default => 'login',
            };
            return redirect()->guest(route($login));
        }

        $role = $portal === 'applicant' ? 'employee' : $portal;
        $allowedRoles = $portal === 'employee' ? ['employee', 'manager'] : [$role];
        abort_unless(in_array($user->role, $allowedRoles, true) && $user->deleted_at === null, 403);

        // Public applicant registration creates an inactive employee record.
        // Only HR-activated employees may enter the staff portal.
        if ($portal === 'employee') {
            abort_unless(DB::table('employees')->where('user_id', $user->getKey())->where('status', 'active')->exists(), 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccessControl
{
    public const MODULES = ['dashboard', 'ecommerce', 'inventory', 'procurement', 'supplychain', 'customerservice', 'sales', 'hr', 'projects', 'finance', 'reports', 'commerce', 'access'];

    public function allows(?User $actor, string $module, string $ability = 'read'): bool
    {
        if (!$actor) return false;
        $user = DB::table('users')->where('user_id', $actor->getKey())->whereNull('deleted_at')->first();
        if (!$user || !in_array($user->role, ['admin', 'employee', 'manager'], true)) return false;
        if ($user->role !== 'admin' && ($module !== 'inventory' || $ability === 'approve' || !DB::table('employees')->where('user_id', $user->user_id)->where('status', 'active')->exists())) return false;
        if ($user->module_permissions === null) return $user->role === 'admin'; // Existing full administrators.
        $permissions = json_decode($user->module_permissions, true, 512, JSON_THROW_ON_ERROR);
        return in_array($ability, $permissions[$module] ?? [], true);
    }

    public function authorize(?User $actor, string $module, string $ability = 'read'): void
    {
        abort_unless($this->allows($actor, $module, $ability), 403);
    }

    public function home(User $user): string
    {
        if ($user->role === 'admin') {
            foreach (config('erp.navigation') as $item) if (($module = $this->module($item['route'])) && $this->allows($user, $module)) return $item['route'];
            return 'profile.edit';
        }
        if (in_array($user->role, ['employee', 'manager'], true)) return DB::table('employees')->where('user_id', $user->getKey())->where('status', 'active')->exists() ? 'employee.dashboard' : 'applicant.index';
        return 'customer.dashboard';
    }

    public function module(string $name): ?string
    {
        $name = ['supplier-invoice-desk' => 'finance.supplier-payables', 'order-payment-desk' => 'finance.payments', 'commerce-settings' => 'commerce.settings', 'accounting-controls' => 'finance.controls', 'order-adjustment-desk' => 'ecommerce.adjustments', 'access-management' => 'access.management'][$name] ?? $name;
        if ($name === 'supplier.list') return 'procurement';
        if ($name === 'employee.warehouse') return 'inventory';
        if (str_starts_with($name, 'admin.orders.receipt')) return 'finance';
        if (str_starts_with($name, 'admin.')) return str_contains($name, 'dashboard') ? 'dashboard' : null;
        $prefix = explode('.', $name)[0];
        return in_array($prefix, self::MODULES, true) ? $prefix : null;
    }

    public function configure(User $actor, int $id, ?array $permissions): void
    {
        // Only unrestricted administrators can change the permission matrix.
        $this->authorize($actor, 'access', 'approve');
        abort_unless(DB::table('users')->where('user_id', $actor->getKey())->value('module_permissions') === null, 403);
        abort_if($id === $actor->getKey(), 422, 'Use another administrator to change your own access.');
        if ($permissions !== null) foreach ($permissions as $module => $abilities) {
            abort_unless(in_array($module, self::MODULES, true) && is_array($abilities) && !array_diff($abilities, ['read', 'write', 'approve']), 422);
        }
        DB::transaction(function () use ($actor, $id, $permissions) {
            $target = DB::table('users')->where('user_id', $id)->whereIn('role', ['admin', 'employee', 'manager'])->whereNull('deleted_at')->lockForUpdate()->first();
            abort_unless($target, 404);
            DB::table('users')->where('user_id', $id)->update(['module_permissions' => $permissions === null ? null : json_encode($permissions), 'updated_at' => now()]);
            DB::table('audit_logs')->insert(['action' => 'permissions', 'table_name' => 'users', 'record_id' => $id, 'user_id' => $actor->getKey(), 'old_values' => json_encode(['permissions' => $target->module_permissions]), 'new_values' => json_encode(['permissions' => $permissions]), 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}

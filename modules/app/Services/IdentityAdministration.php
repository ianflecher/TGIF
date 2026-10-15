<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IdentityAdministration
{
    public function changeRole(User $actor, int $id, string $role): void
    {
        app(AccessControl::class)->authorize($actor, 'access', 'approve');
        abort_unless(DB::table('users')->where('user_id', $actor->getKey())->value('module_permissions') === null, 403);
        abort_if($id === $actor->getKey(), 422, 'Another unrestricted administrator must change your role.');
        Validator::make(compact('role'), ['role' => 'required|in:admin,employee,manager,customer'])->validate();
        DB::transaction(function () use ($actor, $id, $role) {
            $user = DB::table('users')->where('user_id', $id)->whereNull('deleted_at')->lockForUpdate()->first(); abort_unless($user, 404);
            if ($user->role === $role) return;
            // Changing a role never silently inherits unrestricted administrator access.
            DB::table('users')->where('user_id', $id)->update(['role' => $role, 'module_permissions' => '{}', 'updated_at' => now()]);
            if (in_array($role, ['employee', 'manager'], true) && !DB::table('employees')->where('user_id', $id)->exists()) DB::table('employees')->insert(['user_id' => $id, 'job_title' => 'Pending HR activation', 'hire_date' => now()->toDateString(), 'status' => 'inactive', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('audit_logs')->insert(['action' => 'role_change', 'table_name' => 'users', 'record_id' => $id, 'user_id' => $actor->getKey(), 'old_values' => json_encode(['role' => $user->role]), 'new_values' => json_encode(['role' => $role, 'module_permissions' => []]), 'created_at' => now(), 'updated_at' => now()]);
        });
    }
    public function application(User $actor, int $id, string $status): void
    {
        app(AccessControl::class)->authorize($actor, 'hr', in_array($status, ['hired', 'rejected'], true) ? 'approve' : 'write');
        Validator::make(compact('status'), ['status' => 'required|in:pending,reviewed,shortlisted,rejected,hired'])->validate();
        DB::transaction(function () use ($actor, $id, $status) {
            $application = DB::table('job_applications')->where('application_id', $id)->lockForUpdate()->first(); abort_unless($application, 404);
            if ($application->status === $status) return;
            if ($status === 'hired') {
                $user = DB::table('users')->where('user_id', $application->user_id)->whereNull('deleted_at')->lockForUpdate()->first();
                abort_unless($user && $user->role === 'employee', 422, 'Only an applicant identity can be activated through hiring.');
                $employee = DB::table('employees')->where('user_id', $user->user_id)->lockForUpdate()->first();
                if ($employee) DB::table('employees')->where('employee_id', $employee->employee_id)->update(['status' => 'active', 'job_title' => $application->position_applied, 'updated_at' => now()]);
                else DB::table('employees')->insert(['user_id' => $user->user_id, 'job_title' => $application->position_applied, 'hire_date' => now()->toDateString(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('job_applications')->where('application_id', $id)->update(['status' => $status, 'updated_at' => now()]);
            DB::table('audit_logs')->insert(['action' => 'application_status', 'table_name' => 'job_applications', 'record_id' => $id, 'user_id' => $actor->getKey(), 'old_values' => json_encode(['status' => $application->status]), 'new_values' => json_encode(['status' => $status]), 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}

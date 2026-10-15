<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StaffAttendance
{
    public function record(bool $clockIn): void
    {
        $user = auth()->user(); abort_unless($user && in_array($user->role, ['employee', 'manager'], true) && !$user->trashed(), 403);
        DB::transaction(function () use ($user, $clockIn) {
            $employee = DB::table('employees')->where('user_id', $user->getKey())->where('status', 'active')->lockForUpdate()->first(); abort_unless($employee, 403);
            $today = now()->toDateString();
            $row = DB::table('hr_attendance')->where('employee_id', $employee->employee_id)->where('date', $today)->lockForUpdate()->first();
            if ($clockIn) {
                if ($row?->time_in) return;
                if ($row && in_array($row->status, ['on_leave', 'absent'], true)) throw ValidationException::withMessages(['attendance' => 'HR must review this attendance record before clocking in.']);
                if ($row) DB::table('hr_attendance')->where('attendance_id', $row->attendance_id)->update(['time_in' => now(), 'updated_at' => now()]);
                else DB::table('hr_attendance')->insert(['employee_id' => $employee->employee_id, 'date' => $today, 'time_in' => now(), 'status' => 'present', 'notes' => 'Self-service clock entry. Lateness requires the approved work schedule.', 'created_at' => now(), 'updated_at' => now()]);
            } else {
                if (!$row?->time_in) throw ValidationException::withMessages(['attendance' => 'Clock in before clocking out.']);
                if ($row->time_out) return;
                DB::table('hr_attendance')->where('attendance_id', $row->attendance_id)->update(['time_out' => now(), 'updated_at' => now()]);
            }
            if (Schema::hasTable('clock_logs')) {
                if ($clockIn) DB::table('clock_logs')->insert(['employee_id' => $employee->employee_id, 'date' => $today, 'clock_in' => now(), 'created_at' => now(), 'updated_at' => now()]);
                else DB::table('clock_logs')->where('employee_id', $employee->employee_id)->where('date', $today)->whereNull('clock_out')->update(['clock_out' => now(), 'updated_at' => now()]);
            }
            DB::table('audit_logs')->insert(['action' => $clockIn ? 'clock_in' : 'clock_out', 'table_name' => 'employees', 'record_id' => $employee->employee_id, 'user_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}

<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StaffTickets
{
    public function act(int $id, string $action, string $note, bool $internal = false): void
    {
        Validator::make(compact('action', 'note'), ['action' => 'required|in:note,start,resolve,close', 'note' => 'required|string|min:1|max:2000'])->validate();
        $user = auth()->user(); abort_unless($user && in_array($user->role, ['employee', 'manager'], true) && !$user->trashed(), 403);
        DB::transaction(function () use ($id, $action, $note, $internal, $user) {
            $employee = DB::table('employees')->where('user_id', $user->getKey())->where('status', 'active')->lockForUpdate()->first(); abort_unless($employee, 403);
            $ticket = DB::table('tickets')->where('ticket_id', $id)->where('assigned_agent', $employee->employee_id)->lockForUpdate()->first(); abort_unless($ticket, 404);
            $allowed = $action === 'close' ? ['resolved'] : ($action === 'start' ? ['open'] : ['open', 'in_progress']);
            if (!in_array($ticket->status, $allowed, true)) throw ValidationException::withMessages(['ticket' => 'The ticket changed or is closed. Refresh it before continuing.']);
            $status = match ($action) { 'close' => 'closed', 'resolve' => 'resolved', default => 'in_progress' };
            $changes = ['status' => $status, 'updated_at' => now()];
            if (!$ticket->first_response_at && !$internal) $changes['first_response_at'] = now();
            if ($action === 'resolve') $changes['resolved_at'] = now();
            DB::table('tickets')->where('ticket_id', $id)->update($changes);
            DB::table('ticket_notes')->insert(['ticket_id' => $id, 'note_text' => $note, 'created_by' => $user->getKey(), 'internal' => $internal, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('audit_logs')->insert(['action' => $action, 'table_name' => 'tickets', 'record_id' => $id, 'user_id' => $user->getKey(), 'old_values' => json_encode(['status' => $ticket->status]), 'new_values' => json_encode(['status' => $status]), 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}

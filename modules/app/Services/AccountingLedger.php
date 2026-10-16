<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountingLedger
{
    private function error(string $message): never { throw ValidationException::withMessages(['ledger' => $message]); }

    public function lines(array $lines): array
    {
        Validator::make(['lines' => $lines], [
            'lines' => 'required|array|min:2', 'lines.*.account_id' => 'required|integer|exists:chart_of_accounts,account_id',
            'lines.*.debit' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'lines.*.credit' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'lines.*.description' => 'nullable|string|max:500',
        ])->validate();
        $debit = $credit = 0;
        foreach ($lines as $line) {
            $dr = (int) round($line['debit'] * 100); $cr = (int) round($line['credit'] * 100);
            if (($dr > 0) === ($cr > 0)) $this->error('Each journal line must have one positive debit or credit.');
            if (!DB::table('chart_of_accounts')->where('account_id', $line['account_id'])->where('is_active', true)->exists()) $this->error('A journal account is inactive.');
            $debit += $dr; $credit += $cr;
        }
        if ($debit <= 0 || $debit !== $credit || $debit > 999999999999) $this->error('Journal totals must balance exactly in centavos and fit the ledger limit.');
        return [$debit, $credit];
    }

    /** Internal posting boundary; callers supply server-owned source and actor IDs. */
    public function draft(int $actor, string $date, string $description, array $lines, ?string $source = null, ?int $sourceId = null, ?int $reversalOf = null): int
    {
        Validator::make(compact('date', 'description'), ['date' => 'required|date_format:Y-m-d', 'description' => 'required|string|max:1000'])->validate();
        return DB::transaction(function () use ($actor, $date, $description, $lines, $source, $sourceId, $reversalOf) {
            [$dr, $cr] = $this->lines($lines);
            $id = DB::table('journal_entries')->insertGetId(['journal_number' => 'JE-'.Str::uuid(), 'entry_date' => $date, 'description' => $description, 'created_by' => $actor, 'reference_type' => $source, 'reference_id' => $sourceId, 'reversal_of' => $reversalOf, 'status' => 'draft', 'total_debit' => $dr / 100, 'total_credit' => $cr / 100, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $line) DB::table('journal_details')->insert(['journal_id' => $id, 'account_id' => $line['account_id'], 'debit' => $line['debit'], 'credit' => $line['credit'], 'description' => $line['description'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, 'draft', $id);
            return $id;
        });
    }

    public function submit(User $actor, int $id): void
    {
        app(AccessControl::class)->authorize($actor, 'finance', 'write');
        $this->transition($actor, $id, 'submit');
    }
    public function approve(User $actor, int $id): void
    {
        app(AccessControl::class)->authorize($actor, 'finance', 'approve');
        $this->transition($actor, $id, 'approve');
    }
    public function post(User $actor, int $id): void
    {
        app(AccessControl::class)->authorize($actor, 'finance', 'approve');
        $this->transition($actor, $id, 'post');
    }
    public function returnForReview(User $actor, int $id, string $reason): void
    {
        app(AccessControl::class)->authorize($actor, 'finance', 'approve');
        Validator::make(compact('reason'), ['reason' => 'required|string|min:5|max:500'])->validate();
        DB::transaction(function () use ($actor, $id, $reason) {
            $entry = DB::table('journal_entries')->where('journal_id', $id)->lockForUpdate()->first(); abort_unless($entry, 404);
            if ($entry->status !== 'draft') $this->error('Posted journals require a reversal.');
            DB::table('journal_entries')->where('journal_id', $id)->update(['submitted_at' => null, 'approved_at' => null, 'approved_by' => null, 'updated_at' => now()]);
            DB::table('audit_logs')->insert(['action' => 'return_for_review', 'table_name' => 'journal_entries', 'record_id' => $id, 'user_id' => $actor->getKey(), 'new_values' => json_encode(['reason' => $reason]), 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    private function transition(User $actor, int $id, string $action): void
    {
        DB::transaction(function () use ($actor, $id, $action) {
            $entry = DB::table('journal_entries')->where('journal_id', $id)->lockForUpdate()->first();
            abort_unless($entry, 404);
            if ($action === 'post' && $entry->status === 'posted') return;
            if ($entry->status !== 'draft') $this->error('Only draft journals can enter the approval workflow.');
            [$dr, $cr] = $this->lines(DB::table('journal_details')->where('journal_id', $id)->get()->map(fn ($line) => (array) $line)->all());
            if ($dr !== (int) round($entry->total_debit * 100) || $cr !== (int) round($entry->total_credit * 100)) $this->error('Journal header and detail totals differ.');
            $changes = [];
            if ($action === 'submit') {
                if ($entry->submitted_at) return;
                $changes = ['submitted_at' => now()];
            } elseif ($action === 'approve') {
                if (!$entry->submitted_at) $this->error('Submit this journal before approval.');
                if ((int) $entry->created_by === (int) $actor->getKey()) $this->error('A different administrator must approve this journal.');
                if ($entry->approved_at) return;
                $changes = ['approved_by' => $actor->getKey(), 'approved_at' => now()];
            } else {
                if (in_array($entry->reference_type, ['supplier_invoice', 'supplier_payment'], true)) app(SupplierPayables::class)->validateJournal($entry);
                if (in_array($entry->reference_type, ['supplier_credit', 'supplier_correction'], true)) app(SupplierCorrections::class)->validateJournal($entry);
                if ($entry->reference_type === 'order_adjustment') {
                    $orderId = DB::table('order_adjustments')->where('id', $entry->reference_id)->value('order_id');
                    $originalStatus = DB::table('journal_entries')->where('reference_type', 'sales_order')->where('reference_id', $orderId)->value('status');
                    if (!in_array($originalStatus, ['posted', 'cancelled'], true)) $this->error('Post the original sale before posting its return adjustment.');
                }
                if (!$entry->submitted_at || !$entry->approved_at || (int) $entry->approved_by === (int) $entry->created_by) $this->error('Independent approval is required before posting.');
                $period = DB::table('financial_periods')->where('starts_on', '<=', $entry->entry_date)->where('ends_on', '>=', $entry->entry_date)->lockForUpdate()->first();
                if (!$period || $period->closed) $this->error('The journal date must be in a configured open financial period.');
                $changes = ['status' => 'posted', 'posted_at' => now()];
            }
            DB::table('journal_entries')->where('journal_id', $id)->update($changes + ['updated_at' => now()]);
            $this->audit($actor->getKey(), $action, $id);
        });
    }

    public function reverse(User $actor, int $id, string $date, string $reason): int
    {
        app(AccessControl::class)->authorize($actor, 'finance', 'approve');
        Validator::make(compact('date', 'reason'), ['date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:5|max:500'])->validate();
        return DB::transaction(function () use ($actor, $id, $date, $reason) {
            $entry = DB::table('journal_entries')->where('journal_id', $id)->lockForUpdate()->first();
            abort_unless($entry, 404);
            if ($existing = DB::table('journal_entries')->where('reversal_of', $id)->first()) return $existing->journal_id;
            if ($entry->status !== 'posted') $this->error('Only posted journals can be reversed.');
            if (in_array($entry->reference_type, ['supplier_invoice', 'supplier_payment', 'supplier_credit', 'supplier_correction'], true)) $this->error('Use the supplier credit/payment correction workflow to preserve source balances. Approved corrections cannot be silently reversed.');
            if ($entry->reference_type === 'sales_order' && DB::table('order_adjustments')->where('order_id', $entry->reference_id)->exists()) $this->error('This sale already has operational adjustments. Reconcile those credit journals instead of reversing the sale twice.');
            $lines = DB::table('journal_details')->where('journal_id', $id)->get()->map(fn ($line) => ['account_id' => $line->account_id, 'debit' => $line->credit, 'credit' => $line->debit])->all();
            return $this->draft($actor->getKey(), $date, 'Reversal of '.$entry->journal_number.': '.$reason, $lines, 'journal_reversal', $id, $id);
        });
    }

    public function period(User $actor, string $start, string $end): int
    {
        app(AccessControl::class)->authorize($actor, 'finance', 'approve');
        Validator::make(compact('start', 'end'), ['start' => 'required|date_format:Y-m-d', 'end' => 'required|date_format:Y-m-d|after_or_equal:start'])->validate();
        return DB::transaction(function () use ($actor, $start, $end) {
            // A common lock also serializes period creation across administrators.
            DB::table('users')->orderBy('user_id')->lockForUpdate()->first();
            if (DB::table('financial_periods')->where('starts_on', '<=', $end)->where('ends_on', '>=', $start)->exists()) $this->error('Financial periods cannot overlap.');
            $id = DB::table('financial_periods')->insertGetId(['starts_on' => $start, 'ends_on' => $end, 'closed' => false, 'created_by' => $actor->getKey(), 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($actor->getKey(), 'period_open', $id, 'financial_periods'); return $id;
        });
    }
    public function closePeriod(User $actor, int $id): void
    {
        app(AccessControl::class)->authorize($actor, 'finance', 'approve');
        DB::transaction(function () use ($actor, $id) {
            $period = DB::table('financial_periods')->where('id', $id)->lockForUpdate()->first(); abort_unless($period, 404);
            if (DB::table('journal_entries')->whereIn('reference_type', ['supplier_credit', 'supplier_correction'])->where('status', 'draft')->whereBetween('entry_date', [$period->starts_on, $period->ends_on])->exists()) $this->error('Post approved supplier correction journals before closing their financial period.');
            DB::table('financial_periods')->where('id', $id)->update(['closed' => true, 'updated_at' => now()]);
            $this->audit($actor->getKey(), 'period_close', $id, 'financial_periods');
        });
    }
    private function audit(int $actor, string $action, int $id, string $table = 'journal_entries'): void
    {
        DB::table('audit_logs')->insert(['action' => $action, 'table_name' => $table, 'record_id' => $id, 'user_id' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }
}

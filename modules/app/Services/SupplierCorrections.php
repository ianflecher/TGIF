<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SupplierCorrections
{
    private function error(string $message): never { throw ValidationException::withMessages(['corrections' => $message]); }
    private function cents($value): int { return (int) round((float) $value * 100); }
    private function authorize(User $actor, string $ability): void { app(AccessControl::class)->authorize($actor, 'finance', $ability); }
    private function audit(User $actor, string $action, string $table, int $id): void
    {
        DB::table('audit_logs')->insert(['action' => $action, 'table_name' => $table, 'record_id' => $id, 'user_id' => $actor->getKey(), 'created_at' => now(), 'updated_at' => now()]);
    }
    private function lockInvoice(int $id): object
    {
        $invoice = DB::table('supplier_invoices')->where('id', $id)->first(); abort_unless($invoice, 404);
        DB::table('purchase_orders')->where('po_id', $invoice->po_id)->lockForUpdate()->first();
        return DB::table('supplier_invoices')->where('id', $id)->lockForUpdate()->first();
    }
    private function posted(?int $id): void
    {
        if (!$id || DB::table('journal_entries')->where('journal_id', $id)->value('status') !== 'posted' || DB::table('journal_entries')->where('reversal_of', $id)->exists()) $this->error('Post the original source journal before this correction. Reversed sources require reconciliation.');
    }
    private function date(object $invoice, string $date): void
    {
        if ($date < $invoice->invoice_date) $this->error('Correction date cannot precede the invoice.');
    }
    private function openPeriod(string $date): void
    {
        $period = DB::table('financial_periods')->where('starts_on', '<=', $date)->where('ends_on', '>=', $date)->lockForUpdate()->first();
        if (!$period || $period->closed) $this->error('Approve corrections only for a configured open financial period.');
    }
    public function readyForSettlement(int $invoiceId): void
    {
        foreach (['supplier_credits', 'supplier_payment_corrections'] as $table) {
            foreach (DB::table($table)->where('invoice_id', $invoiceId)->where('status', 'approved')->get() as $source) $this->posted($source->journal_id);
        }
    }
    private function readyForCorrection(object $invoice): void
    {
        if ($invoice->status !== 'approved') $this->error('Corrections require an approved invoice.');
        $this->posted($invoice->journal_id); $this->readyForSettlement($invoice->id);
        foreach (DB::table('supplier_disbursements')->where('invoice_id', $invoice->id)->where('status', 'recorded')->get() as $payment) $this->posted($payment->journal_id);
    }
    private function creditLimits(int $invoiceId, array $lines): int
    {
        $total = 0;
        foreach ($lines as $line) {
            $original = DB::table('supplier_invoice_lines')->where('id', $line['invoice_line_id'])->where('invoice_id', $invoiceId)->first();
            abort_unless($original, 422, 'Credit lines must belong to the selected invoice.');
            $used = DB::table('supplier_credit_lines as l')->join('supplier_credits as c', 'c.id', '=', 'l.credit_id')->where('l.invoice_line_id', $original->id)->where('c.status', 'approved');
            $credited = $this->cents((clone $used)->sum('l.amount')); $returned = (int) (clone $used)->sum('l.return_quantity');
            $amount = $this->cents($line['amount']); $quantity = (int) $line['return_quantity'];
            if ($amount + $credited > $this->cents($original->total) || $quantity + $returned > $original->quantity) $this->error('Credit or return exceeds the remaining original invoice line.');
            if ($quantity && $amount !== $quantity * $this->cents($original->unit_price)) $this->error('Physical returns must credit the exact original unit price. Use a separate zero-quantity line credit for discounts.');
            $total += $amount;
        }
        return $total;
    }
    public function requestCredit(User $actor, int $invoiceId, array $data, array $lines, string $key): int
    {
        $this->authorize($actor, 'write'); $data['number'] = strtoupper(trim($data['number'] ?? ''));
        Validator::make($data + ['lines' => $lines, 'key' => $key], [
            'number' => 'required|string|max:100', 'date' => 'required|date_format:Y-m-d|before_or_equal:today', 'reason' => 'required|string|min:5|max:1000', 'key' => 'required|uuid',
            'lines' => 'required|array|min:1', 'lines.*.invoice_line_id' => 'required|integer|distinct',
            'lines.*.amount' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2', 'lines.*.return_quantity' => 'required|integer|min:0|max:1000000',
        ])->validate();
        $lines = array_map(fn ($l) => ['invoice_line_id' => (int) $l['invoice_line_id'], 'amount' => $this->cents($l['amount']) / 100, 'return_quantity' => (int) $l['return_quantity']], $lines);
        usort($lines, fn ($a, $b) => $a['invoice_line_id'] <=> $b['invoice_line_id']);
        if (array_sum(array_column($lines, 'return_quantity'))) app(AccessControl::class)->authorize($actor, 'inventory', 'write');
        $hash = hash('sha256', json_encode([$invoiceId, $data, $lines, $actor->getKey()]));
        return DB::transaction(function () use ($actor, $invoiceId, $data, $lines, $key, $hash) {
            $invoice = $this->lockInvoice($invoiceId);
            if ($old = DB::table('supplier_credits')->where('request_key', $key)->first()) {
                if ($old->payload_hash !== $hash) $this->error('This credit request key was already used.');
                return $old->id;
            }
            $this->readyForCorrection($invoice); $this->date($invoice, $data['date']);
            if (DB::table('supplier_credits')->where('supplier_id', $invoice->supplier_id)->where('number', $data['number'])->exists()) $this->error('This supplier credit number already exists.');
            $amount = $this->creditLimits($invoiceId, $lines);
            $id = DB::table('supplier_credits')->insertGetId(['invoice_id' => $invoiceId, 'supplier_id' => $invoice->supplier_id, 'number' => $data['number'], 'credit_date' => $data['date'], 'reason' => $data['reason'], 'amount' => $amount / 100, 'requested_by' => $actor->getKey(), 'request_key' => $key, 'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $line) DB::table('supplier_credit_lines')->insert($line + ['credit_id' => $id]);
            $this->audit($actor, 'supplier_credit_requested', 'supplier_credits', $id); return $id;
        });
    }
    public function approveCredit(User $actor, int $id): void
    {
        $this->authorize($actor, 'approve');
        DB::transaction(function () use ($actor, $id) {
            $credit = DB::table('supplier_credits')->where('id', $id)->first(); abort_unless($credit, 404);
            $invoice = $this->lockInvoice($credit->invoice_id);
            $credit = DB::table('supplier_credits')->where('id', $id)->lockForUpdate()->first();
            if ($credit->status === 'approved') return;
            if ($credit->status !== 'pending' || (int) $credit->requested_by === (int) $actor->getKey()) $this->error('A different Finance approver must review a pending credit.');
            $this->readyForCorrection($invoice);
            $this->openPeriod($credit->credit_date);
            $lines = DB::table('supplier_credit_lines')->where('credit_id', $id)->get()->map(fn ($l) => (array) $l)->all();
            $amount = $this->creditLimits($invoice->id, $lines);
            if ($amount !== $this->cents($credit->amount)) $this->error('Credit header and lines differ.');
            $balance = app(SupplierPayables::class)->balances($invoice->id);
            if ($this->cents($balance['reserved']) > max(0, $this->cents($balance['position']) - $amount)) $this->error('Cancel unpaid payment requests that exceed the balance after this credit.');
            $returnLines = collect($lines)->filter(fn ($l) => $l['return_quantity'] > 0);
            if ($returnLines->isNotEmpty()) {
                app(AccessControl::class)->authorize($actor, 'inventory', 'write');
                $stock = [];
                foreach ($returnLines as $line) {
                    $original = DB::table('supplier_invoice_lines as l')->join('purchase_order_items as p', 'p.id', '=', 'l.po_item_id')->where('l.id', $line['invoice_line_id'])->select('p.inventory_id', 'l.unit_price')->first();
                    $stock[] = ['inventory_id' => $original->inventory_id, 'quantity' => $line['return_quantity'], 'cost' => $original->unit_price];
                }
                DB::table('inventories')->whereIn('inventory_id', array_column($stock, 'inventory_id'))->orderBy('inventory_id')->lockForUpdate()->get();
                foreach ($stock as $line) {
                    $available = DB::table('inventories')->where('inventory_id', $line['inventory_id'])->value('quantity');
                    if ($available < $line['quantity']) $this->error('Insufficient on-hand stock for this physical return.');
                    DB::table('inventories')->where('inventory_id', $line['inventory_id'])->decrement('quantity', $line['quantity']);
                    DB::table('stock_transactions')->insert(['inventory_id' => $line['inventory_id'], 'type' => 'out', 'quantity' => $line['quantity'], 'unit_cost' => $line['cost'], 'reference_type' => 'supplier_credit', 'reference_id' => $id, 'transaction_date' => $credit->credit_date, 'created_by' => $actor->getKey(), 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $journal = app(AccountingLedger::class)->draft($actor->getKey(), $credit->credit_date, 'Supplier credit '.$credit->number, [
                ['account_id' => $invoice->payable_account_id, 'debit' => $credit->amount, 'credit' => 0],
                ['account_id' => $invoice->debit_account_id, 'debit' => 0, 'credit' => $credit->amount],
            ], 'supplier_credit', $id);
            DB::table('supplier_credits')->where('id', $id)->update(['status' => 'approved', 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'journal_id' => $journal, 'updated_at' => now()]);
            $this->audit($actor, 'supplier_credit_approved', 'supplier_credits', $id);
        });
    }
    private function correctionLimits(object $invoice, object $payment, string $kind, int $amount, ?int $exclude = null): void
    {
        if ($payment->status !== 'recorded') $this->error('Select a recorded payment.'); $this->posted($payment->journal_id);
        $existing = DB::table('supplier_payment_corrections')->where('payment_id', $payment->id)->where('status', 'approved');
        if ((clone $existing)->where('kind', 'void')->exists()) $this->error('This payment has already been voided.');
        if ($kind === 'void') {
            if ($amount !== $this->cents($payment->amount)) $this->error('Void the entire erroneous entry, then record a corrected payment separately.');
            if ((clone $existing)->exists() || DB::table('supplier_payment_corrections')->where('invoice_id', $invoice->id)->where('kind', 'refund')->where('status', 'pending')->exists()) $this->error('Resolve supplier refunds before voiding this payment.');
        } else {
            $refunded = $this->cents((clone $existing)->where('kind', 'refund')->sum('amount'));
            $pending = DB::table('supplier_payment_corrections')->where('invoice_id', $invoice->id)->where('kind', 'refund')->where('status', 'pending');
            if ($exclude) $pending->where('id', '!=', $exclude);
            $due = $this->cents(app(SupplierPayables::class)->balances($invoice->id)['refund_due']) - $this->cents($pending->sum('amount'));
            if ($amount > $this->cents($payment->amount) - $refunded || $amount > $due) $this->error('Refund exceeds the payment or the unreserved supplier refund due.');
        }
    }
    public function requestPaymentCorrection(User $actor, int $paymentId, array $data, string $key): int
    {
        $this->authorize($actor, 'write'); $data['reference'] = strtoupper(trim($data['reference'] ?? ''));
        Validator::make($data + ['key' => $key], ['kind' => 'required|in:void,refund', 'amount' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2', 'date' => 'required|date_format:Y-m-d|before_or_equal:today', 'reference' => 'required|string|max:150', 'reason' => 'required|string|min:5|max:1000', 'key' => 'required|uuid'])->validate();
        $data['amount'] = $this->cents($data['amount']) / 100;
        $hash = hash('sha256', json_encode([$paymentId, $data, $actor->getKey()]));
        return DB::transaction(function () use ($actor, $paymentId, $data, $key, $hash) {
            $payment = DB::table('supplier_disbursements')->where('id', $paymentId)->first(); abort_unless($payment, 404);
            $invoice = $this->lockInvoice($payment->invoice_id);
            if ($old = DB::table('supplier_payment_corrections')->where('request_key', $key)->first()) {
                if ($old->payload_hash !== $hash) $this->error('This correction request key was already used.'); return $old->id;
            }
            $this->readyForCorrection($invoice); $this->date($invoice, $data['date']);
            $this->correctionDate($invoice, $payment, $data['date']);
            $this->correctionLimits($invoice, $payment, $data['kind'], $this->cents($data['amount']));
            if (DB::table('supplier_payment_corrections')->where('reference', $data['reference'])->exists() || DB::table('supplier_disbursements')->where('reference', $data['reference'])->exists()) $this->error('This cash/correction reference already exists.');
            $id = DB::table('supplier_payment_corrections')->insertGetId(['invoice_id' => $invoice->id, 'payment_id' => $paymentId, 'kind' => $data['kind'], 'amount' => $data['amount'], 'correction_date' => $data['date'], 'reference' => $data['reference'], 'reason' => $data['reason'], 'requested_by' => $actor->getKey(), 'request_key' => $key, 'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, 'supplier_correction_requested', 'supplier_payment_corrections', $id); return $id;
        });
    }
    private function correctionDate(object $invoice, object $payment, string $date): void
    {
        $latestCredit = DB::table('supplier_credits')->where('invoice_id', $invoice->id)->where('status', 'approved')->max('credit_date');
        if ($date < $payment->payment_date || ($latestCredit && $date < $latestCredit)) $this->error('Correction date must be on or after the payment and approved credits.');
    }
    public function approvePaymentCorrection(User $actor, int $id): void
    {
        $this->authorize($actor, 'approve');
        DB::transaction(function () use ($actor, $id) {
            $correction = DB::table('supplier_payment_corrections')->where('id', $id)->first(); abort_unless($correction, 404);
            $invoice = $this->lockInvoice($correction->invoice_id);
            $correction = DB::table('supplier_payment_corrections')->where('id', $id)->lockForUpdate()->first();
            if ($correction->status === 'approved') return;
            if ($correction->status !== 'pending' || (int) $correction->requested_by === (int) $actor->getKey()) $this->error('A different Finance approver must review a pending correction.');
            $this->readyForCorrection($invoice);
            $this->openPeriod($correction->correction_date);
            $payment = DB::table('supplier_disbursements')->where('id', $correction->payment_id)->where('invoice_id', $invoice->id)->first(); abort_unless($payment, 422);
            $this->correctionDate($invoice, $payment, $correction->correction_date);
            $this->correctionLimits($invoice, $payment, $correction->kind, $this->cents($correction->amount), $id);
            $journal = app(AccountingLedger::class)->draft($actor->getKey(), $correction->correction_date, 'Supplier '.$correction->kind.' '.$correction->reference, [
                ['account_id' => $payment->cash_account_id, 'debit' => $correction->amount, 'credit' => 0],
                ['account_id' => $invoice->payable_account_id, 'debit' => 0, 'credit' => $correction->amount],
            ], 'supplier_correction', $id);
            DB::table('supplier_payment_corrections')->where('id', $id)->update(['status' => 'approved', 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'journal_id' => $journal, 'updated_at' => now()]);
            $this->audit($actor, 'supplier_correction_approved', 'supplier_payment_corrections', $id);
        });
    }
    public function reject(User $actor, string $kind, int $id, string $reason): void
    {
        $this->authorize($actor, 'approve'); Validator::make(compact('reason'), ['reason' => 'required|string|min:5|max:1000'])->validate();
        $table = match ($kind) { 'credit' => 'supplier_credits', 'payment' => 'supplier_payment_corrections', default => abort(422) };
        DB::transaction(function () use ($actor, $table, $id, $reason) {
            $source = DB::table($table)->where('id', $id)->first(); abort_unless($source, 404); $this->lockInvoice($source->invoice_id);
            $source = DB::table($table)->where('id', $id)->lockForUpdate()->first();
            if ($source->status !== 'pending') $this->error('Only pending corrections can be rejected.');
            DB::table($table)->where('id', $id)->update(['status' => 'rejected', 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'review_note' => $reason, 'updated_at' => now()]);
            $this->audit($actor, 'supplier_correction_rejected', $table, $id);
        });
    }
    public function validateJournal(object $entry): void
    {
        $isCredit = $entry->reference_type === 'supplier_credit';
        $source = DB::table($isCredit ? 'supplier_credits' : 'supplier_payment_corrections')->where('id', $entry->reference_id)->first();
        if (!$source || $source->status !== 'approved' || (int) $source->journal_id !== (int) $entry->journal_id) $this->error('Correction journal must reference its approved source.');
        $invoice = DB::table('supplier_invoices')->where('id', $source->invoice_id)->first(); $this->posted($invoice->journal_id);
        if ($isCredit) { $debit = $invoice->payable_account_id; $credit = $invoice->debit_account_id; $date = $source->credit_date; }
        else {
            $payment = DB::table('supplier_disbursements')->where('id', $source->payment_id)->first(); $this->posted($payment->journal_id);
            $debit = $payment->cash_account_id; $credit = $invoice->payable_account_id; $date = $source->correction_date;
            foreach (DB::table('supplier_credits')->where('invoice_id', $invoice->id)->where('status', 'approved')->get() as $note) $this->posted($note->journal_id);
        }
        $lines = DB::table('journal_details')->where('journal_id', $entry->journal_id)->get();
        $dr = $lines->where('account_id', $debit)->first(); $cr = $lines->where('account_id', $credit)->first();
        if ($lines->count() !== 2 || !$dr || !$cr || $this->cents($dr->debit) !== $this->cents($source->amount) || $this->cents($dr->credit) || $this->cents($cr->credit) !== $this->cents($source->amount) || $this->cents($cr->debit) || $entry->entry_date !== $date) $this->error('Correction journal differs from the approved source accounts, amount or date.');
    }
    public function reconciliation(int $invoiceId): array
    {
        $invoice = DB::table('supplier_invoices')->where('id', $invoiceId)->first(); abort_unless($invoice, 404);
        $ids = collect([$invoice->journal_id])->merge(DB::table('supplier_disbursements')->where('invoice_id', $invoiceId)->where('status', 'recorded')->pluck('journal_id'));
        foreach (['supplier_credits', 'supplier_payment_corrections'] as $table) $ids = $ids->merge(DB::table($table)->where('invoice_id', $invoiceId)->where('status', 'approved')->pluck('journal_id'));
        $ids = $ids->filter()->unique();
        $posted = DB::table('journal_entries')->whereIn('journal_id', $ids)->where('status', 'posted')->pluck('journal_id');
        $ledger = $this->cents(DB::table('journal_details')->whereIn('journal_id', $posted)->where('account_id', $invoice->payable_account_id)->sum(DB::raw('credit - debit')));
        $balance = app(SupplierPayables::class)->balances($invoiceId);
        $credits = DB::table('supplier_credits')->where('invoice_id', $invoiceId)->where('status', 'approved')->pluck('id');
        $expectedStock = DB::table('supplier_credit_lines as c')->join('supplier_invoice_lines as l', 'l.id', '=', 'c.invoice_line_id')->join('purchase_order_items as p', 'p.id', '=', 'l.po_item_id')->whereIn('c.credit_id', $credits)->where('c.return_quantity', '>', 0)->groupBy('p.inventory_id')->selectRaw('p.inventory_id, SUM(c.return_quantity) as units')->pluck('units', 'inventory_id');
        $actualStock = DB::table('stock_transactions')->where('reference_type', 'supplier_credit')->whereIn('reference_id', $credits)->where('type', 'out')->groupBy('inventory_id')->selectRaw('inventory_id, SUM(quantity) as units')->pluck('units', 'inventory_id');
        $returned = (int) $expectedStock->sum(); $movements = (int) $actualStock->sum();
        $stockMatches = $expectedStock->map(fn ($v) => (int) $v)->sortKeys()->all() === $actualStock->map(fn ($v) => (int) $v)->sortKeys()->all();
        $cash = 0;
        foreach (DB::table('supplier_disbursements')->where('invoice_id', $invoiceId)->where('status', 'recorded')->get() as $payment) {
            $cashIds = collect([$payment->journal_id])->merge(DB::table('supplier_payment_corrections')->where('payment_id', $payment->id)->where('status', 'approved')->pluck('journal_id'));
            $cash += $this->cents(DB::table('journal_details as d')->join('journal_entries as j', 'j.journal_id', '=', 'd.journal_id')->whereIn('d.journal_id', $cashIds)->where('j.status', 'posted')->where('d.account_id', $payment->cash_account_id)->sum(DB::raw('d.debit - d.credit')));
        }
        return ['position' => $balance['position'], 'posted_payable' => $ledger / 100, 'difference' => ($ledger - $this->cents($balance['position'])) / 100,
            'cash_expected' => -$balance['paid'], 'cash_posted' => $cash / 100, 'cash_difference' => ($cash + $this->cents($balance['paid'])) / 100,
            'unposted' => $ids->count() - $posted->count(), 'returned' => $returned, 'stock_moved' => $movements, 'stock_matches' => $stockMatches];
    }
}

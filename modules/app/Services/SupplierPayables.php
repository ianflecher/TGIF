<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SupplierPayables
{
    private function error(string $message): never { throw ValidationException::withMessages(['payables' => $message]); }
    private function authorize(User $actor, string $ability): void { app(AccessControl::class)->authorize($actor, 'finance', $ability); }
    private function audit(?int $actor, string $action, int $id, string $table = 'supplier_invoices'): void
    {
        DB::table('audit_logs')->insert(['action' => $action, 'table_name' => $table, 'record_id' => $id, 'user_id' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }
    private function cents($value): int { return (int) round((float) $value * 100); }

    public function submit(?User $actor, int $poId, array $data, array $lines, string $key): int
    {
        $supplierId = null;
        if ($actor) app(AccessControl::class)->authorize($actor, 'procurement', 'write');
        else $supplierId = app(SupplierOrders::class)->supplierId();
        $data['invoice_number'] = strtoupper(trim($data['invoice_number'] ?? ''));
        Validator::make($data + ['lines' => $lines, 'key' => $key], [
            'invoice_number' => 'required|string|max:100', 'invoice_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'due_date' => 'required|date_format:Y-m-d|after_or_equal:invoice_date', 'key' => 'required|uuid',
            'lines' => 'required|array|min:1', 'lines.*.po_item_id' => 'required|integer|distinct',
            'lines.*.quantity' => 'required|integer|min:1|max:1000000', 'lines.*.unit_price' => 'required|numeric|min:0.01|max:99999999.99|decimal:0,2',
        ])->validate();
        $lines = array_map(fn ($l) => ['po_item_id' => (int) $l['po_item_id'], 'quantity' => (int) $l['quantity'], 'unit_price' => $this->cents($l['unit_price']) / 100], $lines);
        usort($lines, fn ($a, $b) => $a['po_item_id'] <=> $b['po_item_id']);
        $hash = hash('sha256', json_encode([$poId, $data['invoice_number'], $data['invoice_date'], $data['due_date'], $lines]));
        return DB::transaction(function () use ($actor, $supplierId, $poId, $data, $lines, $key, $hash) {
            $po = DB::table('purchase_orders')->where('po_id', $poId)->lockForUpdate()->first();
            abort_unless($po && (!$supplierId || (int) $po->supplier_id === $supplierId), 404);
            if ($old = DB::table('supplier_invoices')->where('request_key', $key)->first()) {
                if ($old->payload_hash !== $hash || (int) $old->created_by !== (int) $actor?->getKey() || (int) $old->supplier_id !== (int) $po->supplier_id) $this->error('This invoice request key was already used.');
                return $old->id;
            }
            if (!DB::table('suppliers')->where('supplier_id', $po->supplier_id)->where('status', 'active')->exists()) $this->error('The supplier is inactive.');
            if (!in_array($po->status, ['confirmed', 'partially_received', 'completed'], true)) $this->error('Invoices require an accepted purchase order.');
            if (DB::table('supplier_invoices')->where('supplier_id', $po->supplier_id)->where('invoice_number', $data['invoice_number'])->exists()) $this->error('This supplier invoice number already exists.');
            $total = 0;
            foreach ($lines as $line) {
                abort_unless(DB::table('purchase_order_items')->where('id', $line['po_item_id'])->where('po_id', $poId)->exists(), 422, 'Invoice line must belong to this purchase order.');
                $total += $line['quantity'] * $this->cents($line['unit_price']);
            }
            if ($total > 999999999999) $this->error('Invoice exceeds the supported monetary limit.');
            $id = DB::table('supplier_invoices')->insertGetId(['po_id' => $poId, 'supplier_id' => $po->supplier_id, 'invoice_number' => $data['invoice_number'], 'invoice_date' => $data['invoice_date'], 'due_date' => $data['due_date'], 'total' => $total / 100, 'request_key' => $key, 'payload_hash' => $hash, 'created_by' => $actor?->getKey(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $line) DB::table('supplier_invoice_lines')->insert($line + ['invoice_id' => $id, 'total' => $line['quantity'] * $this->cents($line['unit_price']) / 100]);
            if ($actor) $this->audit($actor->getKey(), 'supplier_invoice_submitted', $id);
            else DB::table('supplier_invoice_events')->insert(['invoice_id' => $id, 'supplier_id' => $po->supplier_id, 'event' => 'submitted', 'created_at' => now(), 'updated_at' => now()]);
            return $id;
        });
    }

    /** Recomputed at approval while holding the PO lock; only approved invoices reserve receipts. */
    public function matching(int $id): array
    {
        $invoice = DB::table('supplier_invoices')->where('id', $id)->first(); abort_unless($invoice, 404);
        $problems = []; $rows = [];
        foreach (DB::table('supplier_invoice_lines')->where('invoice_id', $id)->get() as $line) {
            $poLine = DB::table('purchase_order_items')->where('id', $line->po_item_id)->where('po_id', $invoice->po_id)->first();
            if (!$poLine) { $problems[] = 'An original PO line is missing.'; continue; }
            $ambiguous = DB::table('purchase_order_items')->where('po_id', $invoice->po_id)->where('inventory_id', $poLine->inventory_id)->count() !== 1;
            $received = (int) DB::table('goods_receipts')->where('po_id', $invoice->po_id)->where('inventory_id', $poLine->inventory_id)->where('quality_status', 'passed')->sum('quantity_received');
            $allocated = (int) DB::table('supplier_invoice_lines as l')->join('supplier_invoices as i', 'i.id', '=', 'l.invoice_id')->where('l.po_item_id', $line->po_item_id)->where('i.status', 'approved')->where('i.id', '!=', $id)->sum('l.quantity');
            $available = max(0, min($received, $poLine->quantity) - $allocated);
            $issues = [];
            if ($ambiguous) $issues[] = 'Duplicate inventory on PO requires reconciliation';
            if ($this->cents($line->unit_price) !== $this->cents($poLine->unit_price)) $issues[] = 'Unit price differs from PO';
            if ($line->quantity > $available) $issues[] = 'Quantity exceeds unbilled accepted goods';
            $name = DB::table('inventories')->where('inventory_id', $poLine->inventory_id)->value('product_name');
            $rows[] = ['name' => $name, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'po_price' => $poLine->unit_price, 'received' => $received, 'allocated' => $allocated, 'available' => $available, 'issues' => $issues];
            foreach ($issues as $issue) $problems[] = $name.': '.$issue;
        }
        if (!$rows) $problems[] = 'No invoice lines.';
        return ['matched' => !$problems, 'problems' => $problems, 'rows' => $rows];
    }

    private function account(int $id, array $types): object
    {
        $account = DB::table('chart_of_accounts')->where('account_id', $id)->where('is_active', true)->first();
        if (!$account || !in_array($account->account_type, $types, true)) $this->error('Select an active account of the required type.');
        return $account;
    }
    private function invoiceLock(int $id): object
    {
        $invoice = DB::table('supplier_invoices')->where('id', $id)->first(); abort_unless($invoice, 404);
        DB::table('purchase_orders')->where('po_id', $invoice->po_id)->lockForUpdate()->first();
        return DB::table('supplier_invoices')->where('id', $id)->lockForUpdate()->first();
    }
    public function approveInvoice(User $actor, int $id, int $debitAccount, int $payableAccount): void
    {
        $this->authorize($actor, 'approve');
        DB::transaction(function () use ($actor, $id, $debitAccount, $payableAccount) {
            $invoice = $this->invoiceLock($id);
            if ($invoice->status === 'approved') return;
            if ($invoice->status !== 'submitted') $this->error('Only submitted invoices may be approved.');
            if ((int) $invoice->created_by === (int) $actor->getKey()) $this->error('A different administrator must approve the invoice.');
            if (!in_array(DB::table('purchase_orders')->where('po_id', $invoice->po_id)->value('status'), ['confirmed', 'partially_received', 'completed'], true)) $this->error('The purchase order is no longer eligible.');
            $match = $this->matching($id); if (!$match['matched']) $this->error(implode('; ', $match['problems']));
            $this->account($debitAccount, ['asset', 'expense']); $this->account($payableAccount, ['liability']);
            $journal = app(AccountingLedger::class)->draft($actor->getKey(), $invoice->invoice_date, 'Supplier invoice '.$invoice->invoice_number, [
                ['account_id' => $debitAccount, 'debit' => $invoice->total, 'credit' => 0], ['account_id' => $payableAccount, 'debit' => 0, 'credit' => $invoice->total],
            ], 'supplier_invoice', $id);
            DB::table('supplier_invoices')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $actor->getKey(), 'approved_at' => now(), 'journal_id' => $journal, 'debit_account_id' => $debitAccount, 'payable_account_id' => $payableAccount, 'updated_at' => now()]);
            $this->audit($actor->getKey(), 'supplier_invoice_approved', $id);
        });
    }
    public function rejectInvoice(User $actor, int $id, string $reason): void
    {
        $this->authorize($actor, 'approve'); Validator::make(compact('reason'), ['reason' => 'required|string|min:5|max:1000'])->validate();
        DB::transaction(function () use ($actor, $id, $reason) {
            $invoice = $this->invoiceLock($id);
            if ($invoice->status !== 'submitted') $this->error('Only submitted invoices can be rejected.');
            DB::table('supplier_invoices')->where('id', $id)->update(['status' => 'rejected', 'review_note' => $reason, 'updated_at' => now()]);
            $this->audit($actor->getKey(), 'supplier_invoice_rejected', $id);
        });
    }
    public function validateJournal(object $entry): void
    {
        if ($entry->reference_type === 'supplier_invoice') {
            $source = DB::table('supplier_invoices')->where('id', $entry->reference_id)->first();
            if (!$source || $source->status !== 'approved' || (int) $source->journal_id !== (int) $entry->journal_id) $this->error('Supplier invoice journal is not linked to an approved invoice.');
            $debit = $source->debit_account_id; $credit = $source->payable_account_id; $amount = $source->total; $date = $source->invoice_date;
        } else {
            $source = DB::table('supplier_disbursements')->where('id', $entry->reference_id)->first();
            if (!$source || $source->status !== 'recorded' || (int) $source->journal_id !== (int) $entry->journal_id) $this->error('Supplier payment journal is not linked to recorded money paid.');
            $invoice = DB::table('supplier_invoices')->where('id', $source->invoice_id)->first(); $this->postedInvoice($invoice);
            $debit = $invoice->payable_account_id; $credit = $source->cash_account_id; $amount = $source->amount; $date = $source->payment_date;
        }
        $lines = DB::table('journal_details')->where('journal_id', $entry->journal_id)->get();
        $dr = $lines->where('account_id', $debit)->first(); $cr = $lines->where('account_id', $credit)->first();
        if ($lines->count() !== 2 || !$dr || !$cr || $this->cents($dr->debit) !== $this->cents($amount) || $this->cents($dr->credit) !== 0 || $this->cents($cr->credit) !== $this->cents($amount) || $this->cents($cr->debit) !== 0 || $entry->entry_date !== $date) $this->error('Supplier source journal differs from its approved accounts, amount or date.');
    }
    public function balances(int $id): array
    {
        $total = $this->cents(DB::table('supplier_invoices')->where('id', $id)->value('total'));
        $paid = $this->cents(DB::table('supplier_disbursements')->where('invoice_id', $id)->where('status', 'recorded')->sum('amount'));
        $reserved = $this->cents(DB::table('supplier_disbursements')->where('invoice_id', $id)->whereIn('status', ['pending', 'approved'])->sum('amount'));
        $credits = $this->cents(DB::table('supplier_credits')->where('invoice_id', $id)->where('status', 'approved')->sum('amount'));
        $corrections = DB::table('supplier_payment_corrections')->where('invoice_id', $id)->where('status', 'approved');
        $voided = $this->cents((clone $corrections)->where('kind', 'void')->sum('amount'));
        $refunded = $this->cents((clone $corrections)->where('kind', 'refund')->sum('amount'));
        $netPaid = $paid - $voided - $refunded; $position = $total - $credits - $netPaid;
        return ['gross_paid' => $paid / 100, 'paid' => $netPaid / 100, 'credits' => $credits / 100, 'voided' => $voided / 100, 'refunded' => $refunded / 100,
            'position' => $position / 100, 'refund_due' => max(0, -$position) / 100, 'outstanding' => max(0, $position) / 100,
            'reserved' => $reserved / 100, 'available' => max(0, $position - $reserved) / 100];
    }
    private function postedInvoice(object $invoice): void
    {
        if ($invoice->status !== 'approved' || DB::table('journal_entries')->where('journal_id', $invoice->journal_id)->value('status') !== 'posted' || DB::table('journal_entries')->where('reversal_of', $invoice->journal_id)->exists()) $this->error('Post the approved supplier invoice journal before payment. Reversed invoices require reconciliation.');
        app(SupplierCorrections::class)->readyForSettlement($invoice->id);
    }
    public function requestPayment(User $actor, int $id, $amount, string $key): int
    {
        $this->authorize($actor, 'write');
        Validator::make(compact('amount', 'key'), ['amount' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2', 'key' => 'required|uuid'])->validate();
        $cents = $this->cents($amount); $hash = hash('sha256', json_encode([$id, $cents, $actor->getKey()]));
        return DB::transaction(function () use ($actor, $id, $cents, $key, $hash) {
            $invoice = $this->invoiceLock($id);
            if ($old = DB::table('supplier_disbursements')->where('request_key', $key)->first()) {
                if ($old->payload_hash !== $hash) $this->error('This payment request key was already used.');
                return $old->id;
            }
            $this->postedInvoice($invoice);
            if ($cents > $this->cents($this->balances($id)['available'])) $this->error('Payment exceeds the balance available after other payment requests.');
            $payment = DB::table('supplier_disbursements')->insertGetId(['invoice_id' => $id, 'amount' => $cents / 100, 'request_key' => $key, 'payload_hash' => $hash, 'requested_by' => $actor->getKey(), 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($actor->getKey(), 'supplier_payment_requested', $payment, 'supplier_disbursements'); return $payment;
        });
    }
    public function approvePayment(User $actor, int $id): void
    {
        $this->authorize($actor, 'approve');
        DB::transaction(function () use ($actor, $id) {
            $payment = DB::table('supplier_disbursements')->where('id', $id)->first(); abort_unless($payment, 404);
            $invoice = $this->invoiceLock($payment->invoice_id);
            $payment = DB::table('supplier_disbursements')->where('id', $id)->lockForUpdate()->first();
            if ($payment->status === 'approved') return;
            if ($payment->status !== 'pending') $this->error('Only pending payments can be approved.');
            if ((int) $payment->requested_by === (int) $actor->getKey()) $this->error('A different administrator must approve payment.');
            $this->postedInvoice($invoice);
            DB::table('supplier_disbursements')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $actor->getKey(), 'approved_at' => now(), 'updated_at' => now()]);
            $this->audit($actor->getKey(), 'supplier_payment_approved', $id, 'supplier_disbursements');
        });
    }
    public function cancelPayment(User $actor, int $id, string $reason): void
    {
        $this->authorize($actor, 'approve'); Validator::make(compact('reason'), ['reason' => 'required|string|min:5|max:1000'])->validate();
        DB::transaction(function () use ($actor, $id, $reason) {
            $payment = DB::table('supplier_disbursements')->where('id', $id)->first(); abort_unless($payment, 404); $this->invoiceLock($payment->invoice_id);
            $payment = DB::table('supplier_disbursements')->where('id', $id)->lockForUpdate()->first();
            if (!in_array($payment->status, ['pending', 'approved'], true)) $this->error('Recorded payments cannot be cancelled; reconcile with Finance.');
            DB::table('supplier_disbursements')->where('id', $id)->update(['status' => 'cancelled', 'note' => $reason, 'updated_at' => now()]);
            $this->audit($actor->getKey(), 'supplier_payment_cancelled', $id, 'supplier_disbursements');
        });
    }
    public function recordPayment(User $actor, int $id, string $date, string $reference, int $cashAccount): void
    {
        $this->authorize($actor, 'write'); $reference = strtoupper(trim($reference));
        Validator::make(compact('date', 'reference'), ['date' => 'required|date_format:Y-m-d|before_or_equal:today', 'reference' => 'required|string|max:150'])->validate();
        DB::transaction(function () use ($actor, $id, $date, $reference, $cashAccount) {
            $payment = DB::table('supplier_disbursements')->where('id', $id)->first(); abort_unless($payment, 404); $invoice = $this->invoiceLock($payment->invoice_id);
            $payment = DB::table('supplier_disbursements')->where('id', $id)->lockForUpdate()->first();
            if ($payment->status === 'recorded') {
                if ($payment->payment_date !== $date || $payment->reference !== $reference || (int) $payment->cash_account_id !== $cashAccount) $this->error('This payment is already recorded with different details.');
                return;
            }
            if ($payment->status !== 'approved') $this->error('Approve the payment before recording money paid.');
            $this->postedInvoice($invoice);
            if ($date < $invoice->invoice_date) $this->error('Payment date cannot precede the invoice date.');
            if (DB::table('supplier_disbursements')->where('reference', $reference)->exists() || DB::table('supplier_payment_corrections')->where('reference', $reference)->exists()) $this->error('This payment reference has already been recorded.');
            $this->account($cashAccount, ['asset']); $this->account($invoice->payable_account_id, ['liability']);
            $journal = app(AccountingLedger::class)->draft($actor->getKey(), $date, 'Supplier payment '.$reference, [
                ['account_id' => $invoice->payable_account_id, 'debit' => $payment->amount, 'credit' => 0], ['account_id' => $cashAccount, 'debit' => 0, 'credit' => $payment->amount],
            ], 'supplier_payment', $id);
            DB::table('supplier_disbursements')->where('id', $id)->update(['status' => 'recorded', 'recorded_by' => $actor->getKey(), 'recorded_at' => now(), 'payment_date' => $date, 'reference' => $reference, 'cash_account_id' => $cashAccount, 'journal_id' => $journal, 'updated_at' => now()]);
            $this->audit($actor->getKey(), 'supplier_payment_recorded', $id, 'supplier_disbursements');
        });
    }
}



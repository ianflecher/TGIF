<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderPayments
{
    public function summary(int $orderId): array
    {
        $rows = DB::table('payments')->where('reference_type', 'sales_order')->where('reference_id', $orderId)->where('status', 'completed')->get();
        $received = (int) round($rows->where('kind', 'receipt')->sum('amount') * 100);
        $refunded = (int) round($rows->where('kind', 'refund')->sum('amount') * 100);
        $total = (int) round((float) DB::table('sales_orders')->where('order_id', $orderId)->value('grand_total') * 100);
        $credits = (int) DB::table('order_adjustments')->where('order_id', $orderId)->sum('credit_cents');
        $adjusted = max(0, $total - $credits); $net = $received - $refunded;
        return ['received' => $received, 'refunded' => $refunded, 'net' => $net, 'credits' => $credits, 'adjusted_total' => $adjusted,
            'refund_due' => $credits ? max(0, $net - $adjusted) : 0,
            'due' => max(0, $adjusted - ($credits ? $net : $received))];
    }

    public function refreshStatus(int $orderId): void
    {
        $summary = $this->summary($orderId);
        $status = $summary['received'] > 0 && $summary['refunded'] >= $summary['received'] ? 'refunded' : ($summary['due'] === 0 && $summary['received'] > 0 ? 'paid' : ($summary['received'] > 0 ? 'partial' : 'pending'));
        DB::table('sales_orders')->where('order_id', $orderId)->update(['payment_status' => $status, 'updated_at' => now()]);
    }

    public function record(User $actor, int $orderId, int $amount, string $key, string $note, ?int $receiptId = null): int
    {
        app(AccessControl::class)->authorize($actor, 'finance', 'write');
        if ($amount <= 0 || $amount > 9999999999 || !Str::isUuid($key) || mb_strlen(trim($note)) < 5 || mb_strlen($note) > 1000) {
            throw ValidationException::withMessages(['payment' => 'Enter a positive amount and a meaningful collection reference or refund reason.']);
        }
        return DB::transaction(function () use ($actor, $orderId, $amount, $key, $note, $receiptId) {
            $order = DB::table('sales_orders')->where('order_id', $orderId)->lockForUpdate()->first();
            abort_if(!$order, 404);
            $kind = $receiptId ? 'refund' : 'receipt';
            $existing = DB::table('payments')->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->reference_type !== 'sales_order' || (int) $existing->reference_id !== $orderId || $existing->kind !== $kind || (int) round($existing->amount * 100) !== $amount || ($existing->parent_payment_id === null ? null : (int) $existing->parent_payment_id) !== $receiptId) throw ValidationException::withMessages(['payment' => 'This request was already used. Refresh the form before recording another transaction.']);
                return $existing->payment_id;
            }
            $summary = $this->summary($orderId);
            if ($receiptId) {
                $receipt = DB::table('payments')->where('payment_id', $receiptId)->where('reference_type', 'sales_order')->where('reference_id', $orderId)->where('kind', 'receipt')->where('status', 'completed')->first();
                if (!$receipt || $receipt->method !== 'cash' || $receipt->provider !== 'manual') throw ValidationException::withMessages(['payment' => 'Select a completed manual cash receipt belonging to this order.']);
                $already = (int) round(DB::table('payments')->where('parent_payment_id', $receiptId)->where('kind', 'refund')->where('status', 'completed')->sum('amount') * 100);
                if ($amount + $already > (int) round($receipt->amount * 100)) throw ValidationException::withMessages(['payment' => 'The refund exceeds the remaining amount on that receipt.']);
                if ($summary['credits'] && $amount > $summary['refund_due']) throw ValidationException::withMessages(['payment' => 'This refund exceeds the remaining approved adjustment credit.']);
            } else {
                if (in_array($order->status, ['cancelled', 'returned'], true) || $order->payment_method !== 'cash' || $summary['refunded'] > 0 && !$summary['credits']) throw ValidationException::withMessages(['payment' => 'Cash collection is not available for this order.']);
                if ($amount > $summary['due']) throw ValidationException::withMessages(['payment' => 'The amount exceeds the unpaid balance.']);
            }
            $payment = DB::table('payments')->insertGetId([
                'payment_number' => ($receiptId ? 'REF-' : 'RCPT-').strtoupper(Str::random(14)),
                'reference_type' => 'sales_order', 'reference_id' => $orderId, 'amount' => $amount / 100,
                'payment_date' => now()->toDateString(), 'method' => 'cash', 'status' => 'completed',
                'received_by' => $actor->getKey(), 'kind' => $kind, 'parent_payment_id' => $receiptId,
                'idempotency_key' => $key, 'currency' => 'PHP', 'provider' => 'manual', 'notes' => $note,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->draftJournal($payment, $orderId, $actor->getKey(), $amount, $kind);
            $summary = $this->summary($orderId);
            $status = $summary['received'] > 0 && $summary['refunded'] >= $summary['received'] ? 'refunded' : ($summary['due'] === 0 ? 'paid' : ($summary['received'] > 0 ? 'partial' : 'pending'));
            DB::table('sales_orders')->where('order_id', $orderId)->update(['payment_status' => $status, 'updated_at' => now()]);
            DB::table('audit_logs')->insert([
                'action' => 'create', 'table_name' => 'payments', 'record_id' => $payment, 'user_id' => $actor->getKey(),
                'new_values' => json_encode(['order_id' => $orderId, 'kind' => $kind, 'amount_cents' => $amount, 'payment_status' => $status]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('order_events')->insert(['order_id' => $orderId, 'status' => $order->status, 'message' => ($receiptId ? 'Refund recorded: ₱' : 'Cash payment received: ₱').number_format($amount / 100, 2), 'actor_id' => $actor->getKey(), 'created_at' => now(), 'updated_at' => now()]);
            return $payment;
        });
    }

    private function draftJournal(int $payment, int $orderId, int $actor, int $amount, string $kind): void
    {
        $settings = app(CommerceSettings::class)->get();
        $cash = $settings['cash_account_id'];
        $other = $settings[$kind === 'refund' ? 'refund_account_id' : 'receivable_account_id'];
        $adjustmentRefund = $kind === 'refund' && DB::table('order_adjustments')->where('order_id', $orderId)->exists();
        if ($adjustmentRefund) $other = $settings['receivable_account_id'];
        if (!$cash || !$other) return; // Visible as awaiting ledger mapping in the staff payment view.
        if (($kind === 'receipt' || $adjustmentRefund) && DB::table('journal_entries')->where('reference_type', 'sales_order')->where('reference_id', $orderId)->exists()) {
            $salesAr = DB::table('chart_of_accounts')->where('account_code', '1100')->value('account_id');
            if ((int) $other !== (int) $salesAr) throw ValidationException::withMessages(['payment' => 'The receivable mapping must match the original sales ledger account (1100).']);
        }
        $accounts = DB::table('chart_of_accounts')->whereIn('account_id', [$cash, $other])->where('is_active', true)->get()->keyBy('account_id');
        if (!$accounts->has($cash) || !$accounts->has($other) || (int) $cash === (int) $other || $accounts[$cash]->account_type !== 'asset' || $accounts[$cash]->normal_balance !== 'debit' || $accounts[$other]->normal_balance !== 'debit' || (($kind === 'receipt' || $adjustmentRefund) && $accounts[$other]->account_type !== 'asset') || ($kind === 'refund' && !$adjustmentRefund && !in_array($accounts[$other]->account_type, ['revenue', 'expense'], true))) throw ValidationException::withMessages(['payment' => 'The configured ledger accounts need review before this payment can be recorded.']);
        $journal = DB::table('journal_entries')->insertGetId([
            'journal_number' => 'JE-'.Str::uuid(), 'entry_date' => now()->toDateString(),
            'description' => ucfirst($kind).' for order '.$orderId, 'created_by' => $actor,
            'reference_type' => 'payment', 'reference_id' => $payment, 'status' => 'draft',
            'total_debit' => $amount / 100, 'total_credit' => $amount / 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$kind === 'receipt' ? $cash : $other, $amount, 0], [$kind === 'receipt' ? $other : $cash, 0, $amount]] as [$account, $debit, $credit]) DB::table('journal_details')->insert(['journal_id' => $journal, 'account_id' => $account, 'debit' => $debit / 100, 'credit' => $credit / 100, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payments')->where('payment_id', $payment)->update(['journal_id' => $journal]);
    }
}

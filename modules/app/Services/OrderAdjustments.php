<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderAdjustments
{
    public function record(User $actor, int $orderId, string $kind, string $reason, string $key, array $items, int $feeCredit = 0): int
    {
        app(AccessControl::class)->authorize($actor, 'ecommerce', 'approve');
        Validator::make(compact('kind', 'reason', 'key', 'items', 'feeCredit'), [
            'kind' => 'required|in:cancel,return', 'reason' => 'required|string|min:5|max:1000', 'key' => 'required|uuid',
            'items' => 'required|array|min:1', 'items.*.id' => 'required|integer|distinct', 'items.*.quantity' => 'required|integer|min:1',
            'items.*.restock' => 'required|integer|min:0', 'feeCredit' => 'integer|min:0',
        ])->validate();
        $items = array_map(fn ($line) => ['id' => (int) $line['id'], 'quantity' => (int) $line['quantity'], 'restock' => (int) $line['restock']], $items);
        usort($items, fn ($a, $b) => $a['id'] <=> $b['id']);
        return DB::transaction(function () use ($actor, $orderId, $kind, $reason, $key, $items, $feeCredit) {
            $order = DB::table('sales_orders')->where('order_id', $orderId)->lockForUpdate()->first(); abort_unless($order, 404);
            if ($existing = DB::table('order_adjustments')->where('request_key', $key)->first()) {
                $saved = DB::table('order_adjustment_items')->where('adjustment_id', $existing->id)->orderBy('order_item_id')->get()->map(fn ($line) => ['id' => (int) $line->order_item_id, 'quantity' => (int) $line->quantity, 'restock' => (int) $line->restocked_quantity])->all();
                if ((int) $existing->order_id !== $orderId || $existing->kind !== $kind || $saved !== $items || $existing->reason !== $reason || $kind === 'return' && (int) $existing->fee_credit_cents !== $feeCredit) $this->error('This request key was already used for another adjustment.');
                return $existing->id;
            }
            if ($kind === 'cancel' && !in_array($order->status, ['confirmed', 'processing'], true)) $this->error('Cancel before shipping. Delivered orders use the returns workflow.');
            if ($kind === 'return' && $order->status !== 'delivered') $this->error('Returns require a delivered order.');
            $original = DB::table('journal_entries')->where('reference_type', 'sales_order')->where('reference_id', $orderId)->lockForUpdate()->get();
            if (!$order->checkout_token || $original->count() !== 1 || !in_array($original[0]->status, ['draft', 'posted'], true)) $this->error('This legacy order needs Finance reconciliation before an automatic adjustment.');
            $original = $original[0];
            if (DB::table('journal_entries')->where('reversal_of', $original->journal_id)->where('status', '!=', 'cancelled')->exists()) $this->error('Finance already reversed this sale. Reconcile the reversal before adding operational credits.');
            $lines = DB::table('order_items')->where('order_id', $orderId)->lockForUpdate()->get()->keyBy('order_item_id');
            if ($kind === 'cancel' && count($items) !== $lines->count()) $this->error('Cancellation must include every order line.');
            $credit = 0; $restockCost = 0; $disposedCost = 0; $normalized = [];
            $products = DB::table('products')->whereIn('product_id', $lines->pluck('product_id'))->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id');
            foreach ($items as $input) {
                $line = $lines[$input['id']] ?? null;
                if (!$line || $line->unit_cost_snapshot === null || $input['restock'] > $input['quantity']) $this->error('Choose valid order lines and restock quantities with known original costs.');
                $previous = (int) DB::table('order_adjustment_items')->where('order_item_id', $line->order_item_id)->sum('quantity');
                $qty = $input['quantity'];
                if ($qty + $previous > $line->quantity || $kind === 'cancel' && $qty !== $line->quantity) $this->error('An adjustment exceeds the remaining ordered quantity.');
                if (!$products->has($line->product_id)) $this->error('A product is missing; reconcile its stock before continuing.');
                // Cumulative proportional rounding ensures the final unit receives the final centavo.
                $total = (int) round($line->total * 100);
                $lineCredit = (int) round($total * ($previous + $qty) / $line->quantity) - (int) round($total * $previous / $line->quantity);
                $credit += $lineCredit;
                $unitCost = (int) round($line->unit_cost_snapshot * 100);
                $restockCost += $unitCost * $input['restock']; $disposedCost += $unitCost * ($qty - $input['restock']);
                $normalized[] = [$line, $qty, $input['restock'], $lineCredit];
            }
            $already = (int) DB::table('order_adjustments')->where('order_id', $orderId)->sum('credit_cents');
            $lineCredits = (int) DB::table('order_adjustment_items')->join('order_adjustments as a', 'a.id', '=', 'order_adjustment_items.adjustment_id')->where('a.order_id', $orderId)->sum('order_adjustment_items.credit_cents');
            $feeRemaining = max(0, (int) round($order->delivery_fee * 100) - ($already - $lineCredits));
            if ($kind === 'cancel') $feeCredit = $feeRemaining;
            if ($feeCredit > $feeRemaining) $this->error('Delivery fee credit exceeds the uncredited delivery fee.');
            $credit += $feeCredit;
            if ($already + $credit > (int) round($order->grand_total * 100)) $this->error('Credits exceed the original order total.');
            $id = DB::table('order_adjustments')->insertGetId(['order_id' => $orderId, 'request_key' => $key, 'kind' => $kind, 'reason' => $reason, 'credit_cents' => $credit, 'fee_credit_cents' => $feeCredit, 'refund_due_cents' => 0, 'actor_id' => $actor->getKey(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($normalized as [$line, $qty, $restock, $lineCredit]) {
                DB::table('order_adjustment_items')->insert(['adjustment_id' => $id, 'order_item_id' => $line->order_item_id, 'quantity' => $qty, 'restocked_quantity' => $restock, 'credit_cents' => $lineCredit, 'created_at' => now(), 'updated_at' => now()]);
                if ($restock) DB::table('products')->where('product_id', $line->product_id)->increment('stock_quantity', $restock);
                DB::table('products')->where('product_id', $line->product_id)->update(['sold_count' => DB::raw('CASE WHEN sold_count >= '.(int) $qty.' THEN sold_count - '.(int) $qty.' ELSE 0 END'), 'updated_at' => now()]);
            }
            $journalLines = [];
            $account = function ($code) { $id = DB::table('chart_of_accounts')->where('account_code', $code)->where('is_active', true)->value('account_id'); if (!$id) $this->error('Finance must configure the original sales/stock accounts first.'); return (int) $id; };
            if ($kind === 'cancel' && $original->status === 'draft') {
                DB::table('journal_entries')->where('journal_id', $original->journal_id)->update(['status' => 'cancelled', 'updated_at' => now()]);
                if ($disposedCost) $journalLines = [['account_id' => $account('5100'), 'debit' => $disposedCost / 100, 'credit' => 0], ['account_id' => $account('1200'), 'debit' => 0, 'credit' => $disposedCost / 100]];
            } else {
                if ($credit) $journalLines = [['account_id' => $account('4100'), 'debit' => $credit / 100, 'credit' => 0], ['account_id' => $account('1100'), 'debit' => 0, 'credit' => $credit / 100]];
                if ($restockCost) $journalLines = array_merge($journalLines, [['account_id' => $account('1200'), 'debit' => $restockCost / 100, 'credit' => 0], ['account_id' => $account('5100'), 'debit' => 0, 'credit' => $restockCost / 100]]);
            }
            $journal = $journalLines ? app(AccountingLedger::class)->draft($actor->getKey(), now()->toDateString(), ucfirst($kind).' order '.$order->order_number.': '.$reason, $journalLines, 'order_adjustment', $id) : null;
            $returned = (int) DB::table('order_adjustment_items')->join('order_adjustments as a', 'a.id', '=', 'order_adjustment_items.adjustment_id')->where('a.order_id', $orderId)->sum('quantity');
            $status = $kind === 'cancel' ? 'cancelled' : ($returned === (int) $lines->sum('quantity') ? 'returned' : 'delivered');
            DB::table('sales_orders')->where('order_id', $orderId)->update(['status' => $status, 'updated_at' => now()]);
            $summary = app(OrderPayments::class)->summary($orderId);
            DB::table('order_adjustments')->where('id', $id)->update(['journal_id' => $journal, 'refund_due_cents' => $summary['refund_due']]);
            app(OrderPayments::class)->refreshStatus($orderId);
            DB::table('order_events')->insert(['order_id' => $orderId, 'status' => $status, 'message' => ucfirst($kind).' recorded. Credit: ₱'.number_format($credit / 100, 2).'. Cash refunds are recorded separately.', 'actor_id' => $actor->getKey(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('audit_logs')->insert(['action' => $kind, 'table_name' => 'order_adjustments', 'record_id' => $id, 'user_id' => $actor->getKey(), 'new_values' => json_encode(['order_id' => $orderId, 'credit_cents' => $credit, 'journal_id' => $journal]), 'created_at' => now(), 'updated_at' => now()]);
            return $id;
        });
    }
    private function error(string $message): never { throw ValidationException::withMessages(['adjustment' => $message]); }
}

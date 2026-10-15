<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class OperationalBalances
{
    public function sales(): array
    {
        $payments = $this->payments('sales_order');
        $credits = DB::table('order_adjustments')->select('order_id')->selectRaw('SUM(credit_cents) AS credit')->groupBy('order_id')->get()->keyBy('order_id');
        $result = ['total_amount' => 0, 'paid_amount' => 0, 'outstanding' => 0, 'total_invoices' => 0, 'pending_invoices' => 0, 'overdue_invoices' => 0];
        foreach (DB::table('sales_orders')->where('status', '!=', 'draft')->cursor() as $order) {
            $credit = (int) ($credits[$order->order_id]->credit ?? 0);
            if (in_array($order->status, ['cancelled', 'returned'], true) && !$credit) continue; // Legacy terminal records need separate reconciliation.
            $total = max(0, (int) round($order->grand_total * 100) - $credit);
            $paid = min($total, max(0, $payments[$order->order_id] ?? 0)); $due = $total - $paid;
            $result['total_amount'] += $total / 100; $result['paid_amount'] += $paid / 100; $result['outstanding'] += $due / 100;
            $result['total_invoices']++; if ($due) { $result['pending_invoices']++; if ($order->order_date < now()->subDays(30)->toDateString()) $result['overdue_invoices']++; }
        }
        return $result;
    }
    public function purchases(): array
    {
        $payments = $this->payments('purchase_order');
        $result = ['total_amount' => 0, 'paid_amount' => 0, 'outstanding' => 0, 'total_orders' => 0, 'pending_orders' => 0, 'overdue_orders' => 0];
        foreach (DB::table('purchase_orders')->whereIn('status', ['sent', 'confirmed', 'partially_received', 'completed'])->cursor() as $order) {
            $total = (int) round($order->total_amount * 100); $paid = min($total, max(0, $payments[$order->po_id] ?? 0));
            $result['total_amount'] += $total / 100; $result['paid_amount'] += $paid / 100; $result['outstanding'] += ($total - $paid) / 100; $result['total_orders']++;
            if ($order->status !== 'completed') { $result['pending_orders']++; if ($order->expected_delivery_date && $order->expected_delivery_date < now()->toDateString()) $result['overdue_orders']++; }
        }
        return $result;
    }
    private function payments(string $type): array
    {
        $values = [];
        foreach (DB::table('payments')->where('reference_type', $type)->where('status', 'completed')->select('reference_id', 'kind')->selectRaw('SUM(amount) AS amount')->groupBy('reference_id', 'kind')->get() as $row) $values[$row->reference_id] = ($values[$row->reference_id] ?? 0) + (int) round($row->amount * 100) * ($row->kind === 'refund' ? -1 : 1);
        return $values;
    }
}

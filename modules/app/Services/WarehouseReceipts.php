<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WarehouseReceipts
{
    public function receive(User $actor, int $poId, string $key, array $receipts): int
    {
        app(AccessControl::class)->authorize($actor, 'inventory', 'write');
        Validator::make(compact('key', 'receipts'), ['key' => 'required|uuid', 'receipts' => 'required|array|min:1',
            'receipts.*.inventory_id' => 'required|integer|distinct', 'receipts.*.quantity_received' => 'required|integer|min:0',
            'receipts.*.quality_status' => 'required|in:passed,failed', 'receipts.*.batch_number' => 'nullable|string|max:100',
            'receipts.*.expiry_date' => 'nullable|date_format:Y-m-d', 'receipts.*.notes' => 'nullable|string|max:2000'])->validate();
        $receipts = array_map(fn ($line) => ['inventory_id' => (int) $line['inventory_id'], 'quantity_received' => (int) $line['quantity_received'], 'quality_status' => $line['quality_status'], 'batch_number' => $line['batch_number'] ?? null, 'expiry_date' => ($line['expiry_date'] ?? null) ?: null, 'notes' => $line['notes'] ?? null], $receipts);
        usort($receipts, fn ($a, $b) => $a['inventory_id'] <=> $b['inventory_id']); $hash = hash('sha256', json_encode($receipts));
        return DB::transaction(function () use ($actor, $poId, $key, $receipts, $hash) {
            $order = DB::table('purchase_orders')->where('po_id', $poId)->lockForUpdate()->first(); abort_unless($order, 404);
            if ($batch = DB::table('warehouse_receipt_batches')->where('request_key', $key)->first()) {
                if ((int) $batch->po_id !== $poId || (int) $batch->actor_id !== (int) $actor->getKey() || $batch->payload_hash !== $hash) $this->error('This receiving request was already used.');
                return $batch->id;
            }
            if (!in_array($order->status, ['confirmed', 'partially_received'], true)) $this->error('Receive only confirmed purchase orders. Refresh the order.');
            $items = DB::table('purchase_order_items')->where('po_id', $poId)->get()->groupBy('inventory_id');
            $stockIds = collect($receipts)->pluck('inventory_id');
            DB::table('inventories')->whereIn('inventory_id', $stockIds)->orderBy('inventory_id')->lockForUpdate()->get();
            $batch = DB::table('warehouse_receipt_batches')->insertGetId(['request_key' => $key, 'payload_hash' => $hash, 'po_id' => $poId, 'actor_id' => $actor->getKey(), 'created_at' => now(), 'updated_at' => now()]);
            $units = 0;
            foreach ($receipts as $receipt) {
                $id = $receipt['inventory_id']; $qty = $receipt['quantity_received'];
                if (!isset($items[$id]) || $items[$id]->count() !== 1) $this->error('Receipt inventory must identify one original purchase-order line.');
                if (!$qty) continue;
                $received = DB::table('goods_receipts')->where('po_id', $poId)->where('inventory_id', $id)->where('quality_status', 'passed')->sum('quantity_received');
                if ($received + $qty > $items[$id][0]->quantity) $this->error('Quantity exceeds the remaining purchase-order amount.');
                if ($receipt['quality_status'] === 'passed' && $receipt['expiry_date'] && $receipt['expiry_date'] < now()->toDateString()) $this->error('Expired stock cannot be accepted as passed.');
                $receiptId = DB::table('goods_receipts')->insertGetId($receipt + ['po_id' => $poId, 'receipt_batch_id' => $batch, 'unit_price' => $items[$id][0]->unit_price, 'received_by' => $actor->getKey(), 'date_received' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
                if ($receipt['quality_status'] === 'passed') {
                    DB::table('inventories')->where('inventory_id', $id)->increment('quantity', $qty);
                    DB::table('stock_transactions')->insert(['inventory_id' => $id, 'type' => 'in', 'quantity' => $qty, 'unit_cost' => $items[$id][0]->unit_price, 'reference_type' => 'goods_receipt', 'reference_id' => $receiptId, 'transaction_date' => now(), 'created_by' => $actor->getKey(), 'created_at' => now(), 'updated_at' => now()]);
                }
                $units += $qty;
            }
            if (!$units) $this->error('Enter at least one quantity to receive.');
            $complete = true; $accepted = 0;
            foreach ($items as $id => $lines) {
                $count = (int) DB::table('goods_receipts')->where('po_id', $poId)->where('inventory_id', $id)->where('quality_status', 'passed')->sum('quantity_received');
                $accepted += $count; if ($count < $lines->sum('quantity')) $complete = false;
            }
            DB::table('purchase_orders')->where('po_id', $poId)->update(['status' => $complete ? 'completed' : ($accepted ? 'partially_received' : 'confirmed'), 'updated_at' => now()]);
            DB::table('audit_logs')->insert(['action' => 'receive', 'table_name' => 'warehouse_receipt_batches', 'record_id' => $batch, 'user_id' => $actor->getKey(), 'new_values' => json_encode(['po_id' => $poId, 'units' => $units]), 'created_at' => now(), 'updated_at' => now()]);
            return $batch;
        });
    }
    private function error(string $message): never { throw ValidationException::withMessages(['receipts' => $message]); }
}

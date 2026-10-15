<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierOrders
{
    public function supplierId(): int
    {
        $id = data_get(session('supplier'), 'supplier_id');
        abort_unless($id && DB::table('suppliers')->where('supplier_id', $id)->where('status', 'active')->exists(), 403);
        return (int) $id;
    }

    public function query()
    {
        return DB::table('purchase_orders')->where('supplier_id', $this->supplierId())->where('status', '!=', 'draft');
    }

    public function accept(int $id): void
    {
        $this->change($id, 'accepted');
    }

    public function dispatch(int $id): void
    {
        $this->change($id, 'dispatched');
    }

    private function change(int $id, string $event): void
    {
        DB::transaction(function () use ($id, $event) {
            $supplierId = $this->supplierId();
            $supplier = DB::table('suppliers')->where('supplier_id', $supplierId)->lockForUpdate()->first();
            abort_unless($supplier?->status === 'active', 403);
            $order = $this->query()->where('po_id', $id)->lockForUpdate()->first();
            abort_unless($order, 404);
            if ($event === 'accepted') {
                if ($order->status === 'confirmed') return; // Safe replay.
                if ($order->status !== 'sent') throw ValidationException::withMessages(['order' => 'Only a sent purchase order can be accepted. Refresh the order list.']);
                $changes = ['status' => 'confirmed'];
            } else {
                if ($order->status !== 'confirmed') throw ValidationException::withMessages(['order' => 'Only a confirmed purchase order can be reported as dispatched.']);
                if ($order->supplier_dispatched_at) return;
                $changes = ['supplier_dispatched_at' => now()];
            }
            DB::table('purchase_orders')->where('po_id', $id)->update($changes + ['updated_at' => now()]);
            DB::table('supplier_order_events')->insert([
                'po_id' => $id, 'supplier_id' => $supplierId, 'event' => $event,
                'previous_status' => $order->status, 'status' => $changes['status'] ?? $order->status,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}

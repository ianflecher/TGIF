<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderWorkflow
{
    public const NEXT = ['draft' => 'confirmed', 'confirmed' => 'processing', 'processing' => 'shipped', 'shipped' => 'delivered'];

    public function advance(User $actor, int $orderId, string $expectedStatus): void
    {
        abort_unless($actor->role === 'admin' && !$actor->trashed(), 403);

        DB::transaction(function () use ($actor, $orderId, $expectedStatus) {
            $order = DB::table('sales_orders')->where('order_id', $orderId)->lockForUpdate()->first();
            abort_if(!$order, 404);
            if ($order->status !== $expectedStatus) {
                throw ValidationException::withMessages(['order' => 'This order changed since you opened it. Close and reopen it to review the latest status.']);
            }
            $next = self::NEXT[$order->status] ?? null;
            if (!$next) {
                throw ValidationException::withMessages(['order' => 'This order cannot advance from its current status.']);
            }

            $changed = DB::table('sales_orders')->where('order_id', $orderId)->where('status', $expectedStatus)
                ->update(['status' => $next, 'updated_at' => now()]);
            if ($changed !== 1) {
                throw ValidationException::withMessages(['order' => 'This order has changed. Reopen it to review the latest status.']);
            }
            DB::table('audit_logs')->insert([
                'action' => 'update', 'table_name' => 'sales_orders', 'record_id' => $orderId,
                'old_values' => json_encode(['status' => $order->status], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['status' => $next], JSON_THROW_ON_ERROR),
                'user_id' => $actor->getKey(), 'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}

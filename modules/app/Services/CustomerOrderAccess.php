<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CustomerOrderAccess
{
    public function query()
    {
        abort_unless(auth()->user()?->role === 'customer' && !auth()->user()->trashed(), 403);
        return DB::table('sales_orders')->whereIn('customer_id', DB::table('customers')->where('user_id', auth()->id())->select('customer_id'));
    }

    public function order(int $id): object
    {
        $order = $this->query()->where('order_id', $id)->first();
        abort_if(!$order, 404);
        return $order;
    }

    public static function label(string $status, string $mode = 'delivery'): string
    {
        if ($mode === 'pickup') return match ($status) { 'shipped' => 'Ready for pickup', 'delivered' => 'Collected', default => ucfirst(str_replace('_', ' ', $status)) };
        return ucfirst(str_replace('_', ' ', $status));
    }

    public function reorder(int $id): void
    {
        $order = $this->order($id);
        DB::transaction(function () use ($order) {
            DB::table('users')->where('user_id', auth()->id())->lockForUpdate()->first();
            $items = DB::table('order_items')->where('order_id', $order->order_id)->get();
            if ($items->isEmpty()) throw \Illuminate\Validation\ValidationException::withMessages(['reorder' => 'This order has no items available to repeat.']);
            foreach ($items as $item) app(StorefrontCart::class)->add($item->product_id, $item->quantity, ['size' => $item->size, 'flavor' => $item->flavor, 'variety' => $item->variety]);
        });
    }
}

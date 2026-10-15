<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderPlacement
{
    public function preview(array $fulfillment): array
    {
        $bag = app(StorefrontCart::class)->summary();
        if (!$bag['canCheckout']) throw ValidationException::withMessages(['checkout' => 'Some items are unavailable or your bag is empty. Review your bag before continuing.']);
        $quote = app(FulfillmentRules::class)->quote($bag['total'], $fulfillment);
        $quote['items'] = $bag['items'];
        $quote['fingerprint'] = hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR));
        return $quote;
    }

    public function place(User $user, array $details, array $fulfillment, string $fingerprint, string $token): object
    {
        abort_unless($user->role === 'customer' && !$user->trashed(), 403);
        return DB::transaction(function () use ($user, $details, $fulfillment, $fingerprint, $token) {
            DB::table('users')->where('user_id', $user->getKey())->lockForUpdate()->first();
            $customer = DB::table('customers')->where('user_id', $user->getKey())->first();
            $existing = DB::table('sales_orders')->where('checkout_token', $token)->first();
            if ($existing) {
                abort_unless($customer && $existing->customer_id === $customer->customer_id, 403);
                return $existing;
            }
            $lines = DB::table('cart_items')->where('user_id', $user->getKey())->lockForUpdate()->get();
            DB::table('products')->whereIn('product_id', $lines->pluck('product_id'))->orderBy('product_id')->lockForUpdate()->get();
            $quote = $this->preview($fulfillment);
            if (!hash_equals($quote['fingerprint'], $fingerprint)) {
                throw ValidationException::withMessages(['quote' => 'Prices, availability or fulfillment charges changed. Review the refreshed summary, then place your order again.']);
            }
            $names = explode(' ', trim($details['name']), 2);
            $customerData = ['first_name' => $names[0], 'last_name' => $names[1] ?? '', 'phone' => $details['phone'], 'updated_at' => now()];
            if ($quote['mode'] === 'delivery') $customerData['address'] = $details['address'];
            if ($customer) {
                $customerId = $customer->customer_id;
                DB::table('customers')->where('customer_id', $customerId)->update($customerData);
            } else {
                $customerId = DB::table('customers')->insertGetId($customerData + ['user_id' => $user->getKey(), 'email' => $user->email, 'date_registered' => now()->toDateString(), 'created_at' => now()]);
            }
            $orderId = DB::table('sales_orders')->insertGetId([
                'customer_id' => $customerId, 'order_number' => 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(10)),
                'checkout_token' => $token, 'order_date' => now()->toDateString(), 'status' => 'confirmed',
                'total_amount' => $quote['subtotal'] / 100, 'grand_total' => $quote['total'] / 100, 'delivery_fee' => $quote['fee'] / 100,
                'payment_status' => 'pending', 'payment_method' => 'cash', 'fulfillment_mode' => $quote['mode'],
                'delivery_zone' => $quote['zone'], 'postal_code' => $quote['postal'] ?: null, 'scheduled_for' => $quote['scheduled'],
                'pickup_location' => $quote['pickup'], 'shipping_address' => $quote['mode'] === 'delivery' ? $details['address'] : null,
                'billing_address' => $details['address'] ?: null, 'customer_notes' => trim($details['notes'] ?? '') ?: null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $cost = 0;
            foreach ($quote['items'] as $item) {
                $inventoryId = DB::table('products')->where('product_id', $item['product_id'])->value('inventory_id');
                $unitCost = (int) round((float) DB::table('inventories')->where('inventory_id', $inventoryId)->value('cost_price') * 100);
                $deducted = DB::table('products')->where('product_id', $item['product_id'])->where('status', 'published')->where('stock_quantity', '>=', $item['quantity'])->decrement('stock_quantity', $item['quantity']);
                if (!$deducted) throw ValidationException::withMessages(['checkout' => 'An item just sold out. Your bag is saved; adjust the quantity and try again.']);
                DB::table('products')->where('product_id', $item['product_id'])->increment('sold_count', $item['quantity']);
                DB::table('order_items')->insert($item['options'] + [
                    'order_id' => $orderId, 'product_id' => $item['product_id'], 'product_name' => $item['name'],
                    'quantity' => $item['quantity'], 'price_per_unit' => $item['unit'] / 100, 'unit_price' => $item['unit'] / 100,
                    'subtotal' => $item['total'] / 100, 'total' => $item['total'] / 100, 'final_price' => $item['unit'] / 100,
                    'unit_cost_snapshot' => $unitCost / 100,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $cost += $unitCost * $item['quantity'];
            }
            $this->postSale($orderId, $user->getKey(), $quote['total'], $cost);
            DB::table('order_events')->insert(['order_id' => $orderId, 'status' => 'confirmed', 'message' => 'Your order was received.', 'actor_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('cart_items')->where('user_id', $user->getKey())->delete();
            return DB::table('sales_orders')->where('order_id', $orderId)->first();
        });
    }

    private function account(string $code, string $name, string $type, string $normal): int
    {
        $account = DB::table('chart_of_accounts')->where('account_code', $code)->first();
        if ($account) {
            if (!$account->is_active || $account->account_type !== $type || $account->normal_balance !== $normal) throw ValidationException::withMessages(['checkout' => 'Ordering is temporarily unavailable while the finance setup is reviewed. Your bag is saved.']);
            return $account->account_id;
        }
        DB::table('chart_of_accounts')->insertOrIgnore(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return (int) DB::table('chart_of_accounts')->where('account_code', $code)->value('account_id');
    }

    private function postSale(int $orderId, int $userId, int $total, int $cost): void
    {
        // Preserve the application's existing sales posting convention.
        $ar = $this->account('1100', 'Accounts Receivable', 'asset', 'debit');
        $sales = $this->account('4100', 'Sales Revenue', 'revenue', 'credit');
        $entries = [[$ar, $total, 0], [$sales, 0, $total]];
        if ($cost > 0) {
            $entries[] = [$this->account('5100', 'Cost of Goods Sold', 'expense', 'debit'), $cost, 0];
            $entries[] = [$this->account('1200', 'Inventory', 'asset', 'debit'), 0, $cost];
        }
        $journal = DB::table('journal_entries')->insertGetId([
            'journal_number' => 'JE-'.Str::uuid(), 'entry_date' => now()->toDateString(), 'description' => 'Sales order '.$orderId,
            'created_by' => $userId, 'reference_type' => 'sales_order', 'reference_id' => $orderId, 'status' => 'draft',
            'total_debit' => ($total + $cost) / 100, 'total_credit' => ($total + $cost) / 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($entries as [$account, $debit, $credit]) DB::table('journal_details')->insert(['journal_id' => $journal, 'account_id' => $account, 'debit' => $debit / 100, 'credit' => $credit / 100, 'created_at' => now(), 'updated_at' => now()]);
    }
}

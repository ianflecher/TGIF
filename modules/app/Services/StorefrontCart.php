<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StorefrontCart
{
    private function customer(): bool
    {
        return Auth::check() && Auth::user()->role === 'customer' && !Auth::user()->trashed();
    }

    public function price(object $product, array $options): array
    {
        $clean = [];
        foreach (StorefrontCatalog::options($product) as $key => $choices) {
            $value = $options[$key] ?? null;
            if ($value !== null && $value !== '' && !in_array($value, $choices, true)) {
                throw ValidationException::withMessages(['options.'.$key => 'Choose an available '. $key.'.']);
            }
            $clean[$key] = $value === '' ? null : $value;
        }
        // Preserve the existing catalog rule: each optional selection adds 1%.
        $percent = count(array_filter($clean, fn ($value) => $value !== null));
        $base = (int) round((float) $product->price * 100);
        return ['options' => $clean, 'base' => $base, 'percent' => $percent, 'unit' => (int) round($base * (100 + $percent) / 100)];
    }

    public function add(int $productId, int $quantity = 1, array $options = []): void
    {
        if (Auth::check() && !$this->customer()) {
            throw ValidationException::withMessages(['cart' => 'Use a customer account to place an order.']);
        }
        if ($quantity < 1 || $quantity > 99) {
            throw ValidationException::withMessages(['quantity' => 'Choose a quantity from 1 to 99.']);
        }
        DB::transaction(function () use ($productId, $quantity, $options) {
            if ($this->customer()) DB::table('users')->where('user_id', Auth::id())->lockForUpdate()->first();
            $product = DB::table('products')->where('product_id', $productId)->where('status', 'published')->lockForUpdate()->first();
            if (!$product) throw ValidationException::withMessages(['cart' => 'This item is no longer available.']);
            $price = $this->price($product, $options);
            if (!$this->customer()) {
                $cart = session('storefront.cart', []);
                $existing = collect($cart)->where('product_id', $productId)->sum('quantity');
                $this->checkStock($existing + $quantity, $product);
                $key = hash('sha256', $productId.json_encode($price['options']));
                $cart[$key] = ['product_id' => $productId, 'quantity' => ($cart[$key]['quantity'] ?? 0) + $quantity, 'options' => $price['options']];
                session(['storefront.cart' => $cart]);
                return;
            }
            $lines = DB::table('cart_items')->where('user_id', Auth::id())->where('product_id', $productId);
            $this->checkStock((clone $lines)->sum('quantity') + $quantity, $product);
            foreach ($price['options'] as $key => $value) $lines->where($key, $value);
            $line = $lines->first();
            $count = ($line->quantity ?? 0) + $quantity;
            $data = $price['options'] + [
                'product_name' => $product->product_name, 'quantity' => $count,
                'unit_price' => $price['unit'] / 100, 'total_price' => $price['unit'] * $count / 100,
                'base_price' => $price['base'] / 100, 'price_adjustment_percent' => $price['percent'],
                'final_price' => $price['unit'] / 100, 'updated_at' => now(),
            ];
            if ($line) {
                DB::table('cart_items')->where('id', $line->id)->update($data);
            } else {
                DB::table('cart_items')->insert($data + ['user_id' => Auth::id(), 'product_id' => $productId, 'created_at' => now()]);
            }
        });
    }

    private function checkStock(int $quantity, object $product): void
    {
        if ($quantity > $product->stock_quantity || $quantity > 99) {
            throw ValidationException::withMessages(['cart' => 'Only '.min(99, max(0, $product->stock_quantity)).' of this item can be added to your order.']);
        }
    }

    public function mergeGuest(): void
    {
        if (!$this->customer()) return;
        foreach (session('storefront.cart', []) as $key => $line) {
            try {
                $this->add((int) $line['product_id'], (int) $line['quantity'], $line['options'] ?? []);
                session()->forget('storefront.cart.'.$key);
            } catch (ValidationException $exception) {
                session()->flash('storefront.notice', 'Some guest items are no longer available in the requested quantity. Please review your bag.');
                session()->forget('storefront.cart.'.$key);
            }
        }
    }

    public function summary(): array
    {
        $this->mergeGuest();
        $lines = $this->customer()
            ? DB::table('cart_items')->where('user_id', Auth::id())->get()->map(fn ($line) => ['key' => 'db-'.$line->id, 'product_id' => $line->product_id, 'quantity' => $line->quantity, 'options' => ['size' => $line->size, 'flavor' => $line->flavor, 'variety' => $line->variety]])->all()
            : (Auth::check() ? [] : collect(session('storefront.cart', []))->map(fn ($line, $key) => $line + ['key' => 'guest-'.$key])->values()->all());
        $products = DB::table('products')->whereIn('product_id', array_column($lines, 'product_id'))->get()->keyBy('product_id');
        $quantities = collect($lines)->groupBy('product_id')->map(fn ($group) => $group->sum('quantity'));
        $items = [];
        foreach ($lines as $line) {
            $product = $products->get($line['product_id']);
            if (!$product) continue;
            try { $price = $this->price($product, $line['options']); } catch (ValidationException) { $price = null; }
            $available = $product->status === 'published' && $quantities[$product->product_id] <= $product->stock_quantity && $price !== null;
            $items[] = $line + ['name' => $product->product_name, 'image' => StorefrontCatalog::image($product->images), 'unit' => $price['unit'] ?? 0, 'total' => ($price['unit'] ?? 0) * $line['quantity'], 'available' => $available];
        }
        return ['items' => $items, 'count' => array_sum(array_column($items, 'quantity')), 'total' => array_sum(array_column($items, 'total')), 'canCheckout' => count($items) > 0 && !in_array(false, array_column($items, 'available'), true)];
    }

    public function change(string $key, int $delta): void
    {
        DB::transaction(function () use ($key, $delta) {
            if ($this->customer()) DB::table('users')->where('user_id', Auth::id())->lockForUpdate()->first();
            $this->changeLine($key, $delta);
        });
    }

    private function changeLine(string $key, int $delta): void
    {
        if (!in_array($delta, [-1, 1], true)) abort(422);
        $line = collect($this->summary()['items'])->firstWhere('key', $key);
        abort_if(!$line, 404);
        if ($delta === 1) { $this->add($line['product_id'], 1, $line['options']); return; }
        if ($line['quantity'] <= 1) { $this->remove($key); return; }
        if ($this->customer()) {
            DB::table('cart_items')->where('id', substr($key, 3))->where('user_id', Auth::id())->update([
                'quantity' => $line['quantity'] - 1,
                'unit_price' => $line['unit'] / 100,
                'total_price' => $line['unit'] * ($line['quantity'] - 1) / 100,
                'updated_at' => now(),
            ]);
        } else {
            $cart = session('storefront.cart', []);
            $cart[substr($key, 6)]['quantity']--;
            session(['storefront.cart' => $cart]);
        }
    }

    public function remove(string $key): void
    {
        if ($this->customer() && str_starts_with($key, 'db-')) DB::table('cart_items')->where('id', substr($key, 3))->where('user_id', Auth::id())->delete();
        if (!Auth::check() && str_starts_with($key, 'guest-')) session()->forget('storefront.cart.'.substr($key, 6));
    }

    public function reprice(): void
    {
        $summary = $this->summary();
        if (!$summary['canCheckout']) throw ValidationException::withMessages(['cart' => 'Review unavailable items in your bag before checkout.']);
        if (!$this->customer()) return;
        foreach ($summary['items'] as $item) {
            DB::table('cart_items')->where('id', substr($item['key'], 3))->where('user_id', Auth::id())->update([
                'unit_price' => $item['unit'] / 100, 'final_price' => $item['unit'] / 100,
                'total_price' => $item['total'] / 100, 'updated_at' => now(),
            ]);
        }
    }
}

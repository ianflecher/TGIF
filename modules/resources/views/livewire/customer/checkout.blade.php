<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.customerapp')] class extends Component
{
    #[\Livewire\Attributes\Locked]
    public $cartItems = [];
    #[\Livewire\Attributes\Locked]
    public $total = 0;
    
    // Simple customer info
    public $name = '';
    public $phone = '';
    public $address = '';
    public $notes = '';
    
    // Order confirmation
    #[\Livewire\Attributes\Locked]
    public $showConfirmation = false;
    #[\Livewire\Attributes\Locked]
    public $orderNumber = '';
    #[\Livewire\Attributes\Locked]
    public $orderDetails = [];
    
    public function mount()
    {
        if (!Auth::check()) {
            $this->redirect(route('login'), navigate: true);
            return;
        }
        
        abort_unless(Auth::user()->role === 'customer' && !Auth::user()->trashed(), 403);
        $user = Auth::user();
        $this->name = $user->full_name ?? '';
        $this->address = (string) session('storefront.address', '');
        
        // Ensure accounting accounts exist
        $this->ensureAccountsExist();
        
        $this->loadCart();
    }
    
    public function loadCart()
    {
        if (!Auth::check()) {
            return;
        }
        
        abort_unless(Auth::user()->role === 'customer' && !Auth::user()->trashed(), 403);
        $userId = Auth::user()->user_id;
        $bag = app(\App\Services\StorefrontCart::class);
        $summary = $bag->summary();
        if ($summary['count'] && !$summary['canCheckout']) {
            $this->dispatch('show-toast', type: 'error', message: 'Please review unavailable items in your bag.');
            $this->redirect(route('customer.cart.index'));
            return;
        }
        if ($summary['count']) $bag->reprice();
        
        if (!DB::getSchemaBuilder()->hasTable('cart_items')) {
            $this->dispatch('show-toast', type: 'error', message: 'Cart system is not available.');
            $this->redirect(route('customer.products.index'), navigate: true);
            return;
        }
        
        $cartItems = DB::table('cart_items')
            ->where('user_id', $userId)
            ->get();
        
        if ($cartItems->isEmpty()) {
            $this->dispatch('show-toast', type: 'error', message: 'Your cart is empty.');
            $this->redirect(route('customer.cart.index'), navigate: true);
            return;
        }
        
        $this->cartItems = [];
        $this->total = 0;
        
        foreach ($cartItems as $cartItem) {
            $product = DB::table('products')
                ->where('product_id', $cartItem->product_id)
                ->first();
            
            if ($product) {
                $itemTotal = $cartItem->unit_price * $cartItem->quantity;
                
                $this->cartItems[] = [
                    'cart_item_id' => $cartItem->id,
                    'product_id' => $product->product_id,
                    'name' => $product->product_name,
                    'image' => \App\Services\StorefrontCatalog::image($product->images),
                    'price' => $cartItem->unit_price,
                    'quantity' => $cartItem->quantity,
                    'total' => $itemTotal,
                    'size' => $cartItem->size,
                    'flavor' => $cartItem->flavor,
                    'variety' => $cartItem->variety,
                    'base_price' => $cartItem->base_price,
                    'price_adjustment_percent' => $cartItem->price_adjustment_percent,
                    'final_price' => $cartItem->final_price,
                ];
                $this->total += $itemTotal;
            }
        }
    }
    
    // Helper function to get or create account ID
    private function getOrCreateAccountId($accountName, $accountType, $accountCode = null, $normalBalance = null)
    {
        // Set default normal balance based on account type
        if (!$normalBalance) {
            $normalBalance = $this->getDefaultNormalBalance($accountType);
        }
        
        // Try to get existing account
        $account = DB::table('chart_of_accounts')
            ->where(function($query) use ($accountName, $accountCode) {
                $query->where('account_name', $accountName);
                if ($accountCode) {
                    $query->orWhere('account_code', $accountCode);
                }
            })
            ->where('account_type', $accountType)
            ->where('is_active', 1)
            ->first();
        
        if ($account) {
            return $account->account_id;
        }
        
        // If account doesn't exist, create it
        // Generate account code if not provided
        if (!$accountCode) {
            $accountCode = $this->generateAccountCode($accountType);
        }
        
        $accountId = DB::table('chart_of_accounts')->insertGetId([
            'account_code' => $accountCode,
            'account_name' => $accountName,
            'account_type' => $accountType,
            'normal_balance' => $normalBalance,
            'parent_account_id' => null,
            'description' => 'Auto-created for sales order system',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return $accountId;
    }
    
    // Helper to get default normal balance based on account type
    private function getDefaultNormalBalance($accountType)
    {
        switch ($accountType) {
            case 'asset':
            case 'expense':
                return 'debit';
            case 'liability':
            case 'equity':
            case 'revenue':
                return 'credit';
            default:
                return 'debit';
        }
    }
    
    // Helper to generate account code based on account type
    private function generateAccountCode($accountType)
    {
        // Get the highest account code for this type
        $prefixMap = [
            'asset' => '1',
            'liability' => '2',
            'equity' => '3',
            'revenue' => '4',
            'expense' => '5',
        ];
        
        $prefix = $prefixMap[$accountType] ?? '9';
        
        // Find the highest existing account code with this prefix
        $highestCode = DB::table('chart_of_accounts')
            ->where('account_code', 'like', $prefix . '%')
            ->orderBy('account_code', 'desc')
            ->value('account_code');
        
        if ($highestCode) {
            $lastNumber = intval(substr($highestCode, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            return $prefix . $newNumber;
        }
        
        return $prefix . '0001';
    }
    
    // Ensure required accounts exist
    private function ensureAccountsExist()
    {
        // Check if accounting tables exist
        if (!DB::getSchemaBuilder()->hasTable('chart_of_accounts')) {
            return;
        }
        
        $requiredAccounts = [
            ['Accounts Receivable', 'asset', '1100'],
            ['Sales Revenue', 'revenue', '4100'],
            ['Cost of Goods Sold', 'expense', '5100'],
            ['Inventory', 'asset', '1200'],
        ];
        
        foreach ($requiredAccounts as $account) {
            $this->getOrCreateAccountId($account[0], $account[1], $account[2]);
        }
    }
    
    #[\Livewire\Attributes\On('delivery-address-updated')]
    public function refreshDeliveryAddress(): void
    {
        $this->address = (string) session('storefront.address', '');
    }

    public function placeOrder()
    {
        abort_unless(Auth::user()?->role === 'customer' && !Auth::user()->trashed(), 403);
        $this->validate([
            'name' => 'required|string|max:100', 'phone' => 'required|string|max:50',
            'address' => 'required|string|min:10|max:500', 'notes' => 'nullable|string|max:1000',
        ]);
        // Simple validation
        if (empty($this->name) || empty($this->phone) || empty($this->address)) {
            $this->dispatch('show-toast', type: 'error', message: 'Please fill in all required fields.');
            return;
        }

        // Validate phone format (at least 10 digits)
        $phoneDigits = preg_replace('/\D/', '', $this->phone);
        if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
            $this->dispatch('show-toast', type: 'error', message: 'Please enter a valid phone number (10 to 15 digits).');
            return;
        }

        // Reload authoritative cart data; Livewire public properties are client input.
        $this->cartItems = [];
        $this->total = 0;
        $this->loadCart();
        if (empty($this->cartItems)) {
            return;
        }

        // Check stock
        foreach ($this->cartItems as $item) {
            $product = DB::table('products')
                ->where('product_id', $item['product_id'])
                ->first();

            if (!$product) {
                $this->dispatch('show-toast', type: 'error', message: $item['name'] . ' is no longer available.');
                return;
            }

            if ($product->stock_quantity < $item['quantity']) {
                $this->dispatch('show-toast', type: 'error', message: $item['name'] . ' is out of stock. Only ' . $product->stock_quantity . ' left.');
                return;
            }
        }

        try {
            DB::beginTransaction();

            $userId = Auth::user()->user_id;
            DB::table('users')->where('user_id', $userId)->lockForUpdate()->first();
            DB::table('cart_items')->where('user_id', $userId)->lockForUpdate()->get();
            $this->cartItems = [];
            $this->total = 0;
            $this->loadCart();
            if (empty($this->cartItems)) {
                throw new \RuntimeException('Your cart is empty or has already been checked out.');
            }
            $this->orderNumber = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(10));

            // Handle customer record
            $customerId = null;
            
            // Check if customer exists for this user
            $customer = DB::table('customers')
                ->where('user_id', $userId)
                ->first();

            if ($customer) {
                $customerId = $customer->customer_id;
                
                // Split name into first and last name
                $nameParts = explode(' ', $this->name, 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';
                
                // Update customer info
                DB::table('customers')
                    ->where('customer_id', $customerId)
                    ->update([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $this->phone,
                        'address' => $this->address,
                        'updated_at' => now(),
                    ]);
            } else {
                // Create new customer record
                // Split name into first and last name
                $nameParts = explode(' ', $this->name, 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';
                
                // Get user email
                $user = DB::table('users')
                    ->where('user_id', $userId)
                    ->first();
                
                $customerId = DB::table('customers')->insertGetId([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $user->email,
                    'phone' => $this->phone,
                    'address' => $this->address,
                    'date_registered' => now()->format('Y-m-d'),
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insert into sales_orders
            $orderId = DB::table('sales_orders')->insertGetId([
                'customer_id' => $customerId,
                'order_number' => $this->orderNumber,
                'order_date' => now()->format('Y-m-d'),
                'delivery_date' => null,
                'total_amount' => $this->total,
                'tax_amount' => 0.00,
                'discount_amount' => 0.00,
                'grand_total' => $this->total,
                'payment_method' => 'cash',
                'payment_status' => 'pending',
                'status' => 'confirmed',
                'shipping_address' => $this->address,
                'billing_address' => $this->address,
                'customer_notes' => trim($this->notes) ?: null,
                'sales_rep_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert order items
            $this->orderDetails = [];
            
            // Track cost of goods sold
            $totalCOGS = 0;
            
            foreach ($this->cartItems as $item) {
                // Get product cost price for COGS calculation
                $product = DB::table('products')
                    ->where('product_id', $item['product_id'])
                    ->first();
                
                $costPrice = $product->cost_price ?? 0;
                $itemCOGS = $costPrice * $item['quantity'];
                $totalCOGS += $itemCOGS;
                
                // Insert order item with product_name
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'size' => $item['size'] ?? null,
                    'flavor' => $item['flavor'] ?? null,
                    'variety' => $item['variety'] ?? null,
                    'quantity' => $item['quantity'],
                    'price_per_unit' => $item['price'],
                    'subtotal' => $item['total'],
                    'total' => $item['total'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Decrement stock
                $deducted = DB::table('products')
                    ->where('product_id', $item['product_id'])
                    ->where('stock_quantity', '>=', $item['quantity'])
                    ->decrement('stock_quantity', $item['quantity']);
                if (!$deducted) {
                    throw new \RuntimeException('Insufficient stock. Refresh your cart.');
                }
                
                $this->orderDetails[] = [
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['total'],
                    'size' => $item['size'],
                    'flavor' => $item['flavor'],
                    'variety' => $item['variety'],
                    'price_adjustment_percent' => $item['price_adjustment_percent'] ?? 0,
                ];
            }

            // Clear cart
            DB::table('cart_items')
                ->where('user_id', $userId)
                ->delete();
            
            // ================================================
            // CREATE ACCOUNTING JOURNAL ENTRIES
            // ================================================
            if (DB::getSchemaBuilder()->hasTable('chart_of_accounts') &&
                DB::getSchemaBuilder()->hasTable('journal_entries') &&
                DB::getSchemaBuilder()->hasTable('journal_details')) {
                
                // Generate journal number
                $journalNumber = 'JE-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(10));
                
                // Get or create necessary accounts
                $accounts = [
                    'accounts_receivable' => $this->getOrCreateAccountId('Accounts Receivable', 'asset', '1100'),
                    'sales_revenue' => $this->getOrCreateAccountId('Sales Revenue', 'revenue', '4100'),
                    'cost_of_goods_sold' => $this->getOrCreateAccountId('Cost of Goods Sold', 'expense', '5100'),
                    'inventory' => $this->getOrCreateAccountId('Inventory', 'asset', '1200'),
                ];
                
                // Log for debugging
                \Log::info('Creating journal entry for order #' . $this->orderNumber);
                \Log::info('Account IDs: ', $accounts);
                
                // Create journal entry
                $journalId = DB::table('journal_entries')->insertGetId([
                    'entry_date' => now()->format('Y-m-d'),
                    'journal_number' => $journalNumber,
                    'description' => 'Sales order #' . $this->orderNumber . ' for customer ' . $this->name,
                    'created_by' => $userId,
                    'reference_type' => 'sales_order',
                    'reference_id' => $orderId,
                    'status' => 'posted',
                    'total_debit' => $this->total + $totalCOGS,
                    'total_credit' => $this->total + $totalCOGS,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Log journal ID
                \Log::info('Journal entry created with ID: ' . $journalId);
                
                // Create journal details (accounting entries)
                // Entry 1: Debit Accounts Receivable, Credit Sales Revenue
                DB::table('journal_details')->insert([
                    'journal_id' => $journalId,
                    'account_id' => $accounts['accounts_receivable'],
                    'debit' => $this->total,
                    'credit' => 0.00,
                    'description' => 'Sales on account for order #' . $this->orderNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                DB::table('journal_details')->insert([
                    'journal_id' => $journalId,
                    'account_id' => $accounts['sales_revenue'],
                    'debit' => 0.00,
                    'credit' => $this->total,
                    'description' => 'Sales revenue from order #' . $this->orderNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Entry 2: Debit Cost of Goods Sold, Credit Inventory (only if there's COGS)
                if ($totalCOGS > 0) {
                    DB::table('journal_details')->insert([
                        'journal_id' => $journalId,
                        'account_id' => $accounts['cost_of_goods_sold'],
                        'debit' => $totalCOGS,
                        'credit' => 0.00,
                        'description' => 'COGS for order #' . $this->orderNumber,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    DB::table('journal_details')->insert([
                        'journal_id' => $journalId,
                        'account_id' => $accounts['inventory'],
                        'debit' => 0.00,
                        'credit' => $totalCOGS,
                        'description' => 'Inventory reduction for order #' . $this->orderNumber,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                
                \Log::info('Journal details created for journal ID: ' . $journalId);
            } else {
                \Log::warning('Accounting tables do not exist. Skipping journal entries.');
            }
            // ================================================
            
            DB::commit();
            
            // Show confirmation
            $this->showConfirmation = true;
            
            // Dispatch cart update
            $this->dispatch('cart-updated');
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Order placement error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            $this->dispatch('show-toast', type: 'error', message: 'Failed to place order. Please try again.');
        }
    }
    
    public function continueShopping()
    {
        $this->redirect(route('customer.products.index'), navigate: true);
    }
}
?>
<div class="sf-checkout" x-data="{ notice: '' }" @show-toast.window="notice = $event.detail.message">
    <div x-show="notice" x-cloak class="sf-alert" role="alert" x-text="notice"></div>
    @if($showConfirmation)
        <section class="sf-order-confirmation"><span class="sf-confirmation-icon" aria-hidden="true">✓</span><p class="sf-kicker">A GOOD DAY IS ON THE MENU</p><h1>You're on the list<span>!</span></h1><p>Your order has been placed. Keep your order number handy.</p><strong class="sf-order-number">{{ $orderNumber }}</strong><div class="sf-confirmation-items">@foreach($orderDetails as $item)<div><span>{{ $item['quantity'] }} × {{ $item['name'] }}</span><strong>₱{{ number_format($item['total'], 2) }}</strong></div>@endforeach<div class="sf-confirmation-total"><span>Order total</span><strong>₱{{ number_format($total, 2) }}</strong></div></div><p>Cash on delivery · Payment is pending until collected.</p><div class="sf-confirmation-links"><a href="{{ route('customer.orders.index') }}" class="sf-button">View my orders ↗</a><a href="{{ route('products.index') }}" class="sf-text-link">Back to the menu</a></div></section>
    @else
        <div class="sf-section-heading"><div><p class="sf-kicker">ONE LAST THING BEFORE THE GOOD STUFF</p><h1>Make it official<span>.</span></h1><p>Review your bag and tell us where it's going.</p></div><a href="{{ route('customer.cart.index') }}" class="sf-text-link">← Back to your bag</a></div>
        <form wire:submit="placeOrder" class="sf-checkout-grid">
            <div>
                <section class="sf-checkout-card"><div class="sf-checkout-card-title"><span>01</span><div><h2>Your details</h2><p>So we know who to look for.</p></div></div><div class="sf-checkout-fields"><label>Full name<input wire:model="name" autocomplete="name" required maxlength="100">@error('name')<span class="sf-form-error">{{ $message }}</span>@enderror</label><label>Mobile number<input wire:model="phone" autocomplete="tel" type="tel" required maxlength="50" placeholder="09XX XXX XXXX">@error('phone')<span class="sf-form-error">{{ $message }}</span>@enderror</label></div></section>
                <section class="sf-checkout-card"><div class="sf-checkout-card-title"><span>02</span><div><h2>Where to?</h2><p>The details make all the difference.</p></div></div><div class="sf-checkout-fields"><label class="sf-field-wide">Delivery address<textarea wire:model="address" autocomplete="street-address" required minlength="10" maxlength="500" rows="3" placeholder="House number, street, barangay and city"></textarea>@error('address')<span class="sf-form-error">{{ $message }}</span>@enderror</label><label class="sf-field-wide">A little note <small>optional</small><textarea wire:model="notes" maxlength="1000" rows="2" placeholder="Delivery landmark or a note about your order"></textarea>@error('notes')<span class="sf-form-error">{{ $message }}</span>@enderror</label></div></section>
                <section class="sf-checkout-card"><div class="sf-checkout-card-title"><span>03</span><div><h2>Payment</h2><p>Simple and familiar.</p></div></div><div class="sf-payment-choice"><span aria-hidden="true">◉</span><div><strong>Cash on delivery</strong><p>Pay when you receive your order. Please prepare the amount shown in your summary.</p></div></div></section>
            </div>
            <aside class="sf-checkout-summary"><div class="sf-checkout-summary-heading"><h2>Your good-food bag</h2><a href="{{ route('customer.cart.index') }}">Edit</a></div>@foreach($cartItems as $item)<div class="sf-checkout-line"><img src="{{ $item['image'] }}" alt="{{ $item['name'] }}"><div><h3>{{ $item['name'] }}</h3><p>{{ implode(' · ', array_filter([$item['size'], $item['flavor'], $item['variety']])) ?: 'Original selection' }}</p><span>Qty {{ $item['quantity'] }}</span></div><strong>₱{{ number_format($item['total'], 2) }}</strong></div>@endforeach<div class="sf-checkout-total"><span>Total to pay</span><strong>₱{{ number_format($total, 2) }}</strong></div><p class="sf-checkout-note">Your total matches the items in your bag. Payment will be collected on delivery.</p><button class="sf-button sf-full" type="submit" wire:loading.attr="disabled" wire:target="placeOrder" @disabled(empty($cartItems))><span wire:loading.remove wire:target="placeOrder">Place my order</span><span wire:loading wire:target="placeOrder">Placing your order…</span><span>↗</span></button><p class="sf-checkout-note">We'll confirm availability when you place your order.</p></aside>
        </form>
    @endif
</div>

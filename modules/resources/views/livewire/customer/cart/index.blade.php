<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.customerapp')] class extends Component
{
    public $cartItems = [];
    public $subtotal = 0;
    public $tax = 0;
    public $shipping = 0;
    public $total = 0;
    
    public function mount()
    {
        $this->loadCart();
    }

    public function loadCart()
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            $this->cartItems = [];
            return;
        }
        
        $userId = Auth::user()->user_id;
        
        // Try to get cart from session first (since we're using session-based cart)
        $cart = session()->get('cart', []);
        
        if (isset($cart[$userId])) {
            // Use session cart
            $this->loadCartFromSession($userId, $cart[$userId]);
        } else {
            // Fallback to database cart if session is empty
            $this->loadCartFromDatabase($userId);
        }
    }

    private function loadCartFromSession($userId, $sessionCart)
    {
        $this->cartItems = [];
        $this->subtotal = 0;
        
        foreach ($sessionCart as $key => $cartItem) {
            // Get product info from database
            $product = DB::table('products')
                ->where('product_id', $cartItem['product_id'])
                ->first();
            
            if ($product) {
                // Get image
                $image = null;
                if (!empty($cartItem['image'])) {
                    $image = $cartItem['image'];
                } elseif ($product->images) {
                    try {
                        $images = json_decode($product->images, true);
                        $image = is_array($images) && count($images) > 0 ? $images[0] : null;
                    } catch (\Exception $e) {
                        $image = null;
                    }
                }
                
                $itemTotal = $cartItem['total_price'];
                
                $this->cartItems[] = [
                    'cart_key' => $key,
                    'product_id' => $product->product_id,
                    'name' => $product->product_name,
                    'price' => $cartItem['unit_price'],
                    'quantity' => $cartItem['quantity'],
                    'image' => $image,
                    'total' => $itemTotal,
                    'stock_quantity' => $product->stock_quantity ?? 0,
                    // Add the selected options
                    'size' => $cartItem['size'] ?? null,
                    'flavor' => $cartItem['flavor'] ?? null,
                    'variety' => $cartItem['variety'] ?? null,
                    'base_price' => $cartItem['base_price'] ?? $cartItem['unit_price'],
                    'price_adjustment_percent' => $cartItem['price_adjustment_percent'] ?? 0,
                    'final_price' => $cartItem['final_price'] ?? $cartItem['total_price'],
                    'is_session' => true
                ];
                $this->subtotal += $itemTotal;
            }
        }
        
        $this->calculateTotals();
    }

    private function loadCartFromDatabase($userId)
    {
        // Check if cart_items table exists
        if (!DB::getSchemaBuilder()->hasTable('cart_items')) {
            $this->cartItems = [];
            return;
        }
        
        // Get cart items from database
        $cartItems = DB::table('cart_items')
            ->where('user_id', $userId)
            ->get();
        
        $this->cartItems = [];
        $this->subtotal = 0;
        
        foreach ($cartItems as $cartItem) {
            $product = DB::table('products')
                ->where('product_id', $cartItem->product_id)
                ->first();
            
            if ($product) {
                // Get image
                $image = null;
                if ($product->images) {
                    try {
                        $images = json_decode($product->images, true);
                        $image = is_array($images) && count($images) > 0 ? $images[0] : null;
                    } catch (\Exception $e) {
                        $image = null;
                    }
                }
                
                $itemTotal = $cartItem->unit_price * $cartItem->quantity;
                
                $this->cartItems[] = [
                    'cart_item_id' => $cartItem->id,
                    'product_id' => $product->product_id,
                    'name' => $cartItem->product_name ?? $product->product_name,
                    'price' => $cartItem->unit_price,
                    'quantity' => $cartItem->quantity,
                    'image' => $image,
                    'total' => $itemTotal,
                    'stock_quantity' => $product->stock_quantity ?? 0,
                    // Add the selected options (check if columns exist)
                    'size' => $cartItem->size ?? null,
                    'flavor' => $cartItem->flavor ?? null,
                    'variety' => $cartItem->variety ?? null,
                    'base_price' => $cartItem->base_price ?? $cartItem->unit_price,
                    'price_adjustment_percent' => $cartItem->price_adjustment_percent ?? 0,
                    'final_price' => $cartItem->final_price ?? $itemTotal,
                    'is_session' => false
                ];
                $this->subtotal += $itemTotal;
            }
        }
        
        $this->calculateTotals();
    }

    private function calculateTotals()
    {
        // Calculate totals
        $this->tax = $this->subtotal * 0.08; // 8% tax
        $this->shipping = $this->subtotal > 100 ? 0 : 10; // Free shipping over $100
        $this->total = $this->subtotal + $this->tax + $this->shipping;
        
        \Log::info('Cart loaded', [
            'items_count' => count($this->cartItems),
            'subtotal' => $this->subtotal,
            'total' => $this->total
        ]);
    }

    public function updateQuantity($cartKey, $action)
    {
        if (!Auth::check()) {
            return;
        }
        
        $userId = Auth::user()->user_id;
        $cart = session()->get('cart', []);
        
        if (!isset($cart[$userId][$cartKey])) {
            return;
        }
        
        $cartItem = $cart[$userId][$cartKey];
        $productId = $cartItem['product_id'];
        
        // Get product to check stock
        $product = DB::table('products')
            ->where('product_id', $productId)
            ->first();
        
        if (!$product) {
            unset($cart[$userId][$cartKey]);
            session()->put('cart', $cart);
            $this->loadCart();
            return;
        }
        
        if ($action === 'increase') {
            if ($product->stock_quantity > 0) {
                $cart[$userId][$cartKey]['quantity'] += 1;
                $cart[$userId][$cartKey]['total_price'] = $cart[$userId][$cartKey]['quantity'] * $cartItem['unit_price'];
                
                // Decrease stock
                DB::table('products')
                    ->where('product_id', $productId)
                    ->decrement('stock_quantity');
            } else {
                session()->flash('error', 'Insufficient stock available.');
                return;
            }
        } elseif ($action === 'decrease') {
            if ($cartItem['quantity'] > 1) {
                $cart[$userId][$cartKey]['quantity'] -= 1;
                $cart[$userId][$cartKey]['total_price'] = $cart[$userId][$cartKey]['quantity'] * $cartItem['unit_price'];
                
                // Increase stock
                DB::table('products')
                    ->where('product_id', $productId)
                    ->increment('stock_quantity');
            } else {
                // Remove item if quantity becomes 0
                $this->removeItem($cartKey);
                return;
            }
        }
        
        session()->put('cart', $cart);
        $this->loadCart();
        $this->dispatch('cart-updated');
    }

    public function removeItem($cartKey)
    {
        if (!Auth::check()) {
            return;
        }
        
        $userId = Auth::user()->user_id;
        $cart = session()->get('cart', []);
        
        if (isset($cart[$userId][$cartKey])) {
            $cartItem = $cart[$userId][$cartKey];
            $productId = $cartItem['product_id'];
            
            // Restore stock
            DB::table('products')
                ->where('product_id', $productId)
                ->increment('stock_quantity', $cartItem['quantity']);
            
            // Remove from cart
            unset($cart[$userId][$cartKey]);
            
            // If user's cart is empty, remove the user's entry
            if (empty($cart[$userId])) {
                unset($cart[$userId]);
            }
            
            session()->put('cart', $cart);
        }
        
        $this->loadCart();
        $this->dispatch('cart-updated');
    }

    public function clearCart()
    {
        if (!Auth::check()) {
            return;
        }
        
        $userId = Auth::user()->user_id;
        $cart = session()->get('cart', []);
        
        if (isset($cart[$userId])) {
            // Restore stock for all items
            foreach ($cart[$userId] as $cartItem) {
                DB::table('products')
                    ->where('product_id', $cartItem['product_id'])
                    ->increment('stock_quantity', $cartItem['quantity']);
            }
            
            // Clear user's cart
            unset($cart[$userId]);
            session()->put('cart', $cart);
        }
        
        $this->loadCart();
        $this->dispatch('cart-updated');
    }

    public function proceedToCheckout()
    {
        if (empty($this->cartItems)) {
            session()->flash('error', 'Your cart is empty.');
            return;
        }
        
        // Check stock availability
        foreach ($this->cartItems as $item) {
            if ($item['stock_quantity'] < 0) {
                session()->flash('error', $item['name'] . ' is out of stock. Please remove it from your cart.');
                return;
            }
        }
        
        return redirect()->route('customer.checkout');
    }
}
?>

<div>
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-2">Shopping Cart</h1>
        <p class="text-gray-600 mb-6">Review your items and proceed to checkout</p>
        
        @php
            $cartItems = $this->cartItems;
            $cartCount = count($cartItems);
        @endphp
        
        @if($cartCount > 0)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Cart Items -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold">Your Items ({{ $cartCount }})</h2>
                            <button wire:click="clearCart" 
                                    wire:confirm="Are you sure you want to clear your cart? This will restore all items to stock."
                                    class="text-red-600 text-sm hover:text-red-800 font-medium flex items-center gap-1">
                                <i class="fas fa-trash"></i>
                                Clear Cart
                            </button>
                        </div>
                        
                        <div class="space-y-4">
                            @foreach($cartItems as $item)
                                <div class="flex items-center border border-gray-200 rounded-lg p-4 hover:border-green-300 transition-colors">
                                    <!-- Product Image -->
                                    <div class="w-24 h-24 bg-gray-100 rounded-lg mr-4 overflow-hidden flex-shrink-0">
                                        @if($item['image'])
                                            <img src="{{ asset('storage/' . $item['image']) }}" 
                                                 class="w-full h-full object-cover" 
                                                 alt="{{ $item['name'] }}"
                                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9IiNmMWY1ZjkiLz48dGV4dCB4PSI1MCUiIHk9IjUwJSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEyIiBmaWxsPSIjNzM3MzczIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+'">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-green-50 to-green-100">
                                                <i class="fas fa-french-fries text-2xl text-green-400"></i>
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <!-- Product Info -->
                                    <div class="flex-1">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h3 class="font-semibold text-lg text-gray-800">{{ $item['name'] }}</h3>
                                                
                                                <!-- Display selected options -->
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    @if($item['size'])
                                                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">
                                                            <i class="fas fa-expand-alt text-xs"></i>
                                                            Size: {{ $item['size'] }}
                                                        </span>
                                                    @endif
                                                    
                                                    @if($item['flavor'])
                                                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full font-medium">
                                                            <i class="fas fa-utensils text-xs"></i>
                                                            Flavor: {{ $item['flavor'] }}
                                                        </span>
                                                    @endif
                                                    
                                                    @if($item['variety'])
                                                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-100 text-indigo-800 text-xs rounded-full font-medium">
                                                            <i class="fas fa-layer-group text-xs"></i>
                                                            Variety: {{ $item['variety'] }}
                                                        </span>
                                                    @endif
                                                    
                                                    @if($item['price_adjustment_percent'] > 0)
                                                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full font-medium">
                                                            <i class="fas fa-percentage text-xs"></i>
                                                            +{{ $item['price_adjustment_percent'] }}%
                                                        </span>
                                                    @endif
                                                </div>
                                                
                                                <!-- Price breakdown -->
                                                <div class="mt-2 flex items-center gap-4">
                                                    @if($item['base_price'] != $item['price'])
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-gray-500 line-through text-sm">
                                                                ₱{{ number_format($item['base_price'], 2) }}
                                                            </span>
                                                            <span class="text-green-600 font-medium">
                                                                ₱{{ number_format($item['price'], 2) }}
                                                            </span>
                                                        </div>
                                                    @else
                                                        <p class="text-green-600 font-medium">
                                                            ₱{{ number_format($item['price'], 2) }}
                                                        </p>
                                                    @endif
                                                    
                                                    @if($item['stock_quantity'] <= 0)
                                                        <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full font-medium">Out of Stock</span>
                                                    @elseif($item['stock_quantity'] <= 5)
                                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full font-medium">Low Stock</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <p class="font-bold text-gray-900 text-lg">₱{{ number_format($item['total'], 2) }}</p>
                                        </div>
                                        
                                        <!-- Quantity Controls -->
                                        <div class="flex items-center mt-4">
                                            <div class="flex items-center border border-gray-300 rounded-lg">
                                                <button wire:click="updateQuantity('{{ $item['cart_key'] ?? $item['cart_item_id'] }}', 'decrease')" 
                                                        class="px-3 py-1 hover:bg-gray-100 rounded-l-lg transition-colors"
                                                        {{ $item['quantity'] <= 1 ? 'disabled' : '' }}>
                                                    <i class="fas fa-minus text-sm {{ $item['quantity'] <= 1 ? 'text-gray-400' : '' }}"></i>
                                                </button>
                                                <span class="px-4 py-1 border-x border-gray-300 font-medium">{{ $item['quantity'] }}</span>
                                                <button wire:click="updateQuantity('{{ $item['cart_key'] ?? $item['cart_item_id'] }}', 'increase')" 
                                                        class="px-3 py-1 hover:bg-gray-100 rounded-r-lg transition-colors"
                                                        {{ $item['stock_quantity'] <= 0 ? 'disabled' : '' }}>
                                                    <i class="fas fa-plus text-sm {{ $item['stock_quantity'] <= 0 ? 'text-gray-400' : '' }}"></i>
                                                </button>
                                            </div>
                                            <button wire:click="removeItem('{{ $item['cart_key'] ?? $item['cart_item_id'] }}')" 
                                                    wire:confirm="Remove this item from cart? Stock will be restored."
                                                    class="ml-6 text-red-600 hover:text-red-800 font-medium flex items-center gap-1">
                                                <i class="fas fa-trash-alt"></i>
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-md p-6 sticky top-6">
                        <h2 class="text-xl font-semibold mb-6">Order Summary</h2>
                        
                        <!-- Order Details -->
                        <div class="space-y-3 mb-6">
                            <div class="flex justify-between text-gray-600">
                                <span>Subtotal ({{ $cartCount }} item{{ $cartCount > 1 ? 's' : '' }})</span>
                                <span class="font-medium">₱{{ number_format($subtotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Tax (8%)</span>
                                <span class="font-medium">₱{{ number_format($tax, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Shipping</span>
                                <span class="font-medium">
                                    @if($shipping > 0)
                                        ₱{{ number_format($shipping, 2) }}
                                    @else
                                        <span class="text-green-600 font-semibold">FREE</span>
                                    @endif
                                </span>
                            </div>
                            
                            <!-- Total -->
                            <div class="border-t border-gray-300 pt-4 mt-4">
                                <div class="flex justify-between text-lg font-bold">
                                    <span>Total</span>
                                    <span class="text-green-600">₱{{ number_format($total, 2) }}</span>
                                </div>
                                <p class="text-gray-500 text-sm mt-1">Inclusive of all taxes</p>
                            </div>
                        </div>
                        
                        <!-- Checkout Button -->
                        <button wire:click="proceedToCheckout" 
                                class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 transition font-medium text-lg flex items-center justify-center gap-2">
                            <i class="fas fa-lock"></i>
                            Proceed to Checkout
                        </button>
                        
                        <!-- Continue Shopping -->
                        <a href="{{ route('customer.products.index') }}" 
                           class="w-full mt-4 border-2 border-green-600 text-green-600 py-3 rounded-lg hover:bg-green-50 transition font-medium text-center block">
                            Continue Shopping
                        </a>
                        
                        <!-- Help Text -->
                        <div class="mt-6 p-3 bg-green-50 rounded-lg border border-green-200">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-info-circle text-green-600 mt-1"></i>
                                <div>
                                    <p class="text-sm text-gray-700">
                                        <span class="font-semibold">Free shipping</span> on orders over ₱100
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">Need help? <a href="{{ route('customer.support.index') }}" class="text-green-600 hover:underline">Contact Support</a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Empty Cart State -->
            <div class="max-w-md mx-auto bg-white rounded-xl shadow-md p-12 text-center">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-shopping-cart text-3xl text-green-600"></i>
                </div>
                <h2 class="text-2xl font-semibold text-gray-800 mb-3">Your cart is empty</h2>
                <p class="text-gray-600 mb-8">Looks like you haven't added any items to your cart yet.</p>
                <a href="{{ route('customer.products.index') }}" 
                   class="inline-flex items-center gap-2 bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition font-medium shadow-md">
                    <i class="fas fa-arrow-left"></i>
                    Continue Shopping
                </a>
            </div>
        @endif
        
        <!-- Flash Messages -->
        @if(session()->has('error'))
            <div class="mt-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <div>
                        <p class="font-medium">{{ session('error') }}</p>
                    </div>
                </div>
            </div>
        @endif
        
        @if(session()->has('success'))
            <div class="mt-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-r-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-3"></i>
                    <div>
                        <p class="font-medium">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@script
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add smooth scroll to top when cart updates
        Livewire.on('cart-updated', () => {
            setTimeout(() => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }, 100);
        });
    });
</script>
@endscript
<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Session;
use App\Models\Product;

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
        $cart = Session::get('cart', []);
        $this->cartItems = [];
        $this->subtotal = 0;
        
        foreach ($cart as $productId => $quantity) {
            $product = Product::find($productId);
            if ($product) {
                $itemTotal = $product->price * $quantity;
                $this->cartItems[] = [
                    'id' => $product->product_id,
                    'name' => $product->product_name,
                    'price' => $product->price,
                    'quantity' => $quantity,
                    'image' => $product->images ? json_decode($product->images, true)[0] ?? null : null,
                    'total' => $itemTotal
                ];
                $this->subtotal += $itemTotal;
            }
        }
        
        // Ensure cartItems is always an array
        if (!is_array($this->cartItems)) {
            $this->cartItems = [];
        }
        
        $this->tax = $this->subtotal * 0.08; // 8% tax
        $this->shipping = $this->subtotal > 100 ? 0 : 10; // Free shipping over $100
        $this->total = $this->subtotal + $this->tax + $this->shipping;
    }

    public function updateQuantity($productId, $action)
    {
        $cart = Session::get('cart', []);
        
        if ($action === 'increase') {
            $cart[$productId] = isset($cart[$productId]) ? $cart[$productId] + 1 : 1;
        } elseif ($action === 'decrease') {
            if (isset($cart[$productId]) && $cart[$productId] > 1) {
                $cart[$productId]--;
            } else {
                unset($cart[$productId]);
            }
        }
        
        Session::put('cart', $cart);
        $this->loadCart();
        $this->dispatch('cart-updated');
    }

    public function removeItem($productId)
    {
        $cart = Session::get('cart', []);
        unset($cart[$productId]);
        Session::put('cart', $cart);
        
        $this->loadCart();
        $this->dispatch('cart-updated');
    }

    public function clearCart()
    {
        Session::forget('cart');
        $this->loadCart();
        $this->dispatch('cart-updated');
    }

    public function proceedToCheckout()
    {
        if (empty($this->cartItems)) {
            session()->flash('error', 'Your cart is empty.');
            return;
        }
        
        return redirect()->route('customer.checkout');
    }
}
?>

<div>
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Shopping Cart</h1>
        
        @php
            $cartItems = $cartItems ?? $this->cartItems ?? [];
            $cartCount = is_array($cartItems) ? count($cartItems) : 0;
        @endphp
        
        @if($cartCount > 0)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Cart Items -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-semibold">Your Items ({{ $cartCount }})</h2>
                            <button wire:click="clearCart" class="text-red-600 text-sm hover:text-red-800">
                                Clear Cart
                            </button>
                        </div>
                        
                        <div class="space-y-4">
                            @foreach($cartItems as $item)
                                <div class="flex items-center border-b pb-4">
                                    <div class="w-20 h-20 bg-gray-200 rounded mr-4">
                                        @if(isset($item['image']) && $item['image'])
                                            <img src="{{ asset('storage/' . $item['image']) }}" 
                                                 class="w-full h-full object-cover rounded" 
                                                 alt="{{ $item['name'] ?? 'Product' }}">
                                        @endif
                                    </div>
                                    
                                    <div class="flex-1">
                                        <h3 class="font-medium">{{ $item['name'] ?? 'Unknown Product' }}</h3>
                                        <p class="text-gray-600">${{ number_format($item['price'] ?? 0, 2) }}</p>
                                        
                                        <div class="flex items-center mt-2">
                                            <button wire:click="updateQuantity({{ $item['id'] ?? 0 }}, 'decrease')" 
                                                    class="px-3 py-1 border rounded-l">
                                                -
                                            </button>
                                            <span class="px-4 py-1 border-t border-b">{{ $item['quantity'] ?? 0 }}</span>
                                            <button wire:click="updateQuantity({{ $item['id'] ?? 0 }}, 'increase')" 
                                                    class="px-3 py-1 border rounded-r">
                                                +
                                            </button>
                                            <button wire:click="removeItem({{ $item['id'] ?? 0 }})" 
                                                    class="ml-4 text-red-600 text-sm">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="text-right">
                                        <p class="font-semibold">${{ number_format($item['total'] ?? 0, 2) }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
                        
                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between">
                                <span>Subtotal</span>
                                <span>${{ number_format($subtotal ?? $this->subtotal ?? 0, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Tax (8%)</span>
                                <span>${{ number_format($tax ?? $this->tax ?? 0, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Shipping</span>
                                <span>
                                    @if(($shipping ?? $this->shipping ?? 0) > 0)
                                        ${{ number_format($shipping ?? $this->shipping ?? 0, 2) }}
                                    @else
                                        <span class="text-green-600">FREE</span>
                                    @endif
                                </span>
                            </div>
                            <div class="border-t pt-2 mt-2">
                                <div class="flex justify-between font-bold">
                                    <span>Total</span>
                                    <span>${{ number_format($total ?? $this->total ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <button wire:click="proceedToCheckout" 
                                class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700">
                            Proceed to Checkout
                        </button>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <div class="text-gray-400 mb-4">
                    <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" 
                              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <h2 class="text-xl font-semibold mb-2">Your cart is empty</h2>
                <p class="text-gray-600 mb-6">Add some items to get started</p>
                <a href="{{ route('customer.products.index') }}" 
                   class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    Continue Shopping
                </a>
            </div>
        @endif
        
        @if(session()->has('error'))
            <div class="mt-4 p-3 bg-red-100 text-red-700 rounded">
                {{ session('error') }}
            </div>
        @endif
    </div>
</div>
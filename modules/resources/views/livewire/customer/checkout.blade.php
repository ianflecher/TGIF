<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.customerapp')] class extends Component
{
    public $cartItems = [];
    public $total = 0;
    
    // Simple customer info
    public $name = '';
    public $phone = '';
    public $address = '';
    public $notes = '';
    
    // Order confirmation
    public $showConfirmation = false;
    public $orderNumber = '';
    public $orderDetails = [];
    
    public function mount()
    {
        if (!Auth::check()) {
            $this->redirect(route('login'), navigate: true);
            return;
        }
        
        $user = Auth::user();
        $this->name = $user->full_name ?? '';
        
        $this->loadCart();
    }
    
    public function loadCart()
    {
        if (!Auth::check()) {
            return;
        }
        
        $userId = Auth::user()->user_id;
        
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
                    'price' => $cartItem->unit_price,
                    'quantity' => $cartItem->quantity,
                    'total' => $itemTotal,
                ];
                $this->total += $itemTotal;
            }
        }
    }
    
    public function placeOrder()
    {
        // Simple validation
        if (empty($this->name) || empty($this->phone) || empty($this->address)) {
            $this->dispatch('show-toast', type: 'error', message: 'Please fill in all required fields.');
            return;
        }
        
        // Validate phone format (at least 10 digits)
        $phoneDigits = preg_replace('/\D/', '', $this->phone);
        if (strlen($phoneDigits) < 10) {
            $this->dispatch('show-toast', type: 'error', message: 'Please enter a valid phone number (at least 10 digits).');
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
            $this->orderNumber = 'ORD' . date('Ymd') . rand(1000, 9999);
            
            // First, check if we need to get or create a customer record
            // Since sales_orders needs customer_id, let's use the user_id as customer_id
            // Or check if there's a separate customers table
            $customerId = $userId; // Using user_id as customer_id for now
            
            // Check if there's a customers table
            if (DB::getSchemaBuilder()->hasTable('customers')) {
                // Try to find existing customer by user_id
                $customer = DB::table('customers')
                    ->where('user_id', $userId)
                    ->first();
                
                if ($customer) {
                    $customerId = $customer->customer_id;
                } else {
                    // Create a new customer record
                    $customerId = DB::table('customers')->insertGetId([
                        'user_id' => $userId,
                        'name' => $this->name,
                        'phone' => $this->phone,
                        'address' => $this->address,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            // Insert into sales_orders with correct column names
            $orderId = DB::table('sales_orders')->insertGetId([
                'customer_id' => $customerId,
                'order_number' => $this->orderNumber,
                'order_date' => now()->format('Y-m-d'), // Date format for order_date
                'total_amount' => $this->total,
                'grand_total' => $this->total,
                'payment_method' => 'cash', // Default to cash for COD
                'payment_status' => 'pending',
                'status' => 'confirmed', // Using confirmed instead of draft
                'shipping_address' => $this->address,
                'billing_address' => $this->address,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Insert order items - using your actual table column names
            $this->orderDetails = [];
            foreach ($this->cartItems as $item) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price_per_unit' => $item['price'],
                    'subtotal' => $item['total'],
                    'discount' => 0.00,
                    'tax' => 0.00,
                    'total' => $item['total'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Update stock
                DB::table('products')
                    ->where('product_id', $item['product_id'])
                    ->decrement('stock_quantity', $item['quantity']);
                    
                $this->orderDetails[] = [
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['total']
                ];
            }
            
            // Clear cart
            DB::table('cart_items')
                ->where('user_id', $userId)
                ->delete();
            
            DB::commit();
            
            // Show confirmation
            $this->showConfirmation = true;
            
            // Dispatch cart update
            $this->dispatch('cart-updated');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('show-toast', type: 'error', message: 'Failed to place order: ' . $e->getMessage());
        }
    }
    
    public function continueShopping()
    {
        $this->redirect(route('customer.products.index'), navigate: true);
    }
}
?>

<div class="min-h-screen bg-gray-50">
    <!-- Checkout Form (Hidden when confirmation shows) -->
    @if(!$showConfirmation)
    <!-- Header -->
    <div class="bg-green-600 text-white py-4">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="{{ route('customer.cart.index') }}" class="text-white">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </a>
                    <h1 class="text-xl font-bold">Checkout</h1>
                </div>
                <div class="text-sm">
                    Step 2 of 2
                </div>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-6">
        <!-- Order Summary -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
            <h2 class="font-bold text-lg mb-3">Order Summary</h2>
            
            <div class="space-y-3 mb-4">
                @foreach($cartItems as $item)
                <div class="flex justify-between items-center border-b pb-3">
                    <div>
                        <p class="font-medium">{{ $item['name'] }}</p>
                        <p class="text-sm text-gray-500">Qty: {{ $item['quantity'] }}</p>
                    </div>
                    <p class="font-bold">₱{{ number_format($item['total'], 2) }}</p>
                </div>
                @endforeach
            </div>
            
            <div class="border-t pt-3">
                <div class="flex justify-between font-bold text-lg">
                    <span>Total</span>
                    <span>₱{{ number_format($total, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Customer Information -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <h2 class="font-bold text-lg mb-4">Delivery Information</h2>
            
            <div class="space-y-4">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Your Name *
                    </label>
                    <input type="text"
                           wire:model="name"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none"
                           placeholder="Enter your name"
                           required>
                </div>
                
                <!-- Phone -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Phone Number *
                    </label>
                    <input type="tel"
                           wire:model="phone"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none"
                           placeholder="0912 345 6789"
                           required>
                </div>
                
                <!-- Address -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Delivery Address *
                    </label>
                    <textarea wire:model="address"
                              rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none"
                              placeholder="Enter your delivery address"
                              required></textarea>
                </div>
                
                <!-- Special Instructions -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Special Instructions (Optional)
                    </label>
                    <textarea wire:model="notes"
                              rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none"
                              placeholder="Any special requests or instructions"></textarea>
                </div>
            </div>
        </div>

        <!-- Payment Method -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <h2 class="font-bold text-lg mb-4">Payment Method</h2>
            
            <div class="space-y-3">
                <div class="flex items-center">
                    <input type="radio" 
                           id="cod" 
                           checked
                           class="h-4 w-4 text-green-600 focus:ring-green-500">
                    <label for="cod" class="ml-3">
                        <span class="block font-medium">Cash on Delivery</span>
                        <span class="block text-sm text-gray-500">Pay when you receive your order</span>
                    </label>
                </div>
            </div>
            
            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-info-circle mr-1"></i>
                    We only accept cash payment upon delivery.
                </p>
            </div>
        </div>

        <!-- Place Order Button -->
        <div class="sticky bottom-0 bg-white border-t p-4">
            <button wire:click="placeOrder"
                    wire:loading.attr="disabled"
                    class="w-full bg-green-600 text-white py-4 rounded-lg font-bold text-lg hover:bg-green-700 transition disabled:opacity-50">
                <span wire:loading.remove>
                    PLACE ORDER • ₱{{ number_format($total, 2) }}
                </span>
                <span wire:loading>
                    <i class="fas fa-spinner fa-spin mr-2"></i> Processing...
                </span>
            </button>
            
            <p class="text-center text-sm text-gray-500 mt-3">
                By placing your order, you agree to our <a href="#" class="text-green-600">Terms & Conditions</a>
            </p>
        </div>
    </div>
    @endif

    <!-- Order Confirmation (Shows after successful order) -->
    @if($showConfirmation)
    <div class="min-h-screen bg-green-50">
        <!-- Success Header -->
        <div class="bg-green-600 text-white py-8">
            <div class="container mx-auto px-4 text-center">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-3xl text-green-600"></i>
                </div>
                <h1 class="text-2xl font-bold mb-2">Order Confirmed!</h1>
                <p class="opacity-90">Thank you for your order</p>
            </div>
        </div>

        <div class="container mx-auto px-4 py-6">
            <!-- Order Details Card -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <!-- Order Number -->
                <div class="text-center mb-6">
                    <p class="text-gray-600">Order Number</p>
                    <p class="text-2xl font-bold text-green-600">{{ $orderNumber }}</p>
                </div>
                
                <!-- Status -->
                <div class="mb-6 p-4 bg-green-50 rounded-lg border border-green-200">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                        <div>
                            <p class="font-semibold text-green-700">Order Status: CONFIRMED</p>
                            <p class="text-sm text-green-600 mt-1">We'll notify you when your order is ready</p>
                        </div>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="mb-6">
                    <h3 class="font-bold text-lg mb-4">Order Details</h3>
                    <div class="space-y-3">
                        @foreach($orderDetails as $item)
                        <div class="flex justify-between items-center py-3 border-b">
                            <div>
                                <p class="font-medium">{{ $item['name'] }}</p>
                                <p class="text-sm text-gray-500">Qty: {{ $item['quantity'] }}</p>
                            </div>
                            <p class="font-bold">₱{{ number_format($item['total'], 2) }}</p>
                        </div>
                        @endforeach
                        
                        <!-- Total -->
                        <div class="pt-4 border-t">
                            <div class="flex justify-between font-bold text-lg">
                                <span>Total</span>
                                <span class="text-green-600">₱{{ number_format($total, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Delivery Info -->
                <div class="mb-6">
                    <h3 class="font-bold text-lg mb-3">Delivery Information</h3>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="font-medium">{{ $name }}</p>
                        <p class="text-gray-600">{{ $address }}</p>
                        <p class="text-gray-600">{{ $phone }}</p>
                        @if($notes)
                        <p class="mt-2 text-gray-600">
                            <span class="font-medium">Note:</span> {{ $notes }}
                        </p>
                        @endif
                    </div>
                </div>
                
                <!-- Estimated Time -->
                <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="flex items-start">
                        <i class="fas fa-clock text-blue-500 mt-1 mr-3"></i>
                        <div>
                            <p class="font-semibold text-blue-700">Estimated Preparation Time</p>
                            <p class="text-blue-600">20-30 minutes</p>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="space-y-4">
                    <button wire:click="continueShopping"
                            class="w-full bg-green-600 text-white py-3 rounded-lg font-bold hover:bg-green-700 transition">
                        CONTINUE SHOPPING
                    </button>
                    
                    <button onclick="window.print()"
                            class="w-full border-2 border-green-600 text-green-600 py-3 rounded-lg font-bold hover:bg-green-50 transition">
                        <i class="fas fa-print mr-2"></i> PRINT RECEIPT
                    </button>
                </div>
            </div>
            
            <!-- Help Info -->
            <div class="text-center">
                <p class="text-gray-600 mb-2">Need help with your order?</p>
                <a href="{{ route('customer.support.index') }}" 
                   class="inline-flex items-center text-green-600 font-medium">
                    <i class="fas fa-headset mr-2"></i>
                    Contact Support
                </a>
            </div>
        </div>
    </div>
    @endif
</div>

@script
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-format phone number
        const phoneInput = document.querySelector('input[wire\\:model="phone"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 0) {
                    value = value.substring(0, 11);
                    if (value.length > 4) {
                        value = value.substring(0, 4) + ' ' + value.substring(4, 7) + ' ' + value.substring(7);
                    }
                }
                e.target.value = value;
            });
        }
        
        // Scroll to top when order is confirmed
        Livewire.on('order-confirmed', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
    
    // Toast notification system
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('show-toast', (data) => {
            showToast(data.type, data.message);
        });
    });
    
    function showToast(type, message) {
        // Remove existing toasts
        const existingToasts = document.querySelectorAll('.toast-message');
        existingToasts.forEach(toast => toast.remove());
        
        // Create new toast in the middle of the screen
        const toast = document.createElement('div');
        toast.className = `toast-message fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 ${getToastColor(type)} text-white px-6 py-4 rounded-xl shadow-2xl z-50 animate-slide-in min-w-[300px] text-center`;
        toast.innerHTML = `
            <div class="flex flex-col items-center">
                <i class="fas ${getToastIcon(type)} text-2xl mb-2"></i>
                <div class="font-medium">${message}</div>
            </div>
        `;
        document.body.appendChild(toast);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            toast.classList.add('animate-slide-out');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }, 3000);
    }
    
    function getToastColor(type) {
        switch(type) {
            case 'success': return 'bg-green-600';
            case 'error': return 'bg-red-600';
            case 'warning': return 'bg-yellow-600';
            case 'info': return 'bg-blue-600';
            default: return 'bg-green-600';
        }
    }
    
    function getToastIcon(type) {
        switch(type) {
            case 'success': return 'fa-check-circle';
            case 'error': return 'fa-times-circle';
            case 'warning': return 'fa-exclamation-triangle';
            case 'info': return 'fa-info-circle';
            default: return 'fa-check-circle';
        }
    }
</script>
@endscript


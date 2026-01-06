<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.customerapp')] class extends Component
{
    public $products = [];
    public $categories = [];
    public $search = '';
    public $selectedCategory = null;
    
    public function mount()
    {
        try {
            $this->loadProducts();
            
            // Load categories if the table exists
            if (DB::getSchemaBuilder()->hasTable('product_categories')) {
                $this->categories = DB::table('product_categories')
                    ->whereNull('parent_id')
                    ->get();
            }
        } catch (\Exception $e) {
            $this->products = collect();
            $this->categories = collect();
        }
    }
    
    public function loadProducts()
    {
        $query = DB::table('products')
            ->where('status', 'published')
            ->orderBy('created_at', 'desc');
            
        if ($this->search) {
            $query->where('product_name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
        }
        
        if ($this->selectedCategory) {
            $query->join('product_category_pivot', 'products.product_id', '=', 'product_category_pivot.product_id')
                  ->where('product_category_pivot.category_id', $this->selectedCategory);
        }
        
        $this->products = $query->get();
    }
    
    public function updatedSearch()
    {
        $this->loadProducts();
    }
    
    public function selectCategory($categoryId = null)
    {
        $this->selectedCategory = $categoryId;
        $this->loadProducts();
    }
    
    public function addToCart($productId)
    {
        // Debug
        \Log::info('Add to cart called', ['product_id' => $productId]);
        
        // Check if user is authenticated
        if (!Auth::check()) {
            \Log::warning('User not authenticated');
            $this->dispatch('show-toast', 
                type: 'error',
                message: 'Please login to add items to cart'
            );
            return;
        }
        
        // Get user info
        $userId = Auth::user()->user_id;
        $userRole = Auth::user()->role;
        \Log::info('User info', ['user_id' => $userId, 'role' => $userRole]);
        
        // Check if user is a customer
        if ($userRole !== 'customer') {
            \Log::warning('User is not a customer', ['role' => $userRole]);
            $this->dispatch('show-toast', 
                type: 'error',
                message: 'Only customers can add items to cart'
            );
            return;
        }
        
        try {
            // Start database transaction for consistency
            DB::beginTransaction();
            
            // Check if product exists and has stock (with lock to prevent race condition)
            $product = DB::table('products')
                ->where('product_id', $productId)
                ->where('status', 'published')
                ->lockForUpdate() // Lock the row for update
                ->first();
                
            if (!$product) {
                DB::rollBack();
                $this->dispatch('show-toast', 
                    type: 'error',
                    message: 'Product not found or unavailable'
                );
                return;
            }
            
            if ($product->stock_quantity <= 0) {
                DB::rollBack();
                $this->dispatch('show-toast', 
                    type: 'error',
                    message: 'Product is out of stock'
                );
                return;
            }
            
            // Decrease stock by 1
            $newStock = $product->stock_quantity - 1;
            DB::table('products')
                ->where('product_id', $productId)
                ->update([
                    'stock_quantity' => $newStock,
                    'updated_at' => now()
                ]);
            
            \Log::info('Stock decreased', [
                'product_id' => $productId,
                'old_stock' => $product->stock_quantity,
                'new_stock' => $newStock
            ]);
            
            // Check if cart_items table exists, if not use session
            if (!DB::getSchemaBuilder()->hasTable('cart_items')) {
                \Log::info('Using session-based cart');
                
                // Use session-based cart
                $cart = session()->get('cart', []);
                
                // Initialize user's cart if not exists
                if (!isset($cart[$userId])) {
                    $cart[$userId] = [];
                }
                
                // Check if product already in user's cart
                if (isset($cart[$userId][$productId])) {
                    // Update quantity
                    $cart[$userId][$productId]['quantity'] += 1;
                    $cart[$userId][$productId]['total_price'] = $cart[$userId][$productId]['quantity'] * $product->price;
                } else {
                    // Add new item to cart
                    $cart[$userId][$productId] = [
                        'product_id' => $productId,
                        'product_name' => $product->product_name,
                        'quantity' => 1,
                        'unit_price' => $product->price,
                        'total_price' => $product->price,
                        'image' => $product->images ? json_decode($product->images, true)[0] ?? null : null
                    ];
                }
                
                // Save cart to session
                session()->put('cart', $cart);
                \Log::info('Cart saved to session', ['items_count' => count($cart[$userId])]);
                
            } else {
                \Log::info('Using database cart');
                
                // Check if item already in cart
                $existingCartItem = DB::table('cart_items')
                    ->where('user_id', $userId)
                    ->where('product_id', $productId)
                    ->first();
                
                if ($existingCartItem) {
                    // Update quantity
                    DB::table('cart_items')
                        ->where('id', $existingCartItem->id)
                        ->update([
                            'quantity' => $existingCartItem->quantity + 1,
                            'total_price' => ($existingCartItem->quantity + 1) * $existingCartItem->unit_price,
                            'updated_at' => now()
                        ]);
                } else {
                    // Add new item to cart
                    DB::table('cart_items')->insert([
                        'user_id' => $userId,
                        'product_id' => $productId,
                        'quantity' => 1,
                        'unit_price' => $product->price,
                        'total_price' => $product->price,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
            
            // Commit transaction
            DB::commit();
            
            // Refresh products to show updated stock
            $this->loadProducts();
            
            // Dispatch success event with detailed message
            $this->dispatch('show-toast', 
                type: 'success',
                message: '✅ ' . $product->product_name . ' added to cart! Stock: ' . $newStock . ' left'
            );
            
            // Update cart count in navbar
            $this->dispatch('cart-updated');
            
            \Log::info('Add to cart completed successfully', [
                'product_id' => $productId,
                'product_name' => $product->product_name,
                'remaining_stock' => $newStock
            ]);
            
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            
            \Log::error('Add to cart error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->dispatch('show-toast', 
                type: 'error',
                message: '❌ Failed to add item to cart. Please try again.'
            );
        }
    }
}
?>

<style>
    @keyframes slide-in {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slide-out {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .animate-slide-in {
        animation: slide-in 0.3s ease-out;
    }
    
    .animate-slide-out {
        animation: slide-out 0.3s ease-in;
    }
</style>
<div class="min-h-screen bg-gradient-to-b from-white to-green-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-4xl font-bold text-gray-900 mb-2">Our Menu</h1>
                    <p class="text-lg text-gray-600">Discover all our delicious fries and snacks</p>
                </div>
                @auth
                <a href="{{ route('customer.cart.index') }}" 
                   class="flex items-center gap-2 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition font-medium">
                    <i class="fas fa-shopping-cart"></i>
                    View Cart
                </a>
                @endauth
            </div>
        </div>
        
        <!-- Search and Filter -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                <!-- Search -->
                <div class="w-full md:w-1/3">
                    <div class="relative">
                        <input type="text" 
                               wire:model.live="search"
                               placeholder="Search products..."
                               class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition">
                        <div class="absolute left-3 top-3 text-gray-400">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Category Filter -->
                @if(count($categories) > 0)
                <div class="w-full md:w-auto">
                    <div class="flex flex-wrap gap-2">
                        <button wire:click="selectCategory(null)"
                                class="px-4 py-2 rounded-lg {{ $selectedCategory === null ? 'bg-green-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}">
                            All Products
                        </button>
                        @foreach($categories as $category)
                        <button wire:click="selectCategory({{ $category->id }})"
                                class="px-4 py-2 rounded-lg {{ $selectedCategory == $category->id ? 'bg-green-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}">
                            {{ $category->name }}
                        </button>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        
        <!-- Products Grid -->
        <div class="mb-8">
            @if(count($products) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    @foreach($products as $product)
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                        <!-- Product Image -->
                        <div class="relative h-48 overflow-hidden">
                            @php
                                $imageUrl = null;
                                if (!empty($product->images)) {
                                    try {
                                        $images = json_decode($product->images, true);
                                        if (is_array($images) && count($images) > 0) {
                                            $imagePath = $images[0];
                                            $imageUrl = asset('storage/' . ltrim($imagePath, '/'));
                                        }
                                    } catch (\Exception $e) {
                                        $imageUrl = null;
                                    }
                                }
                            @endphp
                            
                            @if($imageUrl)
                                <img src="{{ $imageUrl }}" 
                                     alt="{{ $product->product_name }}"
                                     class="w-full h-full object-cover hover:scale-110 transition-transform duration-500"
                                     onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjFmNWY5Ii8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNiIgZmlsbD0iIzQ4YjQ1NiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPlRHSUY8L3RleHQ+PHRleHQgeD0iNTAlIiB5PSI2MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxMiIgZmlsbD0iIzg4ODg4OCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9IjFlbSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+'">
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-green-100 to-green-50 flex items-center justify-center">
                                    <div class="text-center">
                                        <i class="fas fa-french-fries text-5xl text-green-400 mb-2"></i>
                                        <p class="text-green-600 font-medium">TGIF</p>
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Price Badge -->
                            <div class="absolute top-4 right-4 bg-green-500 text-white px-3 py-1 rounded-full text-sm font-semibold">
                                ₱{{ number_format($product->price, 2) }}
                            </div>
                            
                            <!-- Stock Status -->
                            @if($product->stock_quantity <= 0)
                                <div class="absolute top-4 left-4 bg-red-500 text-white px-2 py-1 rounded text-xs font-semibold">
                                    Out of Stock
                                </div>
                            @elseif($product->stock_quantity <= 10)
                                <div class="absolute top-4 left-4 bg-yellow-500 text-white px-2 py-1 rounded text-xs font-semibold">
                                    Low Stock
                                </div>
                            @endif
                        </div>
                        
                        <!-- Product Info -->
                        <div class="p-5">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $product->product_name }}</h3>
                            
                            <p class="text-gray-600 mb-4 text-sm line-clamp-2">
                                {{ $product->short_description ?? ($product->description ?? 'Delicious fries made with perfection') }}
                            </p>
                            
                            <!-- Stats -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center space-x-4">
                                    <div class="text-sm">
                                        <span class="text-gray-500">Stock:</span>
                                        <span class="font-semibold ml-1 {{ $product->stock_quantity > 10 ? 'text-green-600' : ($product->stock_quantity > 0 ? 'text-yellow-600' : 'text-red-600') }}">
                                            {{ $product->stock_quantity }}
                                        </span>
                                    </div>
                                    <div class="text-sm">
                                        <span class="text-gray-500">Sold:</span>
                                        <span class="font-semibold ml-1 text-blue-600">
                                            {{ $product->sold_count ?? 0 }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex items-center justify-between">
                                @auth
                                    @if($product->stock_quantity > 0)
                                        <button type="button"
                                                onclick="addToCart({{ $product->product_id }}, this)"
                                                data-product-id="{{ $product->product_id }}"
                                                data-product-name="{{ $product->product_name }}"
                                                class="add-to-cart-btn flex-1 bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition font-medium flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                            <i class="fas fa-shopping-cart"></i>
                                            <span class="btn-text">Add to Cart</span>
                                            <span class="loading hidden">
                                                <i class="fas fa-spinner fa-spin"></i> Adding...
                                            </span>
                                        </button>
                                    @else
                                        <button disabled
                                                class="flex-1 bg-gray-300 text-gray-500 py-2 px-4 rounded-lg font-medium">
                                            Out of Stock
                                        </button>
                                    @endif
                                @else
                                    <a href="{{ route('login') }}"
                                       class="flex-1 bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition font-medium flex items-center justify-center gap-2">
                                        <i class="fas fa-sign-in-alt"></i>
                                        Login to Order
                                    </a>
                                @endauth
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                
                <!-- Results Count -->
                <div class="mt-6 text-center text-gray-600">
                    Showing {{ count($products) }} product{{ count($products) !== 1 ? 's' : '' }}
                </div>
                
            @else
                <!-- Empty State -->
                <div class="text-center py-16 bg-white rounded-2xl shadow-sm border border-gray-100">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-box-open text-3xl text-green-600"></i>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-700 mb-3">No Products Found</h3>
                    <p class="text-gray-600 max-w-md mx-auto mb-8">
                        @if($search || $selectedCategory)
                            No products match your search criteria. Try adjusting your filters.
                        @else
                            We're currently preparing our menu. Please check back soon!
                        @endif
                    </p>
                    @if($search || $selectedCategory)
                        <button wire:click="selectCategory(null)"
                                class="inline-flex items-center gap-2 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
                            <i class="fas fa-redo"></i>
                            Clear Filters
                        </button>
                    @endif
                </div>
            @endif
        </div>
        
        <!-- Call to Action -->
        <div class="mt-12 text-center">
            <div class="bg-gradient-to-r from-green-50 to-green-100 rounded-2xl p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Can't find what you're looking for?</h2>
                <p class="text-gray-600 mb-6 max-w-2xl mx-auto">
                    Contact us for special requests or custom orders. We're here to make your fry dreams come true!
                </p>
                <a href="{{ route('customer.support.index') }}"
                   class="inline-flex items-center gap-2 bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition font-medium shadow-lg hover:shadow-xl">
                    <i class="fas fa-headset"></i>
                    Contact Support
                </a>
            </div>
        </div>
    </div>
</div>

@script
<script>
    // Toast notification system
    document.addEventListener('DOMContentLoaded', function() {
        // Listen for toast events from Livewire
        Livewire.on('show-toast', (data) => {
            showToast(data.type, data.message);
        });
        
        // Listen for cart updated events
        Livewire.on('cart-updated', () => {
            console.log('Cart updated event received');
            // Update cart count in UI if needed
            updateCartCount();
        });
        
        // Debug: Log all Livewire events
        Livewire.on('*', (event, ...params) => {
            console.log('Livewire event:', event, params);
        });
    });
    
    // Add to cart function with manual Livewire call
    window.addToCart = function(productId, button) {
        console.log('Add to cart clicked for product:', productId);
        
        // Get product name for better feedback
        const productName = button.getAttribute('data-product-name') || 'Product';
        
        // Show loading state
        if (button) {
            const btnText = button.querySelector('.btn-text');
            const loading = button.querySelector('.loading');
            if (btnText && loading) {
                btnText.classList.add('hidden');
                loading.classList.remove('hidden');
            }
            button.disabled = true;
            button.classList.add('opacity-50', 'cursor-not-allowed');
        }
        
        // Call Livewire method
        @this.addToCart(productId).then((result) => {
            console.log('Add to cart completed:', result);
            
            // Show immediate feedback
            showToast('success', 'Adding ' + productName + ' to cart...');
            
            // Reset button state after delay
            setTimeout(() => {
                if (button) {
                    const btnText = button.querySelector('.btn-text');
                    const loading = button.querySelector('.loading');
                    if (btnText && loading) {
                        btnText.classList.remove('hidden');
                        loading.classList.add('hidden');
                    }
                    button.disabled = false;
                    button.classList.remove('opacity-50', 'cursor-not-allowed');
                    
                    // Update stock display
                    updateProductStock(productId);
                }
            }, 1500);
            
        }).catch(error => {
            console.error('Add to cart error:', error);
            
            // Reset button state immediately on error
            if (button) {
                const btnText = button.querySelector('.btn-text');
                const loading = button.querySelector('.loading');
                if (btnText && loading) {
                    btnText.classList.remove('hidden');
                    loading.classList.add('hidden');
                }
                button.disabled = false;
                button.classList.remove('opacity-50', 'cursor-not-allowed');
            }
            
            showToast('error', 'Failed to add ' + productName + ' to cart');
        });
    };
    
    // Function to update product stock display
    function updateProductStock(productId) {
        // Find the product card and update stock display
        const productCard = document.querySelector(`[data-product-id="${productId}"]`)?.closest('.bg-white');
        if (productCard) {
            // Trigger a small visual feedback
            productCard.style.transform = 'scale(0.98)';
            setTimeout(() => {
                productCard.style.transform = '';
            }, 300);
        }
    }
    
    // Function to update cart count in UI
    function updateCartCount() {
        // Update cart count badge if exists
        const cartBadge = document.querySelector('.cart-count-badge');
        if (cartBadge) {
            // You can fetch cart count via AJAX or Livewire
            // For now, just increment
            let currentCount = parseInt(cartBadge.textContent) || 0;
            cartBadge.textContent = currentCount + 1;
            cartBadge.classList.remove('hidden');
        }
    }
    
    // Helper function to show toast with improved styling
    function showToast(type, message) {
        console.log('Showing toast:', type, message);
        
        // Remove existing toasts
        const existingToasts = document.querySelectorAll('.toast-message');
        existingToasts.forEach(toast => toast.remove());
        
        // Create new toast with better styling
        const toast = document.createElement('div');
        toast.className = `toast-message fixed top-4 right-4 ${getToastColor(type)} text-white px-6 py-4 rounded-xl shadow-xl z-50 animate-slide-in flex items-center gap-3`;
        toast.innerHTML = `
            <div class="flex-shrink-0">
                <i class="fas ${getToastIcon(type)} text-xl"></i>
            </div>
            <div class="flex-1">
                <div class="font-semibold">${getToastTitle(type)}</div>
                <div class="text-sm opacity-90">${message}</div>
            </div>
            <button onclick="this.parentElement.remove()" class="ml-4 opacity-70 hover:opacity-100">
                <i class="fas fa-times"></i>
            </button>
        `;
        document.body.appendChild(toast);
        
        // Auto remove after 4 seconds
        setTimeout(() => {
            toast.classList.add('animate-slide-out');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }, 4000);
    }
    
    function getToastColor(type) {
        switch(type) {
            case 'success': return 'bg-gradient-to-r from-green-500 to-green-600 border-l-4 border-green-700';
            case 'error': return 'bg-gradient-to-r from-red-500 to-red-600 border-l-4 border-red-700';
            case 'warning': return 'bg-gradient-to-r from-yellow-500 to-yellow-600 border-l-4 border-yellow-700';
            case 'info': return 'bg-gradient-to-r from-blue-500 to-blue-600 border-l-4 border-blue-700';
            default: return 'bg-gradient-to-r from-green-500 to-green-600 border-l-4 border-green-700';
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
    
    function getToastTitle(type) {
        switch(type) {
            case 'success': return 'Success!';
            case 'error': return 'Error!';
            case 'warning': return 'Warning!';
            case 'info': return 'Info';
            default: return 'Notification';
        }
    }
    
    // Debug: Check if Livewire is loaded
    document.addEventListener('livewire:initialized', () => {
        console.log('✅ Livewire is initialized');
        
        // Add event listeners to all add to cart buttons
        document.querySelectorAll('.add-to-cart-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                console.log('Button clicked via event listener');
            });
        });
    });
</script>
@endscript
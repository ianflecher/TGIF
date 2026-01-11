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
    
    public function addToCart($productId, $size = null, $flavor = null, $variety = null)
    {
        // Debug
        \Log::info('Add to cart called', [
            'product_id' => $productId,
            'size' => $size,
            'flavor' => $flavor,
            'variety' => $variety
        ]);
        
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
            
            // Calculate final price with 1% increase for each selection
            $basePrice = (float) $product->price;
            $priceMultiplier = 1.0; // Start with base price
            
            // Apply 1% increase for each selection made
            $selectionCount = 0;
            if ($size) $selectionCount++;
            if ($flavor) $selectionCount++;
            if ($variety) $selectionCount++;
            
            // Apply 1% increase per selection
            if ($selectionCount > 0) {
                $priceMultiplier += ($selectionCount * 0.01);
            }
            
            $finalPrice = round($basePrice * $priceMultiplier, 2);
            
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
            
            // Prepare cart item data
            $cartItemData = [
                'user_id' => $userId,
                'product_id' => $product->product_id,
                'product_name' => $product->product_name,
                'quantity' => 1,
                'unit_price' => $finalPrice,
                'total_price' => $finalPrice,
                'size' => $size,
                'flavor' => $flavor,
                'variety' => $variety,
                'base_price' => $basePrice,
                'price_adjustment_percent' => ($selectionCount * 1),
                'final_price' => $finalPrice,
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            // Check cart system
            if (!DB::getSchemaBuilder()->hasTable('cart_items')) {
                \Log::info('Using session-based cart');
                
                // Use session-based cart
                $cart = session()->get('cart', []);
                
                // Initialize user's cart if not exists
                if (!isset($cart[$userId])) {
                    $cart[$userId] = [];
                }
                
                // Create a unique key for this combination
                $cartKey = $product->product_id . '_' . 
                          ($size ?? 'nosize') . '_' . 
                          ($flavor ?? 'noflavor') . '_' . 
                          ($variety ?? 'novariety');
                
                if (isset($cart[$userId][$cartKey])) {
                    // Update quantity
                    $cart[$userId][$cartKey]['quantity'] += 1;
                    $cart[$userId][$cartKey]['total_price'] = $cart[$userId][$cartKey]['quantity'] * $finalPrice;
                } else {
                    // Add new item to cart
                    $cartItemData['image'] = $product->images ? json_decode($product->images, true)[0] ?? null : null;
                    $cart[$userId][$cartKey] = $cartItemData;
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
                    ->where('size', $size)
                    ->where('flavor', $flavor)
                    ->where('variety', $variety)
                    ->first();
                
                if ($existingCartItem) {
                    // Update quantity
                    DB::table('cart_items')
                        ->where('id', $existingCartItem->id)
                        ->update([
                            'quantity' => $existingCartItem->quantity + 1,
                            'total_price' => ($existingCartItem->quantity + 1) * $finalPrice,
                            'updated_at' => now()
                        ]);
                } else {
                    // Add new item to cart
                    DB::table('cart_items')->insert($cartItemData);
                }
            }
            
            // Commit transaction
            DB::commit();
            
            // Refresh products to show updated stock
            $this->loadProducts();
            
            // Dispatch success event with detailed message
            $message = '✅ ' . $product->product_name . ' added to cart!';
            if ($size) $message .= ' Size: ' . $size;
            if ($flavor) $message .= ' Flavor: ' . $flavor;
            if ($variety) $message .= ' Variety: ' . $variety;
            if ($selectionCount > 0) $message .= ' (Price: +' . $selectionCount . '%)';
            
            $this->dispatch('show-toast', 
                type: 'success',
                message: $message
            );
            
            // Update cart count in navbar
            $this->dispatch('cart-updated');
            
            \Log::info('Add to cart completed successfully', [
                'product_id' => $productId,
                'product_name' => $product->product_name,
                'remaining_stock' => $newStock,
                'final_price' => $finalPrice
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
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideIn {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .fade-in {
        animation: fadeIn 0.3s ease-out;
    }
    
    .slide-in {
        animation: slideIn 0.3s ease-out;
    }
    
    .selection-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .option-btn {
        padding: 12px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .option-btn:hover {
        border-color: #10b981;
        background: #f0fdf4;
    }
    
    .option-btn.selected {
        border-color: #10b981;
        background: #d1fae5;
        color: #065f46;
        font-weight: 600;
    }
    
    .price-badge {
        background: #10b981;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
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
                    @php
                        // Parse attributes for preview
                        $attributes = json_decode($product->attributes, true) ?? [];
                        $hasOptions = !empty($attributes['sizes']) || !empty($attributes['flavors']) || !empty($attributes['varieties']);
                    @endphp
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
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
                                     class="w-full h-full object-cover hover:scale-110 transition-transform duration-500">
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-green-100 to-green-50 flex items-center justify-center">
                                    <div class="text-center">
                                        <i class="fas fa-french-fries text-5xl text-green-400 mb-2"></i>
                                        <p class="text-green-600 font-medium">TGIF</p>
                                    </div>
                                </div>
                            @endif
                            
                            <div class="absolute top-4 right-4 price-badge">
                                ₱{{ number_format($product->price, 2) }}
                            </div>
                            
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
                        
                        <div class="p-5">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $product->product_name }}</h3>
                            
                            <p class="text-gray-600 mb-4 text-sm line-clamp-2">
                                {{ $product->short_description ?? ($product->description ?? 'Delicious fries made with perfection') }}
                            </p>
                            
                            <!-- Available Options Preview -->
                            @if($hasOptions)
                            <div class="mb-4 space-y-2">
                                @if(!empty($attributes['sizes']))
                                <div class="text-xs text-gray-600">
                                    <span class="font-medium">Sizes:</span>
                                    <span class="ml-1">{{ implode(', ', array_slice($attributes['sizes'], 0, 2)) }}{{ count($attributes['sizes']) > 2 ? '...' : '' }}</span>
                                </div>
                                @endif
                                
                                @if(!empty($attributes['flavors']))
                                <div class="text-xs text-gray-600">
                                    <span class="font-medium">Flavors:</span>
                                    <span class="ml-1">{{ implode(', ', array_slice($attributes['flavors'], 0, 2)) }}{{ count($attributes['flavors']) > 2 ? '...' : '' }}</span>
                                </div>
                                @endif
                                
                                @if(!empty($attributes['varieties']))
                                <div class="text-xs text-gray-600">
                                    <span class="font-medium">Varieties:</span>
                                    <span class="ml-1">{{ implode(', ', array_slice($attributes['varieties'], 0, 2)) }}{{ count($attributes['varieties']) > 2 ? '...' : '' }}</span>
                                </div>
                                @endif
                                
                                @if($hasOptions)
                                <div class="text-xs text-green-600 font-medium">
                                    <i class="fas fa-plus-circle"></i> Click "Customize & Add" to choose options
                                </div>
                                @endif
                            </div>
                            @endif
                            
                            <div class="flex items-center justify-between mb-4">
                                <div class="text-sm">
                                    <span class="text-gray-500">Stock:</span>
                                    <span class="font-semibold ml-1 {{ $product->stock_quantity > 10 ? 'text-green-600' : ($product->stock_quantity > 0 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ $product->stock_quantity }}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                @auth
                                    @if($product->stock_quantity > 0)
                                        <button type="button"
                                                onclick="showSelectionModal({{ $product->product_id }}, {{ json_encode($attributes) }}, {{ $product->price }}, '{{ $product->product_name }}')"
                                                class="add-to-cart-btn flex-1 bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition font-medium flex items-center justify-center gap-2">
                                            <i class="fas fa-shopping-cart"></i>
                                            <span class="btn-text">{{ $hasOptions ? 'Customize & Add' : 'Add to Cart' }}</span>
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
                
                <div class="mt-6 text-center text-gray-600">
                    Showing {{ count($products) }} product{{ count($products) !== 1 ? 's' : '' }}
                </div>
                
            @else
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
    
    <!-- Selection Modal -->
    <div id="selectionModal" class="selection-modal fade-in">
        <div class="modal-content slide-in">
            <div class="p-6">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-900" id="modalProductName"></h3>
                    <button type="button" 
                            onclick="closeSelectionModal()"
                            class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Price Display -->
                <div class="mb-6 text-center">
                    <div class="text-green-600 text-lg font-semibold">
                        Base Price: ₱<span id="modalBasePrice"></span>
                    </div>
                    <div class="text-2xl font-bold text-green-700 mt-2" id="modalFinalPrice"></div>
                    <div class="text-sm text-gray-600 mt-1" id="modalPriceAdjustment"></div>
                </div>
                
                <!-- Size Selection -->
                <div id="sizeSection" class="mb-6" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        <i class="fas fa-expand-alt mr-2"></i>Select Size
                    </label>
                    <div class="grid grid-cols-2 gap-3" id="sizeOptions"></div>
                </div>
                
                <!-- Flavor Selection -->
                <div id="flavorSection" class="mb-6" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        <i class="fas fa-utensils mr-2"></i>Select Flavor
                    </label>
                    <div class="grid grid-cols-2 gap-3" id="flavorOptions"></div>
                </div>
                
                <!-- Variety Selection -->
                <div id="varietySection" class="mb-8" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        <i class="fas fa-layer-group mr-2"></i>Select Variety
                    </label>
                    <div class="grid grid-cols-2 gap-3" id="varietyOptions"></div>
                </div>
                
                <!-- Summary -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-medium text-gray-700 mb-2">Your Selection:</h4>
                    <div class="space-y-1 text-sm" id="selectionSummary">
                        <div class="flex justify-between">
                            <span>Base Price:</span>
                            <span id="summaryBasePrice"></span>
                        </div>
                    </div>
                    <div class="border-t pt-2 mt-2">
                        <div class="flex justify-between font-bold">
                            <span>Final Price:</span>
                            <span id="summaryFinalPrice"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex gap-3">
                    <button type="button"
                            onclick="closeSelectionModal()"
                            class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
                        Cancel
                    </button>
                    <button type="button"
                            onclick="addToCartWithOptions()"
                            class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium flex items-center justify-center gap-2">
                        <i class="fas fa-shopping-cart"></i>
                        Add to Cart
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Global variables to store product info
    let currentProductId = null;
    let currentBasePrice = 0;
    let currentSelections = {
        size: null,
        flavor: null,
        variety: null
    };
    
    // Show selection modal
    function showSelectionModal(productId, attributes, basePrice, productName) {
        console.log('Showing modal for product:', productId, attributes);
        
        currentProductId = productId;
        currentBasePrice = basePrice;
        currentSelections = { size: null, flavor: null, variety: null };
        
        // Set modal title and price
        document.getElementById('modalProductName').textContent = productName;
        document.getElementById('modalBasePrice').textContent = basePrice.toFixed(2);
        document.getElementById('summaryBasePrice').textContent = '₱' + basePrice.toFixed(2);
        
        // Clear previous options
        document.getElementById('sizeOptions').innerHTML = '';
        document.getElementById('flavorOptions').innerHTML = '';
        document.getElementById('varietyOptions').innerHTML = '';
        
        // Show/hide sections based on available options
        const sizeSection = document.getElementById('sizeSection');
        const flavorSection = document.getElementById('flavorSection');
        const varietySection = document.getElementById('varietySection');
        
        // Sizes
        if (attributes.sizes && attributes.sizes.length > 0) {
            sizeSection.style.display = 'block';
            attributes.sizes.forEach(size => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'option-btn';
                button.textContent = size;
                button.onclick = () => selectOption('size', size, button);
                document.getElementById('sizeOptions').appendChild(button);
            });
        } else {
            sizeSection.style.display = 'none';
        }
        
        // Flavors
        if (attributes.flavors && attributes.flavors.length > 0) {
            flavorSection.style.display = 'block';
            attributes.flavors.forEach(flavor => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'option-btn';
                button.textContent = flavor;
                button.onclick = () => selectOption('flavor', flavor, button);
                document.getElementById('flavorOptions').appendChild(button);
            });
        } else {
            flavorSection.style.display = 'none';
        }
        
        // Varieties
        if (attributes.varieties && attributes.varieties.length > 0) {
            varietySection.style.display = 'block';
            attributes.varieties.forEach(variety => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'option-btn';
                button.textContent = variety;
                button.onclick = () => selectOption('variety', variety, button);
                document.getElementById('varietyOptions').appendChild(button);
            });
        } else {
            varietySection.style.display = 'none';
        }
        
        // Auto-select first option if only one exists
        if (attributes.sizes && attributes.sizes.length === 1) {
            selectOption('size', attributes.sizes[0], document.querySelector('#sizeOptions .option-btn'));
        }
        if (attributes.flavors && attributes.flavors.length === 1) {
            selectOption('flavor', attributes.flavors[0], document.querySelector('#flavorOptions .option-btn'));
        }
        if (attributes.varieties && attributes.varieties.length === 1) {
            selectOption('variety', attributes.varieties[0], document.querySelector('#varietyOptions .option-btn'));
        }
        
        // Update price display
        updatePriceDisplay();
        
        // Show modal
        document.getElementById('selectionModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    // Select an option
    function selectOption(type, value, button) {
        console.log('Selected', type, ':', value);
        
        // Remove selected class from all buttons of this type
        const buttons = button.parentElement.querySelectorAll('.option-btn');
        buttons.forEach(btn => btn.classList.remove('selected'));
        
        // Add selected class to clicked button
        button.classList.add('selected');
        
        // Update current selection
        currentSelections[type] = value;
        
        // Update price display
        updatePriceDisplay();
    }
    
    // Update price display based on selections
    function updatePriceDisplay() {
        let selectionCount = 0;
        if (currentSelections.size) selectionCount++;
        if (currentSelections.flavor) selectionCount++;
        if (currentSelections.variety) selectionCount++;
        
        // Calculate final price with 1% increase per selection
        const priceMultiplier = 1 + (selectionCount * 0.01);
        const finalPrice = Math.round(currentBasePrice * priceMultiplier * 100) / 100;
        
        // Update price displays
        document.getElementById('modalFinalPrice').textContent = 'Final Price: ₱' + finalPrice.toFixed(2);
        document.getElementById('summaryFinalPrice').textContent = '₱' + finalPrice.toFixed(2);
        
        // Update price adjustment text
        const adjustmentEl = document.getElementById('modalPriceAdjustment');
        if (selectionCount > 0) {
            adjustmentEl.textContent = 'Price adjustment: +' + selectionCount + '% (+₱' + (finalPrice - currentBasePrice).toFixed(2) + ')';
        } else {
            adjustmentEl.textContent = '';
        }
        
        // Update selection summary
        const summaryEl = document.getElementById('selectionSummary');
        let summaryHTML = `
            <div class="flex justify-between">
                <span>Base Price:</span>
                <span>₱${currentBasePrice.toFixed(2)}</span>
            </div>
        `;
        
        if (currentSelections.size) {
            summaryHTML += `
                <div class="flex justify-between">
                    <span>Size: ${currentSelections.size}</span>
                    <span class="text-green-600">+1%</span>
                </div>
            `;
        }
        
        if (currentSelections.flavor) {
            summaryHTML += `
                <div class="flex justify-between">
                    <span>Flavor: ${currentSelections.flavor}</span>
                    <span class="text-green-600">+1%</span>
                </div>
            `;
        }
        
        if (currentSelections.variety) {
            summaryHTML += `
                <div class="flex justify-between">
                    <span>Variety: ${currentSelections.variety}</span>
                    <span class="text-green-600">+1%</span>
                </div>
            `;
        }
        
        summaryEl.innerHTML = summaryHTML;
    }
    
    // Close selection modal
    function closeSelectionModal() {
        document.getElementById('selectionModal').style.display = 'none';
        document.body.style.overflow = '';
        currentProductId = null;
        currentBasePrice = 0;
        currentSelections = { size: null, flavor: null, variety: null };
    }
    
    // Add to cart with selected options
    function addToCartWithOptions() {
        if (!currentProductId) {
            showToast('error', 'No product selected');
            return;
        }
        
        // Get the Add to Cart button
        const addButton = document.querySelector(`[onclick*="showSelectionModal(${currentProductId}"]`);
        if (addButton) {
            // Show loading state
            const originalHTML = addButton.innerHTML;
            addButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            addButton.disabled = true;
            
            // Call Livewire method
            @this.addToCart(
                currentProductId, 
                currentSelections.size, 
                currentSelections.flavor, 
                currentSelections.variety
            ).then(() => {
                // Close modal
                closeSelectionModal();
                
                // Reset button after a delay
                setTimeout(() => {
                    addButton.innerHTML = originalHTML;
                    addButton.disabled = false;
                }, 1000);
            }).catch(error => {
                console.error('Add to cart error:', error);
                addButton.innerHTML = originalHTML;
                addButton.disabled = false;
                showToast('error', 'Failed to add to cart');
            });
        } else {
            // Fallback: direct Livewire call
            @this.addToCart(
                currentProductId, 
                currentSelections.size, 
                currentSelections.flavor, 
                currentSelections.variety
            );
            closeSelectionModal();
        }
    }
    
    // Toast notification function
    function showToast(type, message) {
        // Remove existing toasts
        const existingToasts = document.querySelectorAll('.toast-message');
        existingToasts.forEach(toast => toast.remove());
        
        // Create toast
        const toast = document.createElement('div');
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            warning: 'bg-yellow-500',
            info: 'bg-blue-500'
        };
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        
        toast.className = `toast-message fixed top-4 right-4 ${colors[type] || colors.success} text-white px-6 py-4 rounded-xl shadow-xl z-50 fade-in flex items-center gap-3`;
        toast.innerHTML = `
            <i class="fas ${icons[type] || icons.success} text-xl"></i>
            <div>
                <div class="font-semibold">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
                <div class="text-sm opacity-90">${message}</div>
            </div>
            <button onclick="this.parentElement.remove()" class="ml-4 opacity-70 hover:opacity-100">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 4 seconds
        setTimeout(() => {
            toast.remove();
        }, 4000);
    }
    
    // Listen for Livewire toast events
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('show-toast', (data) => {
            showToast(data.type, data.message);
        });
    });
    
    // Close modal on ESC key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeSelectionModal();
        }
    });
    
    // Close modal when clicking outside
    document.getElementById('selectionModal')?.addEventListener('click', (e) => {
        if (e.target === document.getElementById('selectionModal')) {
            closeSelectionModal();
        }
    });
</script>
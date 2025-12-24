<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.landing')] class extends Component
{
    public $stats = [];
    public $featuredProducts = [];
    public $popularProducts = [];
    
    public function mount()
    {
        try {
            // Get featured products from database
            $this->featuredProducts = DB::table('products')
                ->where('status', 'published')
                ->orderBy('created_at', 'desc')
                ->take(4)
                ->get();
            
            // Get popular products (most sold)
            $this->popularProducts = DB::table('products')
                ->where('status', 'published')
                ->orderBy('sold_count', 'desc')
                ->take(3)
                ->get();
            
            // Get stats from database
            $this->stats = [
                'total_customers' => DB::table('customers')->count(),
                'today_orders' => DB::table('sales_orders')
                    ->whereDate('created_at', Carbon::today())
                    ->where('status', '!=', 'cancelled')
                    ->count(),
                'active_products' => DB::table('products')
                    ->where('status', 'published')
                    ->count(),
                'total_orders' => DB::table('sales_orders')
                    ->where('status', '!=', 'cancelled')
                    ->count(),
            ];
            
        } catch (\Exception $e) {
            // Fallback data if database query fails or no data
            $this->featuredProducts = $this->getFallbackProducts();
            $this->popularProducts = array_slice($this->getFallbackProducts(), 0, 3);
            $this->stats = [
                'total_customers' => 0,
                'today_orders' => 0,
                'active_products' => count($this->featuredProducts),
                'total_orders' => 0,
            ];
        }
    }
    
    private function getFallbackProducts()
    {
        return [
            (object) [
                'product_id' => 1,
                'product_name' => 'Classic Golden Fries',
                'short_description' => 'Perfectly crispy hand-cut fries with sea salt',
                'price' => 4.99,
                'stock_quantity' => 50,
                'sold_count' => 0,
                'images' => json_encode(['https://images.unsplash.com/photo-1573080496219-bb080dd4f877?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80'])
            ],
            (object) [
                'product_id' => 2,
                'product_name' => 'Loaded Cheese Fries',
                'short_description' => 'Smothered in melted cheese and special sauce',
                'price' => 8.99,
                'stock_quantity' => 30,
                'sold_count' => 0,
                'images' => json_encode(['https://images.unsplash.com/photo-1594212699903-ec8a3eca50f5?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80'])
            ],
            (object) [
                'product_id' => 3,
                'product_name' => 'Spicy Cajun Fries',
                'short_description' => 'Crispy fries with our special blend of Cajun spices',
                'price' => 5.99,
                'stock_quantity' => 40,
                'sold_count' => 0,
                'images' => json_encode(['https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80'])
            ],
            (object) [
                'product_id' => 4,
                'product_name' => 'Sweet Potato Fries',
                'short_description' => 'Naturally sweet with a crispy golden texture',
                'price' => 6.49,
                'stock_quantity' => 35,
                'sold_count' => 0,
                'images' => json_encode(['https://images.unsplash.com/photo-1606755456206-b25206c44913?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80'])
            ],
        ];
    }
}
?>

<div class="min-h-screen bg-gradient-to-b from-white to-green-50">
    <!-- Hero Section -->
    <section class="pt-32 pb-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <!-- Left Content -->
                <div>
                    <div class="inline-flex items-center gap-2 bg-green-100 text-green-700 px-4 py-2 rounded-full text-sm font-medium mb-6">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        Fresh Fries Daily
                    </div>
                    
                    <h1 class="text-5xl md:text-6xl font-bold text-gray-900 mb-6 leading-tight">
                        Thanks G It's<br>
                        <span class="text-green-600">Fries Day!</span>
                    </h1>
                    
                    <p class="text-xl text-gray-600 mb-8 max-w-xl">
                        Experience the crunch that makes every day special. Fresh, crispy fries 
                        made with love and served with perfection.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-4 mb-12">
                        <a href="{{ route('customer.products.index') }}" 
                           class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-green-600 rounded-xl hover:bg-green-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                            <i class="fas fa-shopping-cart mr-3"></i>
                            Order Now
                        </a>
                        <a href="#menu" 
                           class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-green-600 border-2 border-green-600 rounded-xl hover:bg-green-50 transition-all duration-300">
                            <i class="fas fa-utensils mr-3"></i>
                            View Menu
                        </a>
                    </div>
                    
                    <!-- Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center p-4 bg-white rounded-xl shadow-sm border border-gray-100">
                            <div class="text-2xl font-bold text-green-600">{{ number_format($stats['total_customers']) }}</div>
                            <div class="text-sm text-gray-600 mt-1">Happy Customers</div>
                        </div>
                        <div class="text-center p-4 bg-white rounded-xl shadow-sm border border-gray-100">
                            <div class="text-2xl font-bold text-green-600">{{ number_format($stats['today_orders']) }}</div>
                            <div class="text-sm text-gray-600 mt-1">Today's Orders</div>
                        </div>
                        <div class="text-center p-4 bg-white rounded-xl shadow-sm border border-gray-100">
                            <div class="text-2xl font-bold text-green-600">{{ number_format($stats['active_products']) }}</div>
                            <div class="text-sm text-gray-600 mt-1">Menu Items</div>
                        </div>
                        <div class="text-center p-4 bg-white rounded-xl shadow-sm border border-gray-100">
                            <div class="text-2xl font-bold text-green-600">{{ number_format($stats['total_orders']) }}</div>
                            <div class="text-sm text-gray-600 mt-1">Total Orders</div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Image -->
                <div class="relative">
                    <div class="relative z-10">
                        <img src="https://images.unsplash.com/photo-1573080496219-bb080dd4f877?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                             alt="Delicious Fries" 
                             class="rounded-2xl shadow-2xl w-full h-[400px] object-cover">
                    </div>
                    <div class="absolute -bottom-6 -right-6 w-64 h-64 bg-green-200 rounded-2xl"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Menu Section -->
    <section id="menu" class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Our Signature Fries</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Discover our handcrafted selection of crispy, golden fries
                </p>
            </div>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($featuredProducts as $product)
                <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100 hover:shadow-xl transition-all duration-300">
                    <div class="relative h-48 overflow-hidden">
                        @php
                            $images = $product->images ? json_decode($product->images, true) : [];
                        @endphp
                        @if(!empty($images) && isset($images[0]))
                            <img src="{{ $images[0] }}" 
                                 alt="{{ $product->product_name }}"
                                 class="w-full h-full object-cover hover:scale-110 transition-transform duration-500">
                        @else
                            <div class="w-full h-full bg-green-100 flex items-center justify-center">
                                <i class="fas fa-french-fries text-4xl text-green-600"></i>
                            </div>
                        @endif
                        <div class="absolute top-4 right-4 bg-green-500 text-white px-3 py-1 rounded-full text-sm font-semibold">
                            ${{ number_format($product->price, 2) }}
                        </div>
                    </div>
                    <div class="p-5">
                        <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $product->product_name }}</h3>
                        <p class="text-gray-600 mb-4 text-sm line-clamp-2">
                            {{ $product->short_description ?? ($product->description ?? 'Delicious crispy fries') }}
                        </p>
                        <div class="flex items-center justify-between">
                            <div class="text-sm">
                                <span class="text-gray-500">Stock:</span>
                                <span class="font-semibold ml-1 {{ $product->stock_quantity > 10 ? 'text-green-600' : 'text-yellow-600' }}">
                                    {{ $product->stock_quantity }}
                                </span>
                            </div>
                            @auth
                                @if(auth()->user()->role === 'customer')
                                    <a href="{{ route('customer.products.show', $product->product_id) }}" 
                                       class="text-green-600 hover:text-green-700 font-medium text-sm">
                                        Order Now
                                    </a>
                                @endif
                            @else
                                <a href="{{ route('login') }}" class="text-green-600 hover:text-green-700 font-medium text-sm">
                                    Order Now
                                </a>
                            @endauth
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            
            @if(count($featuredProducts) > 0)
            <div class="text-center mt-12">
                <a href="{{ route('customer.products.index') }}" 
                   class="inline-flex items-center justify-center px-8 py-3 text-lg font-semibold text-white bg-green-600 rounded-xl hover:bg-green-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                    View Full Menu <i class="fas fa-arrow-right ml-3"></i>
                </a>
            </div>
            @endif
        </div>
    </section>

    <!-- Popular Items -->
    @if(count($popularProducts) > 0)
    <section class="py-16 bg-gradient-to-b from-green-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Customer Favorites</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Our most popular choices loved by customers
                </p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                @foreach($popularProducts as $index => $product)
                <div class="relative">
                    <div class="absolute -top-4 -left-4 w-10 h-10 bg-green-500 text-white rounded-full flex items-center justify-center font-bold text-lg">
                        {{ $index + 1 }}
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-100">
                        <div class="flex items-center justify-center w-16 h-16 bg-green-100 rounded-lg mb-4 mx-auto">
                            @php
                                $images = $product->images ? json_decode($product->images, true) : [];
                            @endphp
                            @if(!empty($images) && isset($images[0]))
                                <img src="{{ $images[0] }}" alt="{{ $product->product_name }}" class="w-full h-full object-cover rounded-lg">
                            @else
                                <i class="fas fa-crown text-2xl text-green-600"></i>
                            @endif
                        </div>
                        <h4 class="font-bold text-gray-900 text-center mb-2">{{ $product->product_name }}</h4>
                        <div class="text-center">
                            <div class="text-green-600 font-bold text-lg">${{ number_format($product->price, 2) }}</div>
                            <div class="text-sm text-gray-500 mt-1">{{ $product->sold_count ?? 0 }} orders</div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Call to Action -->
    <section class="py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-gradient-to-r from-green-600 to-green-700 rounded-3xl p-8 md:p-12 text-center text-white">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">Ready to Taste Perfection?</h2>
                <p class="text-lg md:text-xl mb-8 opacity-90">
                    Join our community of fry lovers today
                </p>
                
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    @auth
                        @if(auth()->user()->role === 'customer')
                            <a href="{{ route('customer.products.index') }}" 
                               class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-green-600 bg-white rounded-xl hover:bg-gray-100 transition-all duration-300 shadow-lg">
                                <i class="fas fa-bolt mr-3"></i>
                                Order Now
                            </a>
                        @else
                            <a href="{{ route('dashboard') }}" 
                               class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-green-600 bg-white rounded-xl hover:bg-gray-100 transition-all duration-300 shadow-lg">
                                <i class="fas fa-chart-line mr-3"></i>
                                Admin Dashboard
                            </a>
                        @endif
                    @else
                        <a href="{{ route('register') }}" 
                           class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-green-600 bg-white rounded-xl hover:bg-gray-100 transition-all duration-300 shadow-lg">
                            <i class="fas fa-user-plus mr-3"></i>
                            Create Account
                        </a>
                    @endauth
                    
                    <a href="{{ route('customer.support.index') }}" 
                       class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white border-2 border-white rounded-xl hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-question-circle mr-3"></i>
                        Need Help?
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
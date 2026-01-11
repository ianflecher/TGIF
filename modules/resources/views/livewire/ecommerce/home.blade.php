<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $ecommerceStats = [];
    public array $recentOrders = [];
    public array $recentEcommerceOrders = [];
    public array $topProducts = [];
    public array $platformStats = [];
    public array $customerMetrics = [];
    
    public function mount()
    {
        $this->loadEcommerceData();
    }
    
    public function loadEcommerceData()
    {
        // E-commerce Overview Statistics
        $this->ecommerceStats = [
            'total_orders' => DB::table('ecommerce_orders')->count(),
            'total_revenue' => DB::table('ecommerce_orders')->sum('total_amount') ?? 0,
            'total_products' => DB::table('products')->count(),
            'total_customers' => DB::table('customers')->count(),
            'pending_orders' => DB::table('ecommerce_orders')->where('order_status', 'new')->count(),
            'processing_orders' => DB::table('ecommerce_orders')->where('order_status', 'processing')->count(),
            'completed_orders' => DB::table('ecommerce_orders')->where('order_status', 'delivered')->count(),
            'avg_order_value' => $this->calculateAverageOrderValue(),
        ];
        
        // Recent E-commerce Orders
        $this->recentEcommerceOrders = DB::table('ecommerce_orders')
            ->select(
                'ecommerce_orders.*',
                'customers.first_name',
                'customers.last_name',
                'customers.email'
            )
            ->leftJoin('customers', 'ecommerce_orders.customer_id', '=', 'customers.customer_id')
            ->orderBy('ecommerce_orders.order_date', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
        
        // Recent Sales Orders (from main system)
        $this->recentOrders = DB::table('sales_orders')
            ->select(
                'sales_orders.*',
                'customers.first_name',
                'customers.last_name'
            )
            ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.customer_id')
            ->orderBy('sales_orders.order_date', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
        
            // Top Products
        $this->topProducts = DB::table('order_items')
            ->select(
                'products.product_name',
                'products.product_id',  // Use product_id instead of sku
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.total) as total_revenue')
            )
            ->leftJoin('products', 'order_items.product_id', '=', 'products.product_id')
            ->groupBy('products.product_id', 'products.product_name', 'products.product_id')
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
        // Platform Statistics
        $this->platformStats = DB::table('ecommerce_orders')
            ->select(
                'platform',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('AVG(total_amount) as avg_order_value')
            )
            ->groupBy('platform')
            ->orderBy('total_revenue', 'desc')
            ->get()
            ->toArray();
        
        // Customer Metrics
        $this->customerMetrics = [
            'new_customers' => DB::table('customers')
                ->where('date_registered', '>=', now()->subDays(30))
                ->count(),
            'repeat_customers' => DB::table('customers')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                          ->from('sales_orders')
                          ->whereColumn('sales_orders.customer_id', 'customers.customer_id')
                          ->groupBy('sales_orders.customer_id')
                          ->havingRaw('COUNT(*) > 1');
                })
                ->count(),
            'avg_customer_value' => $this->calculateAverageCustomerValue(),
        ];
    }
    
    private function calculateAverageOrderValue()
    {
        $result = DB::table('ecommerce_orders')
            ->select(DB::raw('AVG(total_amount) as avg_value'))
            ->where('order_status', 'delivered')
            ->first();
        
        return $result->avg_value ?? 0;
    }
    
    private function calculateAverageCustomerValue()
    {
        $result = DB::table('sales_orders')
            ->select(DB::raw('AVG(grand_total) as avg_value'))
            ->where('status', 'delivered')
            ->first();
        
        return $result->avg_value ?? 0;
    }
    
    public function formatCurrency($amount)
    {
        return '₱' . number_format($amount, 2);
    }
    
    public function formatNumber($number)
    {
        return number_format($number);
    }
    
    public function formatDate($date)
    {
        if (!$date) return 'N/A';
        return date('M d, Y', strtotime($date));
    }
    
    public function getOrderStatusColor($status)
    {
        return match(strtolower($status)) {
            'new', 'draft' => 'bg-blue-100 text-blue-800',
            'processing', 'confirmed' => 'bg-yellow-100 text-yellow-800',
            'shipped', 'in_progress' => 'bg-orange-100 text-orange-800',
            'delivered', 'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    
    public function getPaymentStatusColor($status)
    {
        return match(strtolower($status)) {
            'paid' => 'bg-green-100 text-green-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'failed' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    
    public function getPlatformColor($platform)
    {
        return match(strtolower($platform)) {
            'shopify' => 'bg-green-100 text-green-800',
            'woocommerce' => 'bg-blue-100 text-blue-800',
            'amazon' => 'bg-orange-100 text-orange-800',
            'ebay' => 'bg-red-100 text-red-800',
            'magento' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    
    public function getTimeAgo($datetime)
    {
        if (!$datetime) return 'N/A';
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff/60) . 'm ago';
        if ($diff < 86400) return floor($diff/3600) . 'h ago';
        if ($diff < 604800) return floor($diff/86400) . 'd ago';
        return date('M d, Y', $time);
    }
};
?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">E-commerce Dashboard</h1>
        <p class="text-gray-600 mt-2">Online sales performance and e-commerce analytics</p>
    </div>

    <!-- E-commerce Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Orders -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Orders</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatNumber($ecommerceStats['total_orders'] ?? 0) }}</h3>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-green-600 font-medium">{{ $ecommerceStats['pending_orders'] ?? 0 }} pending</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">{{ $ecommerceStats['processing_orders'] ?? 0 }} processing</span>
                </span>
            </div>
        </div>

        <!-- Total Revenue -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Revenue</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatCurrency($ecommerceStats['total_revenue'] ?? 0) }}</h3>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-blue-600 font-medium">Avg: {{ $this->formatCurrency($ecommerceStats['avg_order_value'] ?? 0) }}</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">per order</span>
                </span>
            </div>
        </div>

        <!-- Total Products -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Products</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatNumber($ecommerceStats['total_products'] ?? 0) }}</h3>
                </div>
                <div class="bg-purple-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-purple-600 font-medium">{{ $customerMetrics['new_customers'] ?? 0 }} new</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">customers this month</span>
                </span>
            </div>
        </div>

        <!-- Total Customers -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Customers</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatNumber($ecommerceStats['total_customers'] ?? 0) }}</h3>
                </div>
                <div class="bg-orange-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-orange-600 font-medium">{{ $customerMetrics['repeat_customers'] ?? 0 }} repeat</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Avg value: {{ $this->formatCurrency($customerMetrics['avg_customer_value'] ?? 0) }}</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent E-commerce Orders -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Recent E-commerce Orders</h2>
                    <p class="text-sm text-gray-600">Latest online orders from all platforms</p>
                </div>
                <a href="{{ route('ecommerce.orders') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                    View All
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($recentEcommerceOrders as $order)
                        <div class="p-4 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h4 class="font-medium text-gray-800">Order #{{ $order->external_order_code ?? $order->ecommerce_order_id }}</h4>
                                    <p class="text-sm text-gray-600">
                                        {{ $order->first_name ?? 'Customer' }} {{ $order->last_name ?? '' }}
                                    </p>
                                </div>
                                <div class="flex flex-col items-end space-y-1">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $this->getOrderStatusColor($order->order_status ?? '') }}">
                                        {{ ucfirst($order->order_status ?? 'unknown') }}
                                    </span>
                                    <span class="px-2 py-1 text-xs rounded-full {{ $this->getPlatformColor($order->platform ?? '') }}">
                                        {{ ucfirst($order->platform ?? 'unknown') }}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="flex items-center text-sm text-gray-600 mb-3">
                                <span class="mr-4">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    {{ $this->formatDate($order->order_date) }}
                                </span>
                                <span>
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    {{ $this->formatCurrency($order->total_amount ?? 0) }}
                                </span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">
                                    Payment: 
                                    <span class="{{ $this->getPaymentStatusColor($order->payment_status ?? '') }} px-2 py-1 rounded-full text-xs ml-1">
                                        {{ ucfirst($order->payment_status ?? 'pending') }}
                                    </span>
                                </span>
                                <span class="text-sm text-gray-500">
                                    {{ $this->getTimeAgo($order->created_at) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                            <p class="mt-2">No e-commerce orders found</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Platform Performance -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Platform Performance</h2>
                    <p class="text-sm text-gray-600">Sales breakdown by e-commerce platform</p>
                </div>
                <a href="{{ route('ecommerce.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                    View Details
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($platformStats as $platform)
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-gray-800">{{ ucfirst($platform->platform ?? 'Unknown') }}</h4>
                                    <span class="px-2 py-1 text-xs rounded-full {{ $this->getPlatformColor($platform->platform ?? '') }}">
                                        {{ $platform->order_count ?? 0 }} orders
                                    </span>
                                </div>
                                
                                <div class="flex items-center text-sm text-gray-600">
                                    <span class="mr-4">
                                        Revenue: {{ $this->formatCurrency($platform->total_revenue ?? 0) }}
                                    </span>
                                    <span>
                                        Avg: {{ $this->formatCurrency($platform->avg_order_value ?? 0) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="mt-2">No platform data available</p>
                        </div>
                    @endforelse
                </div>
                
                <!-- Performance Summary -->
                <div class="mt-6 pt-4 border-t">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Performance Summary</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center p-3 bg-green-50 rounded-lg">
                            <p class="text-sm text-gray-600">Completion Rate</p>
                            <p class="text-lg font-bold text-green-600">
                                @if(($ecommerceStats['total_orders'] ?? 0) > 0)
                                    {{ round((($ecommerceStats['completed_orders'] ?? 0) / ($ecommerceStats['total_orders'] ?? 1)) * 100, 1) }}%
                                @else
                                    0%
                                @endif
                            </p>
                        </div>
                        <div class="text-center p-3 bg-blue-50 rounded-lg">
                            <p class="text-sm text-gray-600">Platforms</p>
                            <p class="text-lg font-bold text-blue-600">{{ count($platformStats) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Top Products -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Top Selling Products</h2>
                <p class="text-sm text-gray-600">Best performing products by sales volume</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($topProducts as $product)
                        <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-800">{{ $product->product_name ?? 'Unknown Product' }}</h4>
                                <div class="flex items-center space-x-2 mt-1">
                                    <span class="text-xs text-gray-600">SKU: {{ $product->sku ?? 'N/A' }}</span>
                                    <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">
                                        {{ $product->total_sold ?? 0 }} sold
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-green-600">{{ $this->formatCurrency($product->total_revenue ?? 0) }}</span>
                                <p class="text-xs text-gray-500">Total revenue</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-gray-500">
                            <p>No product sales data available</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Recent Sales Orders -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Recent Sales Orders</h2>
                    <p class="text-sm text-gray-600">Latest sales from integrated systems</p>
                </div>
                <a href="{{ route('sales.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                    View All
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-500 border-b">
                                <th class="pb-3">Order #</th>
                                <th class="pb-3">Customer</th>
                                <th class="pb-3">Amount</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3">
                                        <span class="text-green-600 font-medium">
                                            #{{ $order->order_number ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-sm">
                                        {{ Str::limit($order->first_name ?? 'Unknown', 10) }} {{ Str::limit($order->last_name ?? '', 1) }}.
                                    </td>
                                    <td class="py-3 font-medium">
                                        {{ $this->formatCurrency($order->grand_total ?? 0) }}
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 text-xs rounded-full {{ $this->getOrderStatusColor($order->status ?? '') }}">
                                            {{ ucfirst($order->status ?? 'unknown') }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-sm text-gray-600">
                                        {{ $this->formatDate($order->order_date) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-500">
                                        No sales orders found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('ecommerce.orders') }}" class="flex flex-col items-center justify-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span class="font-medium text-gray-800">New Order</span>
            </a>
            
            <a href="{{ route('ecommerce.products') }}" class="flex flex-col items-center justify-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <span class="font-medium text-gray-800">Manage Products</span>
            </a>
        
        </div>
    </div>
</div>
<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $stats = [];
    public array $topProducts = [];
    public array $topCustomers = [];
    public array $recentSales = [];
    public array $recentEcommerceOrders = [];
    public array $inventoryStatus = [];
    
    public function mount()
    {
        $this->loadAnalyticsData();
    }
    
    public function loadAnalyticsData()
    {
        // Overall Statistics from sales_orders (main system)
        $totalSales = DB::table('sales_orders')->where('status', '!=', 'cancelled')->sum('total_amount') ?? 0;
        $totalTransactions = DB::table('sales_orders')->where('status', '!=', 'cancelled')->count();
        $totalProducts = DB::table('products')->count();
        $totalCustomers = DB::table('customers')->count();
        
        // Get total stock from inventories table
        $totalStock = DB::table('inventories')->sum('quantity') ?? 0;
        
        // Combine with e-commerce sales
        $ecommerceSales = DB::table('ecommerce_orders')->where('payment_status', 'paid')->sum('total_amount') ?? 0;
        
        $totalRevenue = $totalSales + $ecommerceSales;
        
        // Today's Performance
        $today = Carbon::today();
        $todaySales = DB::table('sales_orders')
            ->whereDate('order_date', $today)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount') ?? 0;
        
        $todayEcommerceSales = DB::table('ecommerce_orders')
            ->whereDate('order_date', $today)
            ->where('payment_status', 'paid')
            ->sum('total_amount') ?? 0;
        
        $totalTodaySales = $todaySales + $todayEcommerceSales;
        
        $todayTransactions = DB::table('sales_orders')
            ->whereDate('order_date', $today)
            ->where('status', '!=', 'cancelled')
            ->count();
        
        $todayEcommerceTransactions = DB::table('ecommerce_orders')
            ->whereDate('order_date', $today)
            ->where('payment_status', 'paid')
            ->count();
        
        $totalTodayTransactions = $todayTransactions + $todayEcommerceTransactions;
        
        $todayQuantity = DB::table('order_items')
            ->join('sales_orders', 'order_items.order_id', '=', 'sales_orders.order_id')
            ->whereDate('sales_orders.order_date', $today)
            ->where('sales_orders.status', '!=', 'cancelled')
            ->sum('order_items.quantity') ?? 0;
        
        // Yesterday for comparison
        $yesterday = Carbon::yesterday();
        $yesterdaySales = DB::table('sales_orders')
            ->whereDate('order_date', $yesterday)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount') ?? 0;
        
        $yesterdayEcommerceSales = DB::table('ecommerce_orders')
            ->whereDate('order_date', $yesterday)
            ->where('payment_status', 'paid')
            ->sum('total_amount') ?? 0;
        
        $totalYesterdaySales = $yesterdaySales + $yesterdayEcommerceSales;
        
        // Monthly Performance
        $monthStart = Carbon::now()->startOfMonth();
        $monthSales = DB::table('sales_orders')
            ->where('order_date', '>=', $monthStart)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount') ?? 0;
        
        $monthEcommerceSales = DB::table('ecommerce_orders')
            ->where('order_date', '>=', $monthStart)
            ->where('payment_status', 'paid')
            ->sum('total_amount') ?? 0;
        
        $totalMonthSales = $monthSales + $monthEcommerceSales;
        
        $monthTransactions = DB::table('sales_orders')
            ->where('order_date', '>=', $monthStart)
            ->where('status', '!=', 'cancelled')
            ->count();
        
        $monthEcommerceTransactions = DB::table('ecommerce_orders')
            ->where('order_date', '>=', $monthStart)
            ->where('payment_status', 'paid')
            ->count();
        
        $totalMonthTransactions = $monthTransactions + $monthEcommerceTransactions;
        
        // Last month for comparison
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();
        
        $lastMonthSales = DB::table('sales_orders')
            ->whereBetween('order_date', [$lastMonthStart, $lastMonthEnd])
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount') ?? 0;
        
        $lastMonthEcommerceSales = DB::table('ecommerce_orders')
            ->whereBetween('order_date', [$lastMonthStart, $lastMonthEnd])
            ->where('payment_status', 'paid')
            ->sum('total_amount') ?? 0;
        
        $totalLastMonthSales = $lastMonthSales + $lastMonthEcommerceSales;
        
        // Year Sales
        $yearStart = Carbon::now()->startOfYear();
        $yearSales = DB::table('sales_orders')
            ->where('order_date', '>=', $yearStart)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount') ?? 0;
        
        $yearEcommerceSales = DB::table('ecommerce_orders')
            ->where('order_date', '>=', $yearStart)
            ->where('payment_status', 'paid')
            ->sum('total_amount') ?? 0;
        
        $totalYearSales = $yearSales + $yearEcommerceSales;
        
        // Inventory Status from inventories table
        $lowStockThreshold = 10;
        $lowStockCount = DB::table('inventories')
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', $lowStockThreshold)
            ->count();
        
        $outOfStockCount = DB::table('inventories')->where('quantity', 0)->count();
        $negativeStockCount = DB::table('inventories')->where('quantity', '<', 0)->count();
        
        // Average calculations
        $avgSale = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0;
        $avgTransactionValue = $totalTodayTransactions > 0 ? $totalTodaySales / $totalTodayTransactions : 0;
        
        // Calculate percentages
        $todayVsYesterday = $totalYesterdaySales > 0 
            ? (($totalTodaySales - $totalYesterdaySales) / $totalYesterdaySales) * 100 
            : 0;
        
        $monthVsLastMonth = $totalLastMonthSales > 0 
            ? (($totalMonthSales - $totalLastMonthSales) / $totalLastMonthSales) * 100 
            : 0;
        
        $this->stats = [
            // Overall
            'total_sales' => $totalSales,
            'total_revenue' => $totalRevenue,
            'ecommerce_sales' => $ecommerceSales,
            'total_transactions' => $totalTransactions,
            'total_products' => $totalProducts,
            'total_customers' => $totalCustomers,
            'total_stock' => $totalStock,
            
            // Today
            'today_sales' => $totalTodaySales,
            'today_transactions' => $totalTodayTransactions,
            'today_quantity' => $todayQuantity,
            'today_vs_yesterday' => $todayVsYesterday,
            'avg_sale' => $avgSale,
            'avg_transaction_value' => $avgTransactionValue,
            
            // Month
            'month_sales' => $totalMonthSales,
            'month_transactions' => $totalMonthTransactions,
            'month_vs_last_month' => $monthVsLastMonth,
            'year_sales' => $totalYearSales,
            
            // Inventory
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'negative_stock_count' => $negativeStockCount,
            'low_stock_threshold' => $lowStockThreshold,
        ];
        
        // Top Products by Sales - Using inventories table for SKU
        $this->topProducts = DB::table('order_items')
            ->select(
                'products.product_name',
                'inventories.sku',
                DB::raw('SUM(order_items.quantity) as total_qty'),
                DB::raw('SUM(order_items.total) as total_sales')
            )
            ->leftJoin('products', 'order_items.product_id', '=', 'products.product_id')
            ->leftJoin('inventories', 'products.inventory_id', '=', 'inventories.inventory_id')
            ->leftJoin('sales_orders', 'order_items.order_id', '=', 'sales_orders.order_id')
            ->where('sales_orders.status', '!=', 'cancelled')
            ->groupBy('products.product_id', 'products.product_name', 'inventories.sku')
            ->orderBy('total_sales', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
        
        // Top Customers
        $this->topCustomers = DB::table('sales_orders')
            ->select(
                'customers.customer_id',
                DB::raw('CONCAT(customers.first_name, " ", customers.last_name) as customer_name'),
                DB::raw('COUNT(sales_orders.order_id) as order_count'),
                DB::raw('SUM(sales_orders.total_amount) as total_spent')
            )
            ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.customer_id')
            ->where('sales_orders.status', '!=', 'cancelled')
            ->groupBy('customers.customer_id', 'customers.first_name', 'customers.last_name')
            ->orderBy('total_spent', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
        
        // Recent Sales
        $this->recentSales = DB::table('order_items')
            ->select(
                'order_items.*',
                'products.product_name',
                'sales_orders.order_date',
                'sales_orders.order_id',
                DB::raw('CONCAT(customers.first_name, " ", customers.last_name) as customer_name')
            )
            ->leftJoin('products', 'order_items.product_id', '=', 'products.product_id')
            ->leftJoin('sales_orders', 'order_items.order_id', '=', 'sales_orders.order_id')
            ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.customer_id')
            ->where('sales_orders.status', '!=', 'cancelled')
            ->orderBy('sales_orders.order_date', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $item->date = Carbon::parse($item->order_date);
                return $item;
            })
            ->toArray();
        
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
            ->limit(10)
            ->get()
            ->toArray();
        
        // Inventory Status Details
        $this->inventoryStatus = DB::table('inventories')
            ->select(
                'inventory_id',
                'sku',
                'product_name',
                'quantity',
                'unit_price',
                'category'
            )
            ->where('quantity', '<=', $lowStockThreshold)
            ->orderBy('quantity', 'asc')
            ->limit(10)
            ->get()
            ->toArray();
    }
    
    public function formatCurrency($amount)
    {
        return '₱' . number_format($amount, 2);
    }
    
    public function formatNumber($number)
    {
        return number_format($number);
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
};
?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Business Analytics Dashboard</h1>
        <p class="text-gray-600 mt-2">Comprehensive overview of sales, inventory, and customer performance</p>
    </div>

    <!-- Overall Statistics -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-800">Overall Business Performance</h2>
            <span class="text-sm text-gray-500">All Time Data</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Total Revenue -->
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total Revenue</p>
                        <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatCurrency($stats['total_revenue'] ?? 0) }}</h3>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-sm text-gray-600">
                        {{ $stats['total_transactions'] ?? 0 }} transactions
                    </span>
                </div>
            </div>

            <!-- Total Customers -->
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total Customers</p>
                        <h3 class="text-2xl font-bold text-gray-800">{{ $stats['total_customers'] ?? 0 }}</h3>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-sm text-gray-600">
                        E-commerce: {{ $this->formatCurrency($stats['ecommerce_sales'] ?? 0) }}
                    </span>
                </div>
            </div>

            <!-- Total Products -->
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total Products</p>
                        <h3 class="text-2xl font-bold text-gray-800">{{ $stats['total_products'] ?? 0 }}</h3>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-full">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-sm text-gray-600">
                        {{ $this->formatNumber($stats['total_stock'] ?? 0) }} units in stock
                    </span>
                </div>
            </div>

            <!-- Average Sale -->
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Average Sale</p>
                        <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatCurrency($stats['avg_sale'] ?? 0) }}</h3>
                    </div>
                    <div class="bg-orange-100 p-3 rounded-full">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-sm text-gray-600">
                        Per transaction
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's & Monthly Performance -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Today's Performance -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="mb-4">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Today's Performance</h2>
                <p class="text-sm text-gray-600">{{ date('F d, Y') }}</p>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span class="font-medium text-gray-700">Today's Sales</span>
                    <span class="font-bold text-gray-800">{{ $this->formatCurrency($stats['today_sales'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span class="font-medium text-gray-700">Transactions</span>
                    <span class="font-bold text-gray-800">{{ $stats['today_transactions'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span class="font-medium text-gray-700">Items Sold</span>
                    <span class="font-bold text-gray-800">{{ $this->formatNumber($stats['today_quantity'] ?? 0) }}</span>
                </div>
                @if(($stats['today_vs_yesterday'] ?? 0) != 0)
                    <div class="p-3 rounded-lg {{ ($stats['today_vs_yesterday'] ?? 0) >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                        <div class="flex justify-between">
                            <span class="font-medium">Vs Yesterday</span>
                            <span class="font-bold">
                                {{ ($stats['today_vs_yesterday'] ?? 0) >= 0 ? '↑' : '↓' }} 
                                {{ number_format(abs($stats['today_vs_yesterday'] ?? 0), 1) }}%
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Monthly Performance -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="mb-4">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Monthly Performance</h2>
                <p class="text-sm text-gray-600">{{ date('F Y') }}</p>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span class="font-medium text-gray-700">Month Sales</span>
                    <span class="font-bold text-gray-800">{{ $this->formatCurrency($stats['month_sales'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span class="font-medium text-gray-700">Transactions</span>
                    <span class="font-bold text-gray-800">{{ $stats['month_transactions'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span class="font-medium text-gray-700">Year Sales</span>
                    <span class="font-bold text-gray-800">{{ $this->formatCurrency($stats['year_sales'] ?? 0) }}</span>
                </div>
                @if(($stats['month_vs_last_month'] ?? 0) != 0)
                    <div class="p-3 rounded-lg {{ ($stats['month_vs_last_month'] ?? 0) >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                        <div class="flex justify-between">
                            <span class="font-medium">Vs Last Month</span>
                            <span class="font-bold">
                                {{ ($stats['month_vs_last_month'] ?? 0) >= 0 ? '↑' : '↓' }} 
                                {{ number_format(abs($stats['month_vs_last_month'] ?? 0), 1) }}%
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Inventory Status -->
    <div class="bg-white rounded-xl shadow-md mb-8">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Inventory Status</h2>
                <p class="text-sm text-gray-600">Stock levels and alerts</p>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <!-- Low Stock -->
                <div class="p-4 border {{ ($stats['low_stock_count'] ?? 0) > 0 ? 'border-yellow-200 bg-yellow-50' : 'border-gray-200' }} rounded-lg">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-medium text-gray-700">Low Stock</span>
                        <span class="text-lg font-bold {{ ($stats['low_stock_count'] ?? 0) > 0 ? 'text-yellow-600' : 'text-gray-600' }}">
                            {{ $stats['low_stock_count'] ?? 0 }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600">≤ {{ $stats['low_stock_threshold'] ?? 10 }} units</p>
                </div>

                <!-- Out of Stock -->
                <div class="p-4 border {{ ($stats['out_of_stock_count'] ?? 0) > 0 ? 'border-red-200 bg-red-50' : 'border-gray-200' }} rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="font-medium text-gray-700">Out of Stock</span>
                        <span class="text-lg font-bold {{ ($stats['out_of_stock_count'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-600' }}">
                            {{ $stats['out_of_stock_count'] ?? 0 }}
                        </span>
                    </div>
                </div>

                <!-- Negative Stock -->
                <div class="p-4 border {{ ($stats['negative_stock_count'] ?? 0) > 0 ? 'border-red-200 bg-red-50' : 'border-gray-200' }} rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="font-medium text-gray-700">Negative Stock</span>
                        <span class="text-lg font-bold {{ ($stats['negative_stock_count'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-600' }}">
                            {{ $stats['negative_stock_count'] ?? 0 }}
                        </span>
                    </div>
                </div>

                <!-- Total Stock -->
                <div class="p-4 border border-green-200 bg-green-50 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="font-medium text-gray-700">Total Stock</span>
                        <span class="text-lg font-bold text-green-600">
                            {{ $this->formatNumber($stats['total_stock'] ?? 0) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Low Stock Items Table -->
            @if(count($inventoryStatus) > 0)
                <div class="mt-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Low Stock Items</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-sm text-gray-500 border-b">
                                    <th class="pb-3">SKU</th>
                                    <th class="pb-3">Product</th>
                                    <th class="pb-3">Category</th>
                                    <th class="pb-3">Quantity</th>
                                    <th class="pb-3">Unit Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($inventoryStatus as $item)
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3 text-sm font-medium">{{ $item->sku ?? 'N/A' }}</td>
                                        <td class="py-3">{{ $item->product_name ?? 'Unknown' }}</td>
                                        <td class="py-3 text-sm text-gray-600">{{ $item->category ?? 'N/A' }}</td>
                                        <td class="py-3">
                                            <span class="px-2 py-1 text-xs rounded-full {{ $item->quantity <= 0 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ $item->quantity }}
                                            </span>
                                        </td>
                                        <td class="py-3">{{ $this->formatCurrency($item->unit_price ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Top Products & Top Customers -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Top Products -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Top 5 Products by Sales</h2>
                    <p class="text-sm text-gray-600">Best performing products</p>
                </div>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($topProducts as $product)
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-800">{{ $product->product_name ?? 'Unknown Product' }}</h4>
                                <div class="flex items-center space-x-2 mt-1">
                                    <span class="text-xs text-gray-600">SKU: {{ $product->sku ?? 'N/A' }}</span>
                                    <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">
                                        {{ $this->formatNumber($product->total_qty ?? 0) }} units
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-green-600">{{ $this->formatCurrency($product->total_sales ?? 0) }}</span>
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

        <!-- Top Customers -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Top 5 Customers</h2>
                    <p class="text-sm text-gray-600">Highest spending customers</p>
                </div>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($topCustomers as $customer)
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-800">{{ $customer->customer_name ?? 'Unknown Customer' }}</h4>
                                <div class="flex items-center space-x-2 mt-1">
                                    <span class="text-xs text-gray-600">ID: {{ $customer->customer_id ?? 'N/A' }}</span>
                                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                        {{ $customer->order_count ?? 0 }} orders
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-green-600">{{ $this->formatCurrency($customer->total_spent ?? 0) }}</span>
                                <p class="text-xs text-gray-500">Total spent</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-gray-500">
                            <p>No customer data available</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Sales -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Recent Sales</h2>
                <p class="text-sm text-gray-600">Latest sales transactions</p>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-500 border-b">
                                <th class="pb-3">Date</th>
                                <th class="pb-3">Product</th>
                                <th class="pb-3">Customer</th>
                                <th class="pb-3">Quantity</th>
                                <th class="pb-3">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentSales as $sale)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 text-sm text-gray-600">
                                        {{ $sale->date->format('M d, Y') ?? 'N/A' }}
                                    </td>
                                    <td class="py-3">
                                        {{ $sale->product_name ?? '—' }}
                                    </td>
                                    <td class="py-3 text-sm">
                                        {{ Str::limit($sale->customer_name ?? 'Unknown', 15) }}
                                    </td>
                                    <td class="py-3">
                                        {{ $this->formatNumber($sale->quantity ?? 0) }}
                                    </td>
                                    <td class="py-3 font-medium text-green-600">
                                        {{ $this->formatCurrency($sale->total ?? 0) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-500">
                                        No sales data available
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent E-commerce Orders -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Recent E-commerce Orders</h2>
                <p class="text-sm text-gray-600">Latest online orders</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($recentEcommerceOrders as $order)
                        <div class="p-3 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h4 class="font-medium text-gray-800">Order #{{ $order->external_order_code ?? $order->ecommerce_order_id }}</h4>
                                    <p class="text-sm text-gray-600">
                                        {{ $order->first_name ?? 'Customer' }} {{ $order->last_name ?? '' }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $this->getPaymentStatusColor($order->payment_status ?? '') }}">
                                        {{ ucfirst($order->payment_status ?? 'pending') }}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="flex items-center text-sm text-gray-600">
                                <span class="mr-4">
                                    {{ \Carbon\Carbon::parse($order->order_date)->format('M d, Y') }}
                                </span>
                                <span class="font-medium text-green-600">
                                    {{ $this->formatCurrency($order->total_amount ?? 0) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-gray-500">
                            <p>No e-commerce orders found</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="mt-8 bg-white rounded-xl shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Links</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('sales.home') }}" class="flex flex-col items-center justify-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="font-medium text-gray-800">Sales</span>
            </a>
            
            <a href="{{ route('inventory.home') }}" class="flex flex-col items-center justify-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <span class="font-medium text-gray-800">Inventory</span>
            </a>
            
            <a href="#" class="flex flex-col items-center justify-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="font-medium text-gray-800">Customers</span>
            </a>
            
            <a href="{{ route('reports.home') }}" class="flex flex-col items-center justify-center p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-orange-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span class="font-medium text-gray-800">Reports</span>
            </a>
        </div>
    </div>
</div>
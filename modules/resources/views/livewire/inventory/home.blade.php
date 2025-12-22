<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $inventoryStats = [];
    public array $lowStockItems = [];
    public array $recentTransactions = [];
    public array $warehouseStats = [];
    public array $topProducts = [];
    public array $stockAlerts = [];
    
    public function mount()
    {
        $this->loadInventoryData();
    }
    
    public function loadInventoryData()
    {
        // Calculate inventory statistics
        $this->inventoryStats = [
            'total_items' => DB::table('inventories')->count(),
            'total_quantity' => DB::table('inventories')->sum('quantity') ?? 0,
            'total_value' => DB::table('inventories')
                ->select(DB::raw('SUM(quantity * unit_price) as total_value'))
                ->first()->total_value ?? 0,
            'out_of_stock' => DB::table('inventories')->where('quantity', '<=', 0)->count(),
            'low_stock' => DB::table('inventories')
                ->where('quantity', '<', DB::raw('min_quantity'))
                ->orWhere(function($query) {
                    $query->whereNull('min_quantity')
                          ->where('quantity', '<', 10);
                })
                ->count(),
        ];
        
        // Get low stock items
        $this->lowStockItems = DB::table('inventories')
            ->select('inventory_id', 'sku', 'product_name', 'quantity', 'min_quantity', 'unit_price', 'warehouse')
            ->where('quantity', '<', DB::raw('min_quantity'))
            ->orWhere(function($query) {
                $query->whereNull('min_quantity')
                      ->where('quantity', '<', 10);
            })
            ->orderBy('quantity', 'asc')
            ->limit(10)
            ->get()
            ->toArray();
        
        // Get recent stock transactions
        $this->recentTransactions = DB::table('stock_transactions')
            ->select(
                'stock_transactions.*',
                'inventories.product_name',
                'inventories.sku',
                'users.full_name as created_by_name'
            )
            ->leftJoin('inventories', 'stock_transactions.inventory_id', '=', 'inventories.inventory_id')
            ->leftJoin('users', 'stock_transactions.created_by', '=', 'users.user_id')
            ->orderBy('stock_transactions.created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
        
        // Get warehouse statistics
        $this->warehouseStats = DB::table('inventories')
            ->select(
                'warehouse',
                DB::raw('COUNT(*) as item_count'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(quantity * unit_price) as total_value')
            )
            ->whereNotNull('warehouse')
            ->groupBy('warehouse')
            ->orderBy('total_value', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
        
        // Get top products by value
        $this->topProducts = DB::table('inventories')
            ->select(
                'inventory_id',
                'sku',
                'product_name',
                'category',
                'quantity',
                'unit_price',
                DB::raw('(quantity * unit_price) as total_value')
            )
            ->orderBy('total_value', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
        
        // Get stock alerts (items with zero or negative stock)
        $this->stockAlerts = DB::table('inventories')
            ->where('quantity', '<=', 0)
            ->orderBy('quantity', 'asc')
            ->limit(5)
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
    
    public function getTransactionTypeColor($type)
    {
        return match(strtolower($type)) {
            'in', 'received' => 'bg-green-100 text-green-800',
            'out', 'issued' => 'bg-red-100 text-red-800',
            'adjustment' => 'bg-yellow-100 text-yellow-800',
            'transfer' => 'bg-blue-100 text-blue-800',
            'damage' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    
    public function getStockStatusColor($quantity, $minQuantity = null)
    {
        if ($quantity <= 0) {
            return 'bg-red-100 text-red-800';
        }
        
        $min = $minQuantity ?? 10;
        if ($quantity < $min) {
            return 'bg-yellow-100 text-yellow-800';
        }
        
        if ($quantity >= $min * 2) {
            return 'bg-green-100 text-green-800';
        }
        
        return 'bg-blue-100 text-blue-800';
    }
    
    public function getStockStatus($quantity, $minQuantity = null)
    {
        if ($quantity <= 0) {
            return 'Out of Stock';
        }
        
        $min = $minQuantity ?? 10;
        if ($quantity < $min) {
            return 'Low Stock';
        }
        
        if ($quantity >= $min * 2) {
            return 'In Stock';
        }
        
        return 'Adequate';
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
    
    public function calculateStockValue($quantity, $unitPrice)
    {
        return $quantity * $unitPrice;
    }
    
    public function getCategoryColor($category)
    {
        // Generate consistent color based on category name
        $categories = [
            'electronics' => 'bg-blue-100 text-blue-800',
            'office supplies' => 'bg-green-100 text-green-800',
            'furniture' => 'bg-purple-100 text-purple-800',
            'software' => 'bg-indigo-100 text-indigo-800',
            'raw materials' => 'bg-yellow-100 text-yellow-800',
            'finished goods' => 'bg-pink-100 text-pink-800',
            'consumables' => 'bg-gray-100 text-gray-800',
        ];
        
        $lowerCategory = strtolower($category);
        return $categories[$lowerCategory] ?? 'bg-gray-100 text-gray-800';
    }
};
?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Inventory & Warehouse Dashboard</h1>
        <p class="text-gray-600 mt-2">Monitor stock levels, track movements, and manage warehouse operations</p>
    </div>

    <!-- Inventory Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Items -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Items</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatNumber($inventoryStats['total_items'] ?? 0) }}</h3>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-green-600 font-medium">{{ $this->formatNumber($inventoryStats['total_quantity'] ?? 0) }} units</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Total quantity</span>
                </span>
            </div>
        </div>

        <!-- Total Inventory Value -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Value</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatCurrency($inventoryStats['total_value'] ?? 0) }}</h3>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-blue-600 font-medium">{{ $inventoryStats['total_items'] ?? 0 }} items</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Average value per item</span>
                </span>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-yellow-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Low Stock Items</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $inventoryStats['low_stock'] ?? 0 }}</h3>
                </div>
                <div class="bg-yellow-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.404 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-yellow-600 font-medium">Need restocking</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">{{ $inventoryStats['out_of_stock'] ?? 0 }} out of stock</span>
                </span>
            </div>
        </div>

        <!-- Warehouse Utilization -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Warehouses</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ count($warehouseStats) }}</h3>
                </div>
                <div class="bg-purple-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-purple-600 font-medium">Active locations</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Managing inventory</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Low Stock Items -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Low Stock Alerts</h2>
                    <p class="text-sm text-gray-600">Items that need immediate attention</p>
                </div>
                <a href="{{ route('inventory.home') }}?filter=low_stock" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                    View All
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($lowStockItems as $item)
                        <div class="flex items-center justify-between p-4 hover:bg-yellow-50 rounded-lg border border-yellow-200 transition-colors">
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <h4 class="font-medium text-gray-800">{{ $item->product_name ?? 'Unknown Item' }}</h4>
                                        <p class="text-sm text-gray-600">SKU: {{ $item->sku ?? 'N/A' }}</p>
                                    </div>
                                    <span class="px-2 py-1 text-xs rounded-full {{ $this->getStockStatusColor($item->quantity ?? 0, $item->min_quantity) }}">
                                        {{ $this->getStockStatus($item->quantity ?? 0, $item->min_quantity) }}
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-4 mb-3">
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500">Current Stock</p>
                                        <p class="text-xl font-bold {{ ($item->quantity ?? 0) <= 0 ? 'text-red-600' : 'text-yellow-600' }}">
                                            {{ $item->quantity ?? 0 }}
                                        </p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500">Min Required</p>
                                        <p class="text-xl font-bold text-gray-800">{{ $item->min_quantity ?? 10 }}</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500">Unit Value</p>
                                        <p class="text-xl font-bold text-gray-800">{{ $this->formatCurrency($item->unit_price ?? 0) }}</p>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-600">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                        </svg>
                                        {{ $item->warehouse ?? 'No Warehouse' }}
                                    </span>
                                    <span class="font-medium">
                                        Total Value: {{ $this->formatCurrency($this->calculateStockValue($item->quantity ?? 0, $item->unit_price ?? 0)) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="mt-2">All items are sufficiently stocked</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Recent Transactions</h2>
                    <p class="text-sm text-gray-600">Latest stock movements and adjustments</p>
                </div>
                <a href="{{ route('inventory.tracking') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
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
                                <th class="pb-3">Item</th>
                                <th class="pb-3">Type</th>
                                <th class="pb-3">Qty</th>
                                <th class="pb-3">By</th>
                                <th class="pb-3">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentTransactions as $transaction)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3">
                                        <div>
                                            <p class="font-medium text-gray-800">{{ $transaction->product_name ?? 'Unknown' }}</p>
                                            <p class="text-xs text-gray-600">{{ $transaction->sku ?? 'N/A' }}</p>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 text-xs rounded-full {{ $this->getTransactionTypeColor($transaction->type ?? '') }}">
                                            {{ ucfirst($transaction->type ?? 'unknown') }}
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <span class="font-medium {{ strtolower($transaction->type ?? '') == 'in' ? 'text-green-600' : 'text-red-600' }}">
                                            {{ strtolower($transaction->type ?? '') == 'in' ? '+' : '-' }}{{ $transaction->quantity ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-sm text-gray-600">
                                        {{ $transaction->created_by_name ?? 'System' }}
                                    </td>
                                    <td class="py-3 text-sm text-gray-600">
                                        {{ $this->getTimeAgo($transaction->created_at) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-500">
                                        No transactions found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Warehouse Overview -->
    <div class="bg-white rounded-xl shadow-md mb-8">
        <div class="px-6 py-4 border-b">
            <h2 class="text-xl font-semibold text-gray-800">Warehouse Overview</h2>
            <p class="text-sm text-gray-600">Inventory distribution across warehouses</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Warehouse Statistics -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Warehouse Statistics</h3>
                    <div class="space-y-4">
                        @forelse($warehouseStats as $warehouse)
                            <div class="flex items-center justify-between p-4 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-800">{{ $warehouse->warehouse ?? 'Unknown Warehouse' }}</h4>
                                    <div class="flex items-center space-x-4 mt-2">
                                        <span class="text-sm text-gray-600">
                                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                            </svg>
                                            {{ $warehouse->item_count ?? 0 }} items
                                        </span>
                                        <span class="text-sm text-gray-600">
                                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            {{ $this->formatCurrency($warehouse->total_value ?? 0) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-2xl font-bold text-blue-600">{{ $warehouse->total_quantity ?? 0 }}</span>
                                    <p class="text-xs text-gray-500">Total Units</p>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-6 text-gray-500">
                                <p>No warehouse data available</p>
                            </div>
                        @endforelse
                    </div>
                </div>
                
                <!-- Top Products by Value -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Products by Value</h3>
                    <div class="space-y-4">
                        @forelse($topProducts as $product)
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-800">{{ Str::limit($product->product_name ?? 'Unknown', 25) }}</h4>
                                    <div class="flex items-center space-x-2 mt-1">
                                        @if($product->category)
                                            <span class="px-2 py-1 text-xs rounded-full {{ $this->getCategoryColor($product->category) }}">
                                                {{ $product->category }}
                                            </span>
                                        @endif
                                        <span class="text-xs text-gray-600">SKU: {{ $product->sku ?? 'N/A' }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold">{{ $this->formatCurrency($product->total_value ?? 0) }}</span>
                                    <p class="text-xs text-gray-500">{{ $product->quantity ?? 0 }} units</p>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-6 text-gray-500">
                                <p>No product data available</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Alerts -->
    @if(count($stockAlerts) > 0)
        <div class="mb-8">
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.404 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Critical Stock Alerts</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p>The following items are out of stock and require immediate attention:</p>
                            <ul class="list-disc pl-5 mt-1">
                                @foreach($stockAlerts as $alert)
                                    <li>{{ $alert->product_name ?? 'Unknown Item' }} (SKU: {{ $alert->sku ?? 'N/A' }}) - Quantity: {{ $alert->quantity ?? 0 }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('inventory.stock') }}" class="flex flex-col items-center justify-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span class="font-medium text-gray-800">Add Stock</span>
            </a>
            
            <a href="{{ route('inventory.home') }}?action=adjust" class="flex flex-col items-center justify-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span class="font-medium text-gray-800">Adjust Stock</span>
            </a>
            
            <a href="{{ route('inventory.warehouse') }}" class="flex flex-col items-center justify-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <span class="font-medium text-gray-800">Manage Warehouses</span>
            </a>
            
            <a href="{{ route('inventory.tracking') }}" class="flex flex-col items-center justify-center p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-orange-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                <span class="font-medium text-gray-800">Track Movements</span>
            </a>
        </div>
    </div>
</div>
<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $supplyChainStats = [];
    public array $procurementSummary = [];
    public array $inventorySummary = [];
    public array $logisticsSummary = [];
    public array $recentOrders = [];
    public array $lowStockAlerts = [];
    public array $recentShipments = [];
    
    public function mount()
    {
        $this->loadSupplyChainData();
    }
    
    public function loadSupplyChainData()
    {
        // Supply Chain Overview Statistics
        $this->supplyChainStats = [
            'total_orders' => DB::table('purchase_orders')->count(),
            'pending_receipts' => DB::table('purchase_orders')->where('status', 'sent')->count(),
            'in_transit' => DB::table('purchase_orders')->where('status', 'confirmed')->count(),
            'total_inventory' => DB::table('inventories')->sum('quantity') ?? 0,
            'inventory_value' => DB::table('inventories')
                ->select(DB::raw('SUM(quantity * unit_price) as total_value'))
                ->first()->total_value ?? 0,
            'low_stock_items' => DB::table('inventories')
                ->whereRaw('quantity < min_quantity OR quantity < 10')
                ->count(),
            'active_suppliers' => DB::table('suppliers')->where('status', 'active')->count(),
            'total_shipments' => DB::table('purchase_orders')->whereNotNull('expected_delivery_date')->count(),
        ];
        
        // Procurement Summary
        $this->procurementSummary = [
            'active_suppliers' => $this->supplyChainStats['active_suppliers'],
            'total_orders' => $this->supplyChainStats['total_orders'],
            'pending_orders' => DB::table('purchase_orders')->whereIn('status', ['draft', 'sent'])->count(),
            'low_stock_items' => $this->supplyChainStats['low_stock_items'],
            'recent_po_value' => DB::table('purchase_orders')
                ->where('order_date', '>=', now()->subDays(30))
                ->sum('total_amount') ?? 0,
        ];
        
        // Inventory Summary
        $this->inventorySummary = [
            'total_warehouses' => DB::table('inventories')->distinct('warehouse')->count('warehouse'),
            'total_products' => DB::table('inventories')->count(),
            'total_inventory_value' => $this->supplyChainStats['inventory_value'],
            'low_stock_items' => $this->supplyChainStats['low_stock_items'],
            // FIXED: Removed status check since stock_transactions doesn't have status column
            'pending_transfers' => DB::table('stock_transactions')
                ->where('type', 'transfer')
                ->where('created_at', '>=', now()->subDays(7)) // Filter by recent transfers instead
                ->count(),
        ];
        
        // Logistics Summary
        $this->logisticsSummary = [
            'total_shipments' => $this->supplyChainStats['total_shipments'],
            'in_transit' => $this->supplyChainStats['in_transit'],
            'active_routes' => DB::table('suppliers')
                ->whereNotNull('address')
                ->where('status', 'active')
                ->count(),
            'scheduled_deliveries' => DB::table('purchase_orders')
                ->where('expected_delivery_date', '>=', now())
                ->where('status', 'confirmed')
                ->count(),
        ];
        
        // Recent Purchase Orders
        $this->recentOrders = DB::table('purchase_orders')
            ->select(
                'purchase_orders.*',
                'suppliers.name as supplier_name',
                'suppliers.contact_person'
            )
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.supplier_id')
            ->orderBy('purchase_orders.order_date', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
        
        // Low Stock Alerts
        $this->lowStockAlerts = DB::table('inventories')
            ->whereRaw('quantity < min_quantity OR quantity < 10')
            ->orderBy('quantity', 'asc')
            ->limit(5)
            ->get()
            ->toArray();
        
        // Recent Shipments
        $this->recentShipments = DB::table('purchase_orders')
            ->select(
                'purchase_orders.*',
                'suppliers.name as supplier_name',
                'suppliers.address'
            )
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.supplier_id')
            ->whereNotNull('purchase_orders.expected_delivery_date')
            ->orderBy('purchase_orders.expected_delivery_date', 'asc')
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
    
    public function formatDate($date)
    {
        if (!$date) return 'N/A';
        return date('M d, Y', strtotime($date));
    }
    
    public function getOrderStatusColor($status)
    {
        return match(strtolower($status)) {
            'draft' => 'bg-gray-100 text-gray-800',
            'sent' => 'bg-blue-100 text-blue-800',
            'confirmed' => 'bg-yellow-100 text-yellow-800',
            'partially_received' => 'bg-orange-100 text-orange-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
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
        
        return 'bg-green-100 text-green-800';
    }
    
    public function getShippingStatusColor($expectedDate)
    {
        if (!$expectedDate) return 'bg-gray-100 text-gray-800';
        
        $expected = strtotime($expectedDate);
        $today = time();
        $diff = $expected - $today;
        
        if ($diff < 0) {
            return 'bg-red-100 text-red-800'; // Overdue
        } elseif ($diff < 86400) {
            return 'bg-yellow-100 text-yellow-800'; // Due today
        } elseif ($diff < 86400 * 3) {
            return 'bg-orange-100 text-orange-800'; // Due in 3 days
        } else {
            return 'bg-green-100 text-green-800'; // On schedule
        }
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
    
    public function getDaysUntilDelivery($expectedDate)
    {
        if (!$expectedDate) return 'N/A';
        
        $expected = strtotime($expectedDate);
        $today = time();
        $diff = $expected - $today;
        
        if ($diff < 0) {
            return 'Overdue by ' . abs(floor($diff / 86400)) . ' days';
        } else {
            return floor($diff / 86400) . ' days';
        }
    }
};
?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Supply Chain Dashboard</h1>
        <p class="text-gray-600 mt-2">End-to-end supply chain visibility from procurement to delivery</p>
    </div>

    <!-- Supply Chain Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Orders -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Orders</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatNumber($supplyChainStats['total_orders'] ?? 0) }}</h3>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-green-600 font-medium">{{ $supplyChainStats['pending_receipts'] ?? 0 }} pending</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">{{ $supplyChainStats['in_transit'] ?? 0 }} in transit</span>
                </span>
            </div>
        </div>

        <!-- Inventory Value -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Inventory Value</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatCurrency($supplyChainStats['inventory_value'] ?? 0) }}</h3>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-blue-600 font-medium">{{ $this->formatNumber($supplyChainStats['total_inventory'] ?? 0) }} units</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Total stock</span>
                </span>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-yellow-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Low Stock Alerts</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $supplyChainStats['low_stock_items'] ?? 0 }}</h3>
                </div>
                <div class="bg-yellow-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.404 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-yellow-600 font-medium">Need attention</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Procurement needed</span>
                </span>
            </div>
        </div>

        <!-- Active Suppliers -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Active Suppliers</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $supplyChainStats['active_suppliers'] ?? 0 }}</h3>
                </div>
                <div class="bg-purple-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-purple-600 font-medium">{{ $supplyChainStats['total_shipments'] ?? 0 }} shipments</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">In logistics</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent Orders -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Recent Purchase Orders</h2>
                    <p class="text-sm text-gray-600">Latest procurement activities</p>
                </div>
                <a href="{{ route('procurement.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                    View All
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($recentOrders as $order)
                        <div class="p-4 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h4 class="font-medium text-gray-800">PO #{{ $order->po_number ?? 'N/A' }}</h4>
                                    <p class="text-sm text-gray-600">{{ $order->supplier_name ?? 'Unknown Supplier' }}</p>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full {{ $this->getOrderStatusColor($order->status ?? '') }}">
                                    {{ ucfirst($order->status ?? 'unknown') }}
                                </span>
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
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    {{ $order->contact_person ?? 'No contact' }}
                                </span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-green-600">
                                    {{ $this->formatCurrency($order->total_amount ?? 0) }}
                                </span>
                                <span class="text-sm text-gray-500">
                                    Expected: {{ $this->formatDate($order->expected_delivery_date) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                            <p class="mt-2">No recent orders found</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Recent Shipments -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Upcoming Shipments</h2>
                    <p class="text-sm text-gray-600">Scheduled deliveries and logistics</p>
                </div>
                <a href="{{ route('supplychain.logistics') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                    View All
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($recentShipments as $shipment)
                        <div class="p-4 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h4 class="font-medium text-gray-800">Shipment #{{ $shipment->po_number ?? 'N/A' }}</h4>
                                    <p class="text-sm text-gray-600">{{ $shipment->supplier_name ?? 'Unknown Supplier' }}</p>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full {{ $this->getShippingStatusColor($shipment->expected_delivery_date) }}">
                                    {{ $this->getDaysUntilDelivery($shipment->expected_delivery_date) }}
                                </span>
                            </div>
                            
                            <p class="text-sm text-gray-600 mb-3 truncate">
                                {{ $shipment->address ?? 'No address specified' }}
                            </p>
                            
                            <div class="flex justify-between items-center">
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    {{ $this->formatDate($shipment->expected_delivery_date) }}
                                </div>
                                
                                <div class="text-sm text-gray-500">
                                    Status: {{ ucfirst($shipment->status ?? 'unknown') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"></path>
                            </svg>
                            <p class="mt-2">No shipments scheduled</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Module Summaries -->
    <div class="bg-white rounded-xl shadow-md mb-8">
        <div class="px-6 py-4 border-b">
            <h2 class="text-xl font-semibold text-gray-800">Supply Chain Module Summaries</h2>
            <p class="text-sm text-gray-600">Overview of all supply chain components</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Procurement Summary -->
                <div class="p-4 border-l-4 border-green-500 bg-green-50 rounded-lg">
                    <h3 class="text-lg font-semibold text-green-800 mb-3">🛒 Procurement</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Active Suppliers:</span>
                            <span class="font-medium">{{ $procurementSummary['active_suppliers'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Total Orders:</span>
                            <span class="font-medium">{{ $procurementSummary['total_orders'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Pending Orders:</span>
                            <span class="font-medium {{ ($procurementSummary['pending_orders'] ?? 0) > 0 ? 'text-yellow-600' : 'text-green-600' }}">
                                {{ $procurementSummary['pending_orders'] ?? 0 }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">30-Day PO Value:</span>
                            <span class="font-medium text-green-600">{{ $this->formatCurrency($procurementSummary['recent_po_value'] ?? 0) }}</span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('procurement.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                            View Details
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Inventory Summary -->
                <div class="p-4 border-l-4 border-blue-500 bg-blue-50 rounded-lg">
                    <h3 class="text-lg font-semibold text-blue-800 mb-3">📦 Inventory</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Total Warehouses:</span>
                            <span class="font-medium">{{ $inventorySummary['total_warehouses'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Total Products:</span>
                            <span class="font-medium">{{ $inventorySummary['total_products'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Inventory Value:</span>
                            <span class="font-medium text-blue-600">{{ $this->formatCurrency($inventorySummary['total_inventory_value'] ?? 0) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Low Stock Items:</span>
                            <span class="font-medium {{ ($inventorySummary['low_stock_items'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ $inventorySummary['low_stock_items'] ?? 0 }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('inventory.home') }}" class="text-blue-600 hover:text-blue-800 font-medium text-sm flex items-center">
                            View Details
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                        </a>
                    </div>
                </div>

                <!-- Logistics Summary -->
                <div class="p-4 border-l-4 border-purple-500 bg-purple-50 rounded-lg">
                    <h3 class="text-lg font-semibold text-purple-800 mb-3">🚚 Logistics</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Total Shipments:</span>
                            <span class="font-medium">{{ $logisticsSummary['total_shipments'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">In Transit:</span>
                            <span class="font-medium text-purple-600">{{ $logisticsSummary['in_transit'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Active Routes:</span>
                            <span class="font-medium">{{ $logisticsSummary['active_routes'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Scheduled Deliveries:</span>
                            <span class="font-medium">{{ $logisticsSummary['scheduled_deliveries'] ?? 0 }}</span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('supplychain.logistics') }}" class="text-purple-600 hover:text-purple-800 font-medium text-sm flex items-center">
                            View Details
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Supply Chain Health -->
                <div class="p-4 border-l-4 border-orange-500 bg-orange-50 rounded-lg">
                    <h3 class="text-lg font-semibold text-orange-800 mb-3">📊 Supply Chain Health</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Stock Coverage:</span>
                            <span class="font-medium {{ ($supplyChainStats['low_stock_items'] ?? 0) > 5 ? 'text-red-600' : 'text-green-600' }}">
                                {{ $this->formatNumber($supplyChainStats['total_inventory'] ?? 0) }} units
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Supplier Performance:</span>
                            <span class="font-medium text-green-600">Good</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Delivery Accuracy:</span>
                            <span class="font-medium text-green-600">95%</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Order Fulfillment:</span>
                            <span class="font-medium text-green-600">98%</span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('supplychain.home') }}" class="text-orange-600 hover:text-orange-800 font-medium text-sm flex items-center">
                            View Analytics
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('procurement.purchase-orders') }}" class="flex flex-col items-center justify-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span class="font-medium text-gray-800">Create PO</span>
            </a>
            
            <a href="{{ route('inventory.home') }}" class="flex flex-col items-center justify-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <span class="font-medium text-gray-800">Manage Stock</span>
            </a>
            
            <a href="{{ route('supplychain.logistics') }}" class="flex flex-col items-center justify-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"></path>
                </svg>
                <span class="font-medium text-gray-800">Track Shipments</span>
            </a>
            
            <a href="{{ route('procurement.vendors') }}" class="flex flex-col items-center justify-center p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-orange-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="font-medium text-gray-800">Manage Suppliers</span>
            </a>
        </div>
    </div>
</div>
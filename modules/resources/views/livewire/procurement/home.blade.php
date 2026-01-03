<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $procurementStats = [];
    public array $pendingRequisitions = [];
    public array $recentOrders = [];
    public array $topSuppliers = [];
    public array $lowStockAlerts = [];
    public array $pendingApprovals = [];
    
    public function mount()
    {
        $this->loadProcurementData();
    }

    public function formatNumber($number)
    {
        return number_format($number);
    }
    
    public function loadProcurementData()
    {
        // Procurement Statistics
        $this->procurementStats = [
            'total_requisitions' => DB::table('purchase_requisitions')->count(),
            'pending_requisitions' => DB::table('purchase_requisitions')->where('status', 'draft')->count(),
            'pending_approvals' => DB::table('purchase_requisitions')->where('status', 'submitted')->count(),
            'approved_requisitions' => DB::table('purchase_requisitions')->where('status', 'approved')->count(),
            'total_orders' => DB::table('purchase_orders')->count(),
            'pending_orders' => DB::table('purchase_orders')->where('status', 'draft')->count(),
            'total_spent' => DB::table('purchase_orders')->where('status', 'completed')->sum('total_amount') ?? 0,
            'active_suppliers' => DB::table('suppliers')->where('status', 'active')->count(),
        ];
        
        // Pending Requisitions (for approval)
        $this->pendingApprovals = DB::table('purchase_requisitions')
            ->select(
                'purchase_requisitions.*',
                'users.full_name as requested_by_name',
                'departments.department_name'
            )
            ->leftJoin('users', 'purchase_requisitions.requested_by', '=', 'users.user_id')
            ->leftJoin('departments', 'purchase_requisitions.department_id', '=', 'departments.department_id')
            ->where('purchase_requisitions.status', 'submitted')
            ->orderBy('purchase_requisitions.date_requested', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
        
        // Recent Purchase Orders
        $this->recentOrders = DB::table('purchase_orders')
            ->select(
                'purchase_orders.*',
                'suppliers.name as supplier_name',
                'suppliers.contact_person',
                'users.full_name as created_by_name'
            )
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.supplier_id')
            ->leftJoin('users', 'purchase_orders.created_by', '=', 'users.user_id')
            ->orderBy('purchase_orders.order_date', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
        
        // Top Suppliers
        $this->topSuppliers = DB::table('purchase_orders')
            ->select(
                'suppliers.supplier_id',
                'suppliers.name',
                'suppliers.rating',
                DB::raw('COUNT(purchase_orders.po_id) as order_count'),
                DB::raw('SUM(purchase_orders.total_amount) as total_spent')
            )
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.supplier_id')
            ->where('purchase_orders.status', 'completed')
            ->groupBy('suppliers.supplier_id', 'suppliers.name', 'suppliers.rating')
            ->orderBy('total_spent', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
        
        // Low Stock Alerts (from inventory for procurement)
        $this->lowStockAlerts = DB::table('inventories')
            ->whereRaw('quantity < min_quantity OR quantity < 10')
            ->orderBy('quantity', 'asc')
            ->limit(5)
            ->get()
            ->toArray();
    }
    
    public function formatCurrency($amount)
    {
        return '₱' . number_format($amount, 2);
    }
    
    public function formatDate($date)
    {
        if (!$date) return 'N/A';
        return date('M d, Y', strtotime($date));
    }
    
    public function getRequisitionStatusColor($status)
    {
        return match(strtolower($status)) {
            'draft' => 'bg-gray-100 text-gray-800',
            'submitted' => 'bg-blue-100 text-blue-800',
            'approved' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            'converted_to_po' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
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
};
?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Procurement Dashboard</h1>
        <p class="text-gray-600 mt-2">Manage purchase requisitions, orders, and supplier relationships</p>
    </div>

    <!-- Procurement Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Requisitions -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Requisitions</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatNumber($procurementStats['total_requisitions'] ?? 0) }}</h3>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-green-600 font-medium">{{ $procurementStats['pending_approvals'] ?? 0 }} pending approval</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">{{ $procurementStats['approved_requisitions'] ?? 0 }} approved</span>
                </span>
            </div>
        </div>

        <!-- Total Orders -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Orders</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatNumber($procurementStats['total_orders'] ?? 0) }}</h3>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-blue-600 font-medium">{{ $procurementStats['pending_orders'] ?? 0 }} pending</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Orders processing</span>
                </span>
            </div>
        </div>

        <!-- Total Spent -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Spent</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatCurrency($procurementStats['total_spent'] ?? 0) }}</h3>
                </div>
                <div class="bg-purple-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-purple-600 font-medium">{{ $procurementStats['active_suppliers'] ?? 0 }} suppliers</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Active vendors</span>
                </span>
            </div>
        </div>

        <!-- Approval Rate -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Approval Rate</p>
                    <h3 class="text-2xl font-bold text-gray-800">
                        @if(($procurementStats['total_requisitions'] ?? 0) > 0)
                            {{ round((($procurementStats['approved_requisitions'] ?? 0) / ($procurementStats['total_requisitions'] ?? 1)) * 100, 1) }}%
                        @else
                            0%
                        @endif
                    </h3>
                </div>
                <div class="bg-orange-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-orange-600 font-medium">{{ $procurementStats['approved_requisitions'] ?? 0 }} approved</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">of {{ $procurementStats['total_requisitions'] ?? 0 }} total</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Pending Approvals -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Pending Approvals</h2>
                    <p class="text-sm text-gray-600">Requisitions awaiting managerial approval</p>
                </div>
                <a href="#" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                    View All
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($pendingApprovals as $requisition)
                        <div class="p-4 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h4 class="font-medium text-gray-800">Requisition #{{ $requisition->requisition_id ?? 'N/A' }}</h4>
                                    <p class="text-sm text-gray-600">{{ $requisition->requested_by_name ?? 'Unknown' }}</p>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full {{ $this->getRequisitionStatusColor($requisition->status ?? '') }}">
                                    {{ ucfirst(str_replace('_', ' ', $requisition->status ?? 'unknown')) }}
                                </span>
                            </div>
                            
                            <div class="flex items-center text-sm text-gray-600 mb-3">
                                <span class="mr-4">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    {{ $requisition->department_name ?? 'No Department' }}
                                </span>
                                <span>
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    {{ $this->formatDate($requisition->date_requested) }}
                                </span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-green-600">
                                    {{ $this->formatCurrency($requisition->estimated_cost ?? 0) }}
                                </span>
                                <div class="space-x-2">
                                    <button wire:click="approve({{ $requisition->requisition_id }})" class="px-3 py-1 text-xs bg-green-100 text-green-700 hover:bg-green-200 rounded">
                                        Approve
                                    </button>
                                    <button wire:click="startReject({{ $requisition->requisition_id }})" class="px-3 py-1 text-xs bg-red-100 text-red-700 hover:bg-red-200 rounded">
                                        Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="mt-2">No pending approvals</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Recent Orders</h2>
                    <p class="text-sm text-gray-600">Latest purchase orders placed</p>
                </div>
                <a href="#" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
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
                                <th class="pb-3">Supplier</th>
                                <th class="pb-3">Date</th>
                                <th class="pb-3">Amount</th>
                                <th class="pb-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3">
                                        <a href="#" class="text-green-600 hover:text-green-800 font-medium">
                                            #{{ $order->po_number ?? 'N/A' }}
                                        </a>
                                    </td>
                                    <td class="py-3 text-sm">
                                        {{ Str::limit($order->supplier_name ?? 'Unknown', 15) }}
                                    </td>
                                    <td class="py-3 text-sm text-gray-600">
                                        {{ $this->formatDate($order->order_date) }}
                                    </td>
                                    <td class="py-3 font-medium">
                                        {{ $this->formatCurrency($order->total_amount ?? 0) }}
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 text-xs rounded-full {{ $this->getOrderStatusColor($order->status ?? '') }}">
                                            {{ ucfirst($order->status ?? 'unknown') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-500">
                                        No orders found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Top Suppliers -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Top Suppliers</h2>
                <p class="text-sm text-gray-600">Highest value suppliers by spending</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($topSuppliers as $supplier)
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-gray-800">{{ $supplier->name ?? 'Unknown Supplier' }}</h4>
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                        <span class="ml-1 text-sm text-gray-600">{{ $supplier->rating ?? 'N/A' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <span class="mr-4">
                                        {{ $supplier->order_count ?? 0 }} orders
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-green-600 font-bold">{{ $this->formatCurrency($supplier->total_spent ?? 0) }}</span>
                                <p class="text-xs text-gray-500">Total spent</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-gray-500">
                            <p>No supplier data available</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Low Stock Alerts</h2>
                <p class="text-sm text-gray-600">Items that need procurement attention</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($lowStockAlerts as $item)
                        <div class="flex items-center justify-between p-3 hover:bg-red-50 rounded-lg transition-colors">
                            <div>
                                <h4 class="font-medium text-gray-800">{{ $item->product_name ?? 'Unknown Item' }}</h4>
                                <p class="text-sm text-gray-600">{{ $item->sku ?? 'No SKU' }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-red-600 font-bold">{{ $item->quantity ?? 0 }}</span>
                                <p class="text-xs text-gray-500">in stock</p>
                                <p class="text-xs text-gray-500">Min: {{ $item->min_quantity ?? 10 }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-gray-500">
                            <p>All items are sufficiently stocked</p>
                        </div>
                    @endforelse
                </div>
                <div class="mt-6">
                    <a href="{{ route('inventory.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                        View inventory
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('procurement.create') }}" class="flex flex-col items-center justify-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span class="font-medium text-gray-800">New Requisition</span>
            </a>
            
            <a href="{{ route('procurement.createpo') }}" class="flex flex-col items-center justify-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
                <span class="font-medium text-gray-800">Create PO</span>
            </a>
            
            <a href="{{ route('supplier.list') }}" class="flex flex-col items-center justify-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="font-medium text-gray-800">Manage Suppliers</span>
            </a>
            
            <a href="#" class="flex flex-col items-center justify-center p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-orange-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="font-medium text-gray-800">Contracts</span>
            </a>
        </div>
    </div>
</div>
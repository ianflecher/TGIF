<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.supplier')] class extends Component
{
    public $purchaseOrders = [];
    public $selectedOrder = null;
    public $orderItems = [];
    public $message = '';
    
    public function mount()
    {
        // Get the logged-in supplier from session
        $supplier = session('supplier');
        
        if (!$supplier) {
            abort(403, 'Supplier not authenticated');
        }
        
        $this->loadOrders($supplier->supplier_id);
    }
    
    public function loadOrders($supplierId)
    {

        
        try {
            // First, check if there are any orders for this supplier
            $ordersCount = DB::table('purchase_orders')
                ->where('supplier_id', $supplierId)
                ->count();

            
            // Try query without join first
            $testQuery = DB::table('purchase_orders as po')
                ->where('po.supplier_id', $supplierId)
                ->whereIn('po.status', ['sent', 'confirmed', 'partially_received', 'draft'])
                ->select(
                    'po.po_id',
                    'po.po_number',
                    'po.order_date',
                    'po.expected_delivery_date',
                    'po.total_amount',
                    'po.status',
                    'po.terms',
                    'po.created_by'
                )
                ->orderBy('po.order_date', 'desc')
                ->get();
            
            // If there are results, check if join is causing issues
            if (count($testQuery) > 0) {
                $this->purchaseOrders = DB::table('purchase_orders as po')
                    ->leftJoin('users as u', 'po.created_by', '=', 'u.user_id')
                    ->where('po.supplier_id', $supplierId)
                    ->whereIn('po.status', ['sent', 'confirmed', 'partially_received', 'draft'])
                    ->select(
                        'po.po_id',
                        'po.po_number',
                        'po.order_date',
                        'po.expected_delivery_date',
                        'po.total_amount',
                        'po.status',
                        'po.terms',
                        'u.full_name as created_by_name'
                    )
                    ->orderBy('po.order_date', 'desc')
                    ->get()
                    ->toArray();
                    
            }
            
        } catch (\Exception $e) {
            Log::error('Error loading orders: ' . $e->getMessage());
        }
    }
    
    public function viewOrder($poId)
    {
        $this->selectedOrder = DB::table('purchase_orders')
            ->where('po_id', $poId)
            ->first();
            
        if ($this->selectedOrder) {
            $this->orderItems = DB::table('purchase_order_items as poi')
                ->leftJoin('inventories as i', 'poi.inventory_id', '=', 'i.inventory_id')
                ->leftJoin('products as p', 'i.inventory_id', '=', 'p.inventory_id')
                ->where('poi.po_id', $poId)
                ->select(
                    'poi.*',
                    'i.product_name',
                    'i.sku',
                    'p.category',
                    DB::raw('IFNULL(p.product_name, i.product_name) as display_name')
                )
                ->get()
                ->toArray();
        }
    }
    
    public function closeOrderView()
    {
        $this->selectedOrder = null;
        $this->orderItems = [];
    }
    
    public function acceptOrder($poId)
    {
        DB::table('purchase_orders')
            ->where('po_id', $poId)
            ->update([
                'status' => 'confirmed',
                'updated_at' => Carbon::now()
            ]);
            
        $this->message = 'Purchase order accepted successfully!';
        $this->loadOrders(session('supplier')->supplier_id);
        $this->closeOrderView();
    }
    
    public function markAsDelivered($poId)
    {
        DB::beginTransaction();
        
        try {
            // Update PO status to partially_received (first delivery)
            DB::table('purchase_orders')
                ->where('po_id', $poId)
                ->update([
                    'status' => 'partially_received',
                    'updated_at' => Carbon::now()
                ]);
            
            DB::commit();
            
            $this->message = 'Order marked as partially delivered!';
            $this->loadOrders(session('supplier')->supplier_id);
            $this->closeOrderView();
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->message = 'Error: ' . $e->getMessage();
        }
    }
    
    public function getStatusColor($status)
    {
        switch ($status) {
            case 'sent': return 'bg-amber-100 text-amber-800';
            case 'confirmed': return 'bg-emerald-100 text-emerald-800';
            case 'partially_received': return 'bg-teal-100 text-teal-800';
            case 'completed': return 'bg-green-100 text-green-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    }
    
    public function getStatusText($status)
    {
        switch ($status) {
            case 'sent': return 'Sent (Awaiting Confirmation)';
            case 'confirmed': return 'Confirmed (Ready for Delivery)';
            case 'partially_received': return 'Delivery in Progress';
            case 'completed': return 'Completed';
            default: return ucfirst($status);
        }
    }
}
?>

<div class="p-6 min-h-screen bg-gradient-to-br from-green-50 to-emerald-50">
    @php
        $supplier = session('supplier');
    @endphp
    
    <!-- Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-emerald-900">Supplier Dashboard</h1>
                <p class="text-emerald-700 mt-2">Welcome back, <span class="font-semibold">{{ $supplier->name }}</span></p>
                <div class="flex items-center mt-1 text-sm text-gray-600">
                    <i class="fas fa-envelope mr-2"></i>
                    <span>{{ $supplier->email }}</span>
                    @if($supplier->contact_person)
                        <span class="mx-3">•</span>
                        <i class="fas fa-user mr-2"></i>
                        <span>{{ $supplier->contact_person }}</span>
                    @endif
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-4 border border-emerald-100">
                <div class="text-center">
                    <div class="text-2xl font-bold text-emerald-700">{{ count($purchaseOrders) }}</div>
                    <div class="text-sm text-emerald-600">Active Orders</div>
                </div>
            </div>
        </div>
    </div>
    
    @if($message)
        <div class="mb-6 p-4 rounded-lg {{ str_contains($message, 'Error') ? 'bg-red-100 text-red-800 border-l-4 border-red-600' : 'bg-emerald-100 text-emerald-800 border-l-4 border-emerald-600' }}">
            <div class="flex items-center">
                @if(str_contains($message, 'Error'))
                    <i class="fas fa-exclamation-circle mr-3"></i>
                @else
                    <i class="fas fa-check-circle mr-3"></i>
                @endif
                <span>{{ $message }}</span>
            </div>
        </div>
    @endif
    
    <!-- Purchase Orders Section -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-8 border border-emerald-100">
        <div class="bg-gradient-to-r from-emerald-600 to-green-600 px-6 py-4">
            <h2 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-file-invoice-dollar mr-3"></i>
                Purchase Orders ({{ count($purchaseOrders) }})
            </h2>
            <p class="text-emerald-100 text-sm mt-1">Orders awaiting your confirmation or delivery</p>
        </div>
        
        <div class="p-6">
            @if(count($purchaseOrders) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-emerald-50">
                            <tr>
                                <th class="p-3 text-left text-emerald-800 font-semibold">PO Number</th>
                                <th class="p-3 text-left text-emerald-800 font-semibold">Order Date</th>
                                <th class="p-3 text-left text-emerald-800 font-semibold">Expected Delivery</th>
                                <!-- <th class="p-3 text-left text-emerald-800 font-semibold">Total Amount</th> -->
                                <th class="p-3 text-left text-emerald-800 font-semibold">Status</th>
                                <th class="p-3 text-left text-emerald-800 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-emerald-50">
                            @foreach($purchaseOrders as $order)
                                <tr class="hover:bg-emerald-50 transition-colors">
                                    <td class="p-3 font-mono font-bold text-emerald-900">{{ $order->po_number }}</td>
                                    <td class="p-3 text-emerald-800">
                                        {{ Carbon::parse($order->order_date)->format('M d, Y') }}
                                    </td>
                                    <td class="p-3 text-emerald-800">
                                        {{ Carbon::parse($order->expected_delivery_date)->format('M d, Y') }}
                                    </td>
                                    <!-- <td class="p-3">
                                        <div class="font-bold text-green-700">₱{{ number_format($order->total_amount, 2) }}</div>
                                    </td> -->
                                    <td class="p-3">
                                        <span class="px-3 py-1 rounded-full text-xs font-medium {{ $this->getStatusColor($order->status) }}">
                                            {{ $this->getStatusText($order->status) }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex gap-2">
                                            <button wire:click="viewOrder({{ $order->po_id }})"
                                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center">
                                                <i class="fas fa-eye mr-2"></i>
                                                View
                                            </button>
                                            
                                            @if($order->status === 'draft')
                                                <button wire:click="acceptOrder({{ $order->po_id }})"
                                                        onclick="return confirm('Confirm acceptance of PO #{{ $order->po_number }}?')"
                                                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center">
                                                    <i class="fas fa-check-circle mr-2"></i>
                                                    Accept
                                                </button>
                                            @endif
                                            
                                            @if($order->status === 'confirmed')
                                                <button wire:click="markAsDelivered({{ $order->po_id }})"
                                                        onclick="return confirm('Start delivery for PO #{{ $order->po_number }}? This will mark the order as "Delivery in Progress".')"
                                                        class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-medium rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center">
                                                    <i class="fas fa-truck mr-2"></i>
                                                    Start Delivery
                                                </button>
                                            @endif
                                            
                                            @if($order->status === 'partially_received')
                                                <button class="px-4 py-2 bg-gray-300 text-gray-500 font-medium rounded-lg cursor-not-allowed flex items-center"
                                                        disabled>
                                                    <i class="fas fa-truck-loading mr-2"></i>
                                                    Delivery in Progress
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <div class="text-emerald-400 mb-4">
                        <i class="fas fa-file-invoice text-5xl"></i>
                    </div>
                    <p class="text-emerald-700">No purchase orders found</p>
                    <p class="text-sm text-emerald-600 mt-1">All orders have been processed or you have no active orders</p>
                </div>
            @endif
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6 border border-amber-100 hover:shadow-xl transition-shadow duration-300">
            <div class="flex items-center">
                <div class="bg-amber-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-paper-plane text-amber-600 text-xl"></i>
                </div>
                <div>
                    @php
                        $sentCount = collect($purchaseOrders)->where('status', 'sent')->count();
                    @endphp
                    <p class="text-sm text-amber-700">Awaiting Confirmation</p>
                    <p class="text-2xl font-bold text-amber-800">{{ $sentCount }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-6 border border-emerald-100 hover:shadow-xl transition-shadow duration-300">
            <div class="flex items-center">
                <div class="bg-emerald-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-check-circle text-emerald-600 text-xl"></i>
                </div>
                <div>
                    @php
                        $confirmedCount = collect($purchaseOrders)->where('status', 'confirmed')->count();
                    @endphp
                    <p class="text-sm text-emerald-700">Ready for Delivery</p>
                    <p class="text-2xl font-bold text-emerald-800">{{ $confirmedCount }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-6 border border-teal-100 hover:shadow-xl transition-shadow duration-300">
            <div class="flex items-center">
                <div class="bg-teal-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-truck-loading text-teal-600 text-xl"></i>
                </div>
                <div>
                    @php
                        $partiallyCount = collect($purchaseOrders)->where('status', 'partially_received')->count();
                    @endphp
                    <p class="text-sm text-teal-700">Delivery in Progress</p>
                    <p class="text-2xl font-bold text-teal-800">{{ $partiallyCount }}</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    @if($selectedOrder)
        <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden border border-emerald-200">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-emerald-600 to-green-600 px-6 py-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-bold text-white">
                                Purchase Order Details
                            </h3>
                            <p class="text-emerald-100 text-sm mt-1">
                                PO #{{ $selectedOrder->po_number }} • {{ Carbon::parse($selectedOrder->order_date)->format('M d, Y') }}
                            </p>
                        </div>
                        <button wire:click="closeOrderView" 
                                class="text-white hover:text-emerald-200 transition-colors">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Content -->
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <!-- Order Summary -->
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-emerald-50 p-4 rounded-lg border border-emerald-100">
                            <h4 class="font-semibold text-emerald-800 mb-2">Order Information</h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-emerald-600">Order Date:</span>
                                    <span class="font-medium text-emerald-800">{{ Carbon::parse($selectedOrder->order_date)->format('M d, Y') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-emerald-600">Expected Delivery:</span>
                                    <span class="font-medium text-emerald-800">{{ Carbon::parse($selectedOrder->expected_delivery_date)->format('M d, Y') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-emerald-600">Status:</span>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium {{ $this->getStatusColor($selectedOrder->status) }}">
                                        {{ $this->getStatusText($selectedOrder->status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-green-50 p-4 rounded-lg border border-green-100">
                            <h4 class="font-semibold text-green-800 mb-2">Payment & Terms</h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-green-600">Total Amount:</span>
                                    <span class="font-bold text-green-700">₱{{ number_format($selectedOrder->total_amount, 2) }}</span>
                                </div>
                                @if($selectedOrder->terms)
                                    <div class="mt-2">
                                        <span class="text-green-600">Terms:</span>
                                        <p class="font-medium text-gray-800">{{ $selectedOrder->terms }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <!-- Items List -->
                    <div class="mb-6">
                        <h4 class="font-semibold text-emerald-800 mb-3 flex items-center">
                            <i class="fas fa-list mr-2"></i>
                            <span>Order Items ({{ count($orderItems) }})</span>
                        </h4>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-emerald-50">
                                    <tr>
                                        <th class="p-3 text-left text-emerald-800 font-semibold">Product</th>
                                        <th class="p-3 text-left text-emerald-800 font-semibold">SKU</th>
                                        <th class="p-3 text-left text-emerald-800 font-semibold">Quantity</th>
                                        <th class="p-3 text-left text-emerald-800 font-semibold">Unit Price</th>
                                        <th class="p-3 text-left text-emerald-800 font-semibold">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-emerald-50">
                                    @foreach($orderItems as $item)
                                        <tr class="hover:bg-emerald-50">
                                            <td class="p-3">
                                                <div class="font-medium text-emerald-900">{{ $item->display_name ?? 'Product #' . $item->inventory_id }}</div>
                                                @if($item->category)
                                                    <div class="text-xs text-emerald-600">{{ $item->category }}</div>
                                                @endif
                                                @if($item->description)
                                                    <div class="text-xs text-emerald-500 mt-1">{{ $item->description }}</div>
                                                @endif
                                            </td>
                                            <td class="p-3 font-mono text-emerald-700">{{ $item->sku ?? 'N/A' }}</td>
                                            <td class="p-3 font-bold text-emerald-700">{{ $item->quantity }}</td>
                                            <td class="p-3 text-emerald-800">₱{{ number_format($item->unit_price, 2) }}</td>
                                            <td class="p-3 font-bold text-green-700">₱{{ number_format($item->total_price, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-emerald-50">
                                    <tr>
                                        <td colspan="4" class="p-3 text-right font-bold text-emerald-800">Grand Total:</td>
                                        <td class="p-3 font-bold text-lg text-green-700">₱{{ number_format($selectedOrder->total_amount, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="border-t border-emerald-100 px-6 py-4 bg-emerald-50">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-emerald-700">
                            <i class="fas fa-user-circle mr-1"></i>
                            Created by: {{ $selectedOrder->created_by_name ?? 'System' }}
                        </div>
                        <div class="flex gap-3">
                            <button wire:click="closeOrderView"
                                    class="px-6 py-3 border border-emerald-300 text-emerald-700 font-medium rounded-lg hover:bg-emerald-50 transition-colors">
                                Close
                            </button>
                            
                            @if($selectedOrder->status === 'sent')
                                <button wire:click="acceptOrder({{ $selectedOrder->po_id }})"
                                        onclick="return confirm('Confirm acceptance of PO #{{ $selectedOrder->po_number }}?')"
                                        class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    Accept Order
                                </button>
                            @endif
                            
                            @if($selectedOrder->status === 'confirmed')
                                <button wire:click="markAsDelivered({{ $selectedOrder->po_id }})"
                                        onclick="return confirm('Start delivery for PO #{{ $selectedOrder->po_number }}? This will mark the order as "Delivery in Progress".')"
                                        class="px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white font-medium rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center">
                                    <i class="fas fa-truck mr-2"></i>
                                    Start Delivery
                                </button>
                            @endif
                            
                            @if($selectedOrder->status === 'partially_received')
                                <button class="px-6 py-3 bg-gray-300 text-gray-500 font-medium rounded-lg cursor-not-allowed"
                                        disabled>
                                    <i class="fas fa-truck-loading mr-2"></i>
                                    Delivery in Progress
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div> 
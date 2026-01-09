<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.employeeland')] #[Title('Warehouse - Order Completion')] class extends Component
{
    public $pendingOrders = [];
    public $selectedOrder = null;
    public $orderItems = [];
    public $receiptHistory = [];
    public $message = '';
    
    // Receiving form for multiple items
    public $receipts = [];
    
    public function mount()
    {
        $this->loadPendingOrders();
    }
    
    public function loadPendingOrders()
    {
        // Load orders that are partially_received (delivery in progress)
        $this->pendingOrders = DB::table('purchase_orders as po')
            ->join('suppliers as s', 'po.supplier_id', '=', 's.supplier_id')
            ->join('users as u', 'po.created_by', '=', 'u.user_id')
            ->where('po.status', 'partially_received')
            ->select(
                'po.po_id',
                'po.po_number',
                'po.order_date',
                'po.expected_delivery_date',
                'po.total_amount',
                'po.status',
                'po.terms',
                's.name as supplier_name',
                's.rating as supplier_rating',
                'u.full_name as created_by_name'
            )
            ->orderBy('po.order_date', 'desc')
            ->get()
            ->toArray();
    }
    
    public function viewOrder($poId)
    {
        $this->selectedOrder = DB::table('purchase_orders as po')
            ->join('suppliers as s', 'po.supplier_id', '=', 's.supplier_id')
            ->join('users as u', 'po.created_by', '=', 'u.user_id')
            ->where('po.po_id', $poId)
            ->select(
                'po.*',
                's.name as supplier_name',
                's.rating as supplier_rating',
                'u.full_name as created_by_name'
            )
            ->first();
            
        if ($this->selectedOrder) {
            // Get order items - FIXED: changed current_stock to quantity
            $this->orderItems = DB::table('purchase_order_items as poi')
                ->join('inventories as i', 'poi.inventory_id', '=', 'i.inventory_id')
                ->where('poi.po_id', $poId)
                ->select(
                    'poi.*',
                    'i.product_name',
                    'i.sku',
                    'i.quantity as current_stock' // FIXED HERE
                )
                ->get()
                ->toArray();
            
            // Get receipt history for this PO
            $this->receiptHistory = DB::table('goods_receipts as gr')
                ->join('inventories as i', 'gr.inventory_id', '=', 'i.inventory_id')
                ->leftJoin('users as u', 'gr.received_by', '=', 'u.user_id')
                ->where('gr.po_id', $poId)
                ->select(
                    'gr.*',
                    'i.product_name',
                    'i.sku',
                    'u.full_name as received_by_name'
                )
                ->orderBy('gr.date_received', 'desc')
                ->get()
                ->toArray();
            
            // Initialize receipt form
            $this->initializeReceipts();
        }
    }
    
    public function initializeReceipts()
    {
        $this->receipts = [];
        foreach ($this->orderItems as $item) {
            // Calculate remaining quantity
            $receivedQty = DB::table('goods_receipts')
                ->where('po_id', $this->selectedOrder->po_id)
                ->where('inventory_id', $item->inventory_id)
                ->where('quality_status', 'passed')
                ->sum('quantity_received');
            
            $remainingQty = $item->quantity - $receivedQty;
            
            $this->receipts[] = [
                'inventory_id' => $item->inventory_id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'ordered_qty' => $item->quantity,
                'received_qty' => $receivedQty,
                'remaining_qty' => $remainingQty,
                'quantity_received' => $remainingQty > 0 ? $remainingQty : 0,
                'batch_number' => '',
                'expiry_date' => '',
                'quality_status' => 'passed',
                'notes' => '',
                'supplier_rating' => $this->selectedOrder->supplier_rating ?? 5
            ];
        }
    }
    
    public function closeOrderView()
    {
        $this->selectedOrder = null;
        $this->orderItems = [];
        $this->receiptHistory = [];
        $this->receipts = [];
    }
    
   public function completeOrder()
{
    $this->validate([
        'receipts.*.quantity_received' => 'required|numeric|min:0',
        'receipts.*.quality_status' => 'required|in:passed,failed',
        'receipts.*.supplier_rating' => 'required|numeric|min:1|max:5',
        'receipts.*.expiry_date' => 'nullable|date',
    ]);
    
    DB::beginTransaction();
    
    try {
        $hasValidReceipts = false;
        
        foreach ($this->receipts as $receipt) {
            if ($receipt['quantity_received'] > 0) {
                $hasValidReceipts = true;
                
                // Convert empty expiry_date to NULL
                $expiryDate = !empty($receipt['expiry_date']) ? $receipt['expiry_date'] : null;
                
                // Create goods receipt
                DB::table('goods_receipts')->insert([
                    'po_id' => $this->selectedOrder->po_id,
                    'inventory_id' => $receipt['inventory_id'],
                    'quantity_received' => $receipt['quantity_received'],
                    'date_received' => Carbon::today(),
                    'unit_price' => $this->getItemUnitPrice($receipt['inventory_id']),
                    'batch_number' => $receipt['batch_number'] ?: null,
                    'expiry_date' => $expiryDate,
                    'notes' => $receipt['notes'] ?: null,
                    'quality_status' => $receipt['quality_status'],
                    'received_by' => Auth::id(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
                
                // Update inventory if quality passed
                if ($receipt['quality_status'] === 'passed') {
                    DB::table('inventories')
                        ->where('inventory_id', $receipt['inventory_id'])
                        ->increment('quantity', $receipt['quantity_received']);
                }
            }
        }
        
        // Update supplier rating based on all receipts in this order
        if ($hasValidReceipts) {
            $this->updateSupplierRating();
            
            // Check if order is now complete
            if ($this->isOrderComplete()) {
                DB::table('purchase_orders')
                    ->where('po_id', $this->selectedOrder->po_id)
                    ->update([
                        'status' => 'completed',
                        'updated_at' => Carbon::now()
                    ]);
                
                $this->message = 'Order completed successfully! All items received and inventory updated.';
            } else {
                $this->message = 'Partial delivery recorded successfully! Order still in progress.';
            }
            
            $this->loadPendingOrders();
            $this->closeOrderView();
        } else {
            $this->message = 'No quantities were entered. Please enter quantities to receive.';
        }
        
        DB::commit();
        
    } catch (\Exception $e) {
        DB::rollBack();
        $this->message = 'Error: ' . $e->getMessage();
    }
}
    
    private function getItemUnitPrice($inventoryId)
    {
        $item = DB::table('purchase_order_items')
            ->where('po_id', $this->selectedOrder->po_id)
            ->where('inventory_id', $inventoryId)
            ->first();
            
        return $item->unit_price ?? 0;
    }
    
    private function updateSupplierRating()
    {
        // Get average rating from all receipts in this order
        $averageRating = collect($this->receipts)
            ->where('quantity_received', '>', 0)
            ->avg('supplier_rating');
        
        if ($averageRating) {
            // Get all previous ratings for this supplier
            $supplierRatings = DB::table('goods_receipts as gr')
                ->join('purchase_orders as po', 'gr.po_id', '=', 'po.po_id')
                ->where('po.supplier_id', $this->selectedOrder->supplier_id)
                ->where('gr.quality_status', 'passed')
                ->whereNotNull('gr.received_by')
                ->select(DB::raw('COUNT(DISTINCT gr.receipt_id) as total_receipts'))
                ->first();
            
            $totalReceipts = $supplierRatings->total_receipts;
            $currentRating = $this->selectedOrder->supplier_rating ?? 0;
            
            // Calculate weighted average
            $newReceiptsCount = count(array_filter($this->receipts, fn($r) => $r['quantity_received'] > 0 && $r['quality_status'] === 'passed'));
            
            if ($totalReceipts > 0) {
                $newRating = (($currentRating * $totalReceipts) + ($averageRating * $newReceiptsCount)) / ($totalReceipts + $newReceiptsCount);
            } else {
                $newRating = $averageRating;
            }
            
            DB::table('suppliers')
                ->where('supplier_id', $this->selectedOrder->supplier_id)
                ->update([
                    'rating' => round($newRating, 2),
                    'updated_at' => Carbon::now()
                ]);
        }
    }
    
    private function isOrderComplete()
    {
        foreach ($this->orderItems as $item) {
            $receivedQty = DB::table('goods_receipts')
                ->where('po_id', $this->selectedOrder->po_id)
                ->where('inventory_id', $item->inventory_id)
                ->where('quality_status', 'passed')
                ->sum('quantity_received');
            
            if ($receivedQty < $item->quantity) {
                return false;
            }
        }
        
        return true;
    }
    
    public function getStatusColor($status)
    {
        switch ($status) {
            case 'draft': return 'bg-emerald-100 text-emerald-800';
            case 'sent': return 'bg-amber-100 text-amber-800';
            case 'confirmed': return 'bg-teal-100 text-teal-800';
            case 'partially_received': return 'bg-orange-100 text-orange-800';
            case 'completed': return 'bg-green-100 text-green-800';
            case 'cancelled': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    }
    
    public function getStatusText($status)
    {
        switch ($status) {
            case 'draft': return 'Draft';
            case 'sent': return 'Sent to Supplier';
            case 'confirmed': return 'Confirmed by Supplier';
            case 'partially_received': return 'Delivery in Progress';
            case 'completed': return 'Completed';
            case 'cancelled': return 'Cancelled';
            default: return ucfirst($status);
        }
    }
    
    public function getQualityColor($status)
    {
        switch ($status) {
            case 'passed': return 'bg-green-100 text-green-800 border border-green-300';
            case 'failed': return 'bg-red-100 text-red-800 border border-red-300';
            case 'pending': return 'bg-yellow-100 text-yellow-800 border border-yellow-300';
            default: return 'bg-gray-100 text-gray-800 border border-gray-300';
        }
    }
    
    public function getRatingStars($rating)
    {
        $rating = $rating ?? 0;
        $stars = '';
        $fullStars = floor($rating);
        $hasHalfStar = ($rating - $fullStars) >= 0.5;
        
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $fullStars) {
                $stars .= '<i class="fas fa-star text-yellow-500"></i>';
            } elseif ($i == $fullStars + 1 && $hasHalfStar) {
                $stars .= '<i class="fas fa-star-half-alt text-yellow-500"></i>';
            } else {
                $stars .= '<i class="far fa-star text-gray-300"></i>';
            }
        }
        
        return $stars;
    }
}
?>

<div class="p-6 min-h-screen bg-gradient-to-br from-emerald-50 via-white to-green-50">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-emerald-900 mb-2">Warehouse Management</h1>
                <p class="text-emerald-700">Complete delivery orders and update inventory</p>
            </div>
        </div>
    </div>
    
    @if($message)
        <div class="mb-6 p-4 rounded-lg {{ str_contains($message, 'Error') ? 'bg-red-50 text-red-800 border-l-4 border-red-500' : 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-500' }}">
            <div class="flex items-center">
                @if(str_contains($message, 'Error'))
                    <i class="fas fa-exclamation-circle mr-3 text-red-500"></i>
                @else
                    <i class="fas fa-check-circle mr-3 text-emerald-500"></i>
                @endif
                <span>{{ $message }}</span>
            </div>
        </div>
    @endif
    
    <!-- Partially Received Orders Section -->
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden mb-8 border border-emerald-00 hover:shadow-2xl transition-shadow duration-300">
        <div class="bg-emerald-700 px-8 py-6 rounded-t-xl shadow-md">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <div class="bg-emerald-800/50 p-3 rounded-xl border border-emerald-600">
                    <i class="fas fa-truck-loading text-emerald-200 text-xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-white">
                        Partially Received Orders
                    </h2>
                    <p class="text-emerald-100 text-sm mt-1">
                        Orders awaiting final delivery completion
                    </p>
                </div>
            </div>
        </div>
        <div class="bg-white text-emerald-700 font-bold text-2xl px-5 py-3 rounded-xl shadow-lg">
            {{ count($pendingOrders) }}
        </div>
    </div>
</div>
        
        <div class="p-6">
            @if(count($pendingOrders) > 0)
                <div class="overflow-x-auto rounded-xl border border-emerald-100">
                    <table class="w-full text-sm">
                        <thead class="bg-gradient-to-r from-emerald-50 to-green-50">
                            <tr>
                                <th class="p-4 text-left text-emerald-900 font-semibold">PO Number</th>
                                <th class="p-4 text-left text-emerald-900 font-semibold">Supplier</th>
                                <th class="p-4 text-left text-emerald-900 font-semibold">Order Date</th>
                                <th class="p-4 text-left text-emerald-900 font-semibold">Expected Delivery</th>
                                <th class="p-4 text-left text-emerald-900 font-semibold">Total Amount</th>
                                <th class="p-4 text-left text-emerald-900 font-semibold">Rating</th>
                                <th class="p-4 text-left text-emerald-900 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-emerald-50">
                            @foreach($pendingOrders as $order)
                                <tr class="hover:bg-gradient-to-r hover:from-emerald-50/50 hover:to-green-50/50 transition-all duration-200">
                                    <td class="p-4">
                                        <span class="font-mono font-bold text-emerald-900 bg-emerald-100 px-3 py-1.5 rounded-lg border border-emerald-200">{{ $order->po_number }}</span>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-semibold text-emerald-900">{{ $order->supplier_name }}</div>
                                        <div class="flex items-center mt-2">
                                            <div class="flex">
                                                {!! $this->getRatingStars($order->supplier_rating) !!}
                                            </div>
                                            <span class="ml-2 text-xs font-medium bg-emerald-100 text-emerald-700 px-2 py-1 rounded">{{ number_format($order->supplier_rating, 1) }}/5</span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-emerald-800">
                                        <div class="flex items-center">
                                            <i class="fas fa-calendar-day mr-2 text-emerald-600"></i>
                                            {{ Carbon::parse($order->order_date)->format('M d, Y') }}
                                        </div>
                                    </td>
                                    <td class="p-4 text-emerald-800">
                                        @if($order->expected_delivery_date)
                                            <div class="flex items-center">
                                                <i class="fas fa-truck mr-2 text-emerald-600"></i>
                                                {{ Carbon::parse($order->expected_delivery_date)->format('M d, Y') }}
                                            </div>
                                        @else
                                            <span class="text-emerald-500 italic">Not set</span>
                                        @endif
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-green-700 bg-gradient-to-r from-green-50 to-emerald-50 px-3 py-2 rounded-lg border border-green-200">
                                            ₱{{ number_format($order->total_amount, 2) }}
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center justify-center">
                                            {!! $this->getRatingStars($order->supplier_rating) !!}
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <button wire:click="viewOrder({{ $order->po_id }})"
                                                class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-emerald-900 font-medium rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 flex items-center group">
                                            <i class="fas fa-clipboard-check mr-2 group-hover:scale-110 transition-transform"></i>
                                            Complete Delivery
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <div class="inline-block bg-gradient-to-r from-emerald-100 to-green-100 p-6 rounded-full mb-4">
                        <i class="fas fa-check-circle text-4xl text-emerald-600"></i>
                    </div>
                    <p class="text-emerald-800 font-medium text-lg">No partially received orders</p>
                    <p class="text-sm text-emerald-600 mt-1">All orders are either completed or not yet delivered</p>
                </div>
            @endif
        </div>
    </div>
    
    
    
<!-- Order Details Modal -->
@if($selectedOrder)
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fadeIn">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-7xl max-h-[90vh] overflow-hidden border border-emerald-300 animate-slideUp">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-emerald-600 via-green-600 to-teal-600 px-8 py-5">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-clipboard-check mr-3"></i>
                            Complete Delivery - PO #{{ $selectedOrder->po_number }}
                        </h3>
                        <p class="text-emerald-100 text-sm mt-2 opacity-90">
                            <span class="bg-emerald-700/50 px-3 py-1 rounded-full mr-2">
                                <i class="fas fa-user-tag mr-1"></i>{{ $selectedOrder->supplier_name }}
                            </span>
                            <span class="bg-emerald-700/50 px-3 py-1 rounded-full mr-2">
                                <i class="fas fa-calendar mr-1"></i>{{ Carbon::parse($selectedOrder->order_date)->format('M d, Y') }}
                            </span>
                        </p>
                    </div>
                    <button wire:click="closeOrderView" 
                            class="text-white hover:text-emerald-200 transition-colors bg-emerald-700/30 hover:bg-emerald-700/50 w-10 h-10 rounded-full flex items-center justify-center">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Modal Content -->
            <div class="p-6 overflow-y-auto max-h-[55vh]">
                
                <!-- Order Summary Cards -->
                <div class="mb-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-gradient-to-br from-emerald-50 to-white p-5 rounded-2xl border border-emerald-200 shadow-lg">
                        <h4 class="font-semibold text-emerald-900 mb-4 flex items-center">
                            <i class="fas fa-file-invoice-dollar mr-2 text-emerald-600"></i>
                            Order Information
                        </h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-emerald-700">PO Number:</span>
                                <span class="font-bold text-emerald-900 bg-emerald-100 px-3 py-1 rounded-lg">{{ $selectedOrder->po_number }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-emerald-700">Status:</span>
                                <span class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $this->getStatusColor($selectedOrder->status) }}">
                                    {{ $this->getStatusText($selectedOrder->status) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-emerald-700">Created By:</span>
                                <span class="font-medium text-emerald-900">{{ $selectedOrder->created_by_name }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-teal-50 to-white p-5 rounded-2xl border border-teal-200 shadow-lg">
                        <h4 class="font-semibold text-teal-900 mb-4 flex items-center">
                            <i class="fas fa-truck mr-2 text-teal-600"></i>
                            Supplier Details
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <span class="text-teal-700 block mb-1">Supplier:</span>
                                <span class="font-bold text-teal-900 text-lg">{{ $selectedOrder->supplier_name }}</span>
                            </div>
                            <div>
                                <span class="text-teal-700 block mb-1">Current Rating:</span>
                                <div class="flex items-center">
                                    <div class="flex">
                                        {!! $this->getRatingStars($selectedOrder->supplier_rating ?? 0) !!}
                                    </div>
                                    <span class="ml-3 font-bold text-teal-800 bg-teal-100 px-3 py-1 rounded-lg">
                                        {{ number_format($selectedOrder->supplier_rating ?? 0, 1) }}/5
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-green-50 to-white p-5 rounded-2xl border border-green-200 shadow-lg">
                        <h4 class="font-semibold text-green-900 mb-4 flex items-center">
                            <i class="fas fa-box mr-2 text-green-600"></i>
                            Delivery Summary
                        </h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-green-700">Total Amount:</span>
                                <span class="font-bold text-green-700 text-xl">₱{{ number_format($selectedOrder->total_amount, 2) }}</span>
                            </div>
                            @if($selectedOrder->expected_delivery_date)
                                <div class="flex justify-between items-center">
                                    <span class="text-green-700">Expected Delivery:</span>
                                    <span class="font-medium text-green-800 flex items-center">
                                        <i class="fas fa-clock mr-2"></i>
                                        {{ Carbon::parse($selectedOrder->expected_delivery_date)->format('M d, Y') }}
                                    </span>
                                </div>
                            @endif
                            @if($selectedOrder->terms)
                                <div class="mt-4 pt-4 border-t border-green-200">
                                    <span class="text-green-700 font-medium">Terms:</span>
                                    <p class="text-sm text-emerald-700 mt-2 bg-green-50 p-3 rounded-lg">{{ $selectedOrder->terms }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Receiving Form -->
                <div class="mb-8 bg-gradient-to-br from-emerald-50 to-white p-6 rounded-2xl border border-emerald-200 shadow-lg">
                    <div class="flex items-center justify-between mb-6">
                        <h4 class="font-semibold text-emerald-900 text-lg flex items-center">
                            <i class="fas fa-clipboard-list mr-3 text-emerald-600"></i>
                            Receive Remaining Items
                        </h4>
                        <span class="bg-emerald-600 text-white text-sm font-medium px-3 py-1.5 rounded-full">
                            {{ count($orderItems) }} items
                        </span>
                    </div>
                    
                    
                    <form wire:submit.prevent="completeOrder" id="receiptForm" class="space-y-6">
                        <div class="overflow-x-auto rounded-xl border border-emerald-100">
                            <table class="w-full text-sm">
                                <thead class="bg-gradient-to-r from-emerald-100 to-green-100">
                                    <tr>
                                        <th class="p-4 text-left text-emerald-900 font-semibold">Product</th>
                                        <th class="p-4 text-left text-emerald-900 font-semibold text-center">Ordered</th>
                                        <th class="p-4 text-left text-emerald-900 font-semibold text-center">Received</th>
                                        <th class="p-4 text-left text-emerald-900 font-semibold text-center">Remaining</th>
                                        <th class="p-4 text-left text-emerald-900 font-semibold text-center">Receiving Now</th>
                                        <th class="p-4 text-left text-emerald-900 font-semibold text-center">Quality</th>
                                        <th class="p-4 text-left text-emerald-900 font-semibold">Batch No</th>
                                        <th class="p-4 text-left text-emerald-900 font-semibold text-center">Rate Supplier</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-emerald-100">
                                    @foreach($receipts as $index => $receipt)
                                        <tr class="hover:bg-emerald-50/50 transition-colors">
                                            <td class="p-4">
                                                <div class="font-semibold text-emerald-900">{{ $receipt['product_name'] }}</div>
                                                <div class="text-xs text-emerald-600 font-mono mt-1">{{ $receipt['sku'] }}</div>
                                            </td>
                                            <td class="p-4 text-center">
                                                <span class="font-bold text-teal-700 bg-teal-50 px-3 py-1.5 rounded-lg inline-block min-w-16">
                                                    {{ $receipt['ordered_qty'] }}
                                                </span>
                                            </td>
                                            <td class="p-4 text-center">
                                                <span class="font-bold text-green-600 bg-green-50 px-3 py-1.5 rounded-lg inline-block min-w-16">
                                                    {{ $receipt['received_qty'] }}
                                                </span>
                                            </td>
                                            <td class="p-4 text-center">
                                                <span class="font-bold text-orange-600 bg-orange-50 px-3 py-1.5 rounded-lg inline-block min-w-16">
                                                    {{ $receipt['remaining_qty'] }}
                                                </span>
                                            </td>
                                            <td class="p-4">
                                                <div class="flex justify-center">
                                                    <input type="number" 
                                                           wire:model="receipts.{{ $index }}.quantity_received"
                                                           wire:change="$refresh"
                                                           class="w-24 px-3 py-2 border-2 border-emerald-300 rounded-xl text-center focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none transition-all"
                                                           min="0"
                                                           max="{{ $receipt['remaining_qty'] }}"
                                                           required>
                                                    @error('receipts.'.$index.'.quantity_received')
                                                        <span class="text-red-500 text-xs block mt-1">{{ $message }}</span>
                                                    @enderror
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <div class="flex justify-center">
                                                    <select wire:model="receipts.{{ $index }}.quality_status"
                                                            wire:change="$refresh"
                                                            class="px-3 py-2 border-2 border-emerald-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none transition-all">
                                                        <option value="passed" class="text-green-600">✅ Passed</option>
                                                        <option value="failed" class="text-red-600">❌ Failed</option>
                                                    </select>
                                                    @error('receipts.'.$index.'.quality_status')
                                                        <span class="text-red-500 text-xs block mt-1">{{ $message }}</span>
                                                    @enderror
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <input type="text" 
                                                       wire:model="receipts.{{ $index }}.batch_number"
                                                       class="w-full px-3 py-2 border-2 border-emerald-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none transition-all"
                                                       placeholder="Enter batch #">
                                            </td>
                                            <td class="p-4">
    <div class="flex flex-col items-center space-y-2">
        <!-- Star Rating with better visibility -->
        <div class="flex items-center justify-center space-x-1 mb-1">
            @for($i = 1; $i <= 5; $i++)
                <button type="button"
                        wire:click="$set('receipts.{{ $index }}.supplier_rating', {{ $i }})"
                        class="transform hover:scale-125 transition-transform duration-200 p-1 {{ $i <= ($receipt['supplier_rating'] ?? 5) ? 'text-yellow-500 bg-yellow-50 rounded-full' : 'text-gray-300 hover:text-yellow-400' }}">
                    @if($i <= ($receipt['supplier_rating'] ?? 5))
                        <i class="fas fa-star text-xl"></i>
                    @else
                        <i class="far fa-star text-xl"></i>
                    @endif
                </button>
            @endfor
        </div>
        
        <!-- Show current rating with badge -->
        <div class="text-xs font-medium px-2 py-1 rounded-full {{ ($receipt['supplier_rating'] ?? 5) >= 4 ? 'bg-green-100 text-green-800' : (($receipt['supplier_rating'] ?? 5) >= 3 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
            {{ $receipt['supplier_rating'] ?? 5 }} / 5
        </div>
        
        <!-- Optional: Fallback numeric input (hidden by default) -->
        <div x-data="{ showInput: false }" class="text-xs mt-1">
            <button type="button" 
                    @click="showInput = !showInput" 
                    class="text-emerald-600 hover:text-emerald-800 text-xs underline">
                Change rating
            </button>
            <div x-show="showInput" x-transition class="mt-1">
                <input type="number" 
                       wire:model="receipts.{{ $index }}.supplier_rating"
                       min="1" 
                       max="5" 
                       step="0.5"
                       class="w-16 px-2 py-1 border border-emerald-300 rounded text-center text-sm">
            </div>
        </div>
        
        <!-- Hidden input for validation -->
        <input type="hidden" 
               wire:model="receipts.{{ $index }}.supplier_rating"
               value="{{ $receipt['supplier_rating'] ?? 5 }}">
        
        @error('receipts.'.$index.'.supplier_rating')
            <span class="text-red-500 text-xs block mt-1 text-center">{{ $message }}</span>
        @enderror
    </div>
</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-6">
                            <label class="block text-sm font-semibold text-emerald-800 mb-3 flex items-center">
                                <i class="fas fa-sticky-note mr-2 text-emerald-600"></i>
                                General Notes for this Delivery
                            </label>
                            <textarea wire:model="receipts.0.notes"
                                      rows="3"
                                      class="w-full px-4 py-3 border-2 border-emerald-300 rounded-2xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:outline-none transition-all resize-none"
                                      placeholder="Enter any notes about this delivery..."></textarea>
                        </div>
                    </form>
                </div>
                
                <!-- Receipt History -->
                @if(count($receiptHistory) > 0)
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-semibold text-emerald-900 text-lg flex items-center">
                                <i class="fas fa-history mr-3 text-emerald-600"></i>
                                Previous Receipts for this PO
                            </h4>
                            <span class="bg-emerald-100 text-emerald-800 text-sm font-medium px-3 py-1.5 rounded-full">
                                {{ count($receiptHistory) }} records
                            </span>
                        </div>
                        
                        <div class="overflow-x-auto rounded-xl border border-emerald-100">
                            <table class="w-full text-sm">
                                <thead class="bg-gradient-to-r from-emerald-100 to-green-100">
                                    <tr>
                                        <th class="p-3 text-left text-emerald-900 font-semibold">Date</th>
                                        <th class="p-3 text-left text-emerald-900 font-semibold">Product</th>
                                        <th class="p-3 text-left text-emerald-900 font-semibold">Quantity</th>
                                        <th class="p-3 text-left text-emerald-900 font-semibold">Batch</th>
                                        <th class="p-3 text-left text-emerald-900 font-semibold">Quality</th>
                                        <th class="p-3 text-left text-emerald-900 font-semibold">Received By</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-emerald-50">
                                    @foreach($receiptHistory as $receipt)
                                        <tr class="hover:bg-emerald-50/50 transition-colors">
                                            <td class="p-3">
                                                <div class="flex items-center text-emerald-700">
                                                    <i class="fas fa-calendar-alt mr-2 text-emerald-600"></i>
                                                    {{ Carbon::parse($receipt->date_received)->format('M d, Y') }}
                                                </div>
                                            </td>
                                            <td class="p-3">
                                                <div class="font-medium text-emerald-900">{{ $receipt->product_name }}</div>
                                                <div class="text-xs text-emerald-600 font-mono">{{ $receipt->sku }}</div>
                                            </td>
                                            <td class="p-3 text-center">
                                                <span class="font-bold {{ $receipt->quality_status === 'passed' ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50' }} px-3 py-1.5 rounded-lg inline-block min-w-16">
                                                    {{ $receipt->quantity_received }}
                                                </span>
                                            </td>
                                            <td class="p-3">
                                                <span class="text-emerald-700 bg-emerald-50 px-3 py-1 rounded-lg border border-emerald-200">
                                                    {{ $receipt->batch_number ?: 'N/A' }}
                                                </span>
                                            </td>
                                            <td class="p-3">
                                                <span class="px-3 py-1.5 rounded-full text-xs font-semibold {{ $this->getQualityColor($receipt->quality_status) }}">
                                                    {{ ucfirst($receipt->quality_status) }}
                                                </span>
                                            </td>
                                            <td class="p-3">
                                                <div class="text-emerald-700 flex items-center">
                                                    <i class="fas fa-user-check mr-2 text-emerald-600"></i>
                                                    {{ $receipt->received_by_name ?: 'System' }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
                
                <!-- Modal Footer -->
                <div class="border-t border-emerald-200 px-8 py-6 bg-gradient-to-r from-emerald-50 to-green-50">
                    <div class="flex flex-col lg:flex-row justify-between items-center gap-4">
                        <div class="text-emerald-800 flex items-center">
                            <i class="fas fa-info-circle mr-2 text-emerald-600"></i>
                            <span class="font-medium">This will update inventory and supplier ratings</span>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
                            <button wire:click="closeOrderView"
                                    class="px-8 py-3.5 border-2 border-emerald-400 text-emerald-700 font-semibold rounded-xl hover:bg-emerald-50 transition-all duration-200 hover:border-emerald-500 hover:shadow-lg">
                                Cancel
                            </button>
                            
                           <button type="button"
        wire:click="completeOrder"
        onclick="return confirm('Are you sure you want to complete this delivery? This will update inventory stock and supplier ratings.')"
        class="px-8 py-3.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-2xl transition-all duration-200 flex items-center justify-center group border border-green-700">
    <i class="fas fa-check-circle mr-2 group-hover:rotate-12 transition-transform"></i>
    Complete Delivery & Update
</button>
         
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif</div>


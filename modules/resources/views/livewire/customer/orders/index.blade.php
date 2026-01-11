<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.customerapp')] class extends Component
{
    use WithPagination;
    
    public $statusFilter = 'all';
    public $search = '';
    public $sortField = 'order_date';
    public $sortDirection = 'desc';
    public $perPage = 10;
    
    public $selectedOrder = null;
    public $showOrderDetails = false;
    public $orderItems = [];
    
    // Return functionality properties
    public $returnOrderId = null;
    public $returnReason = '';
    public $returnNotes = '';
    
    // Debug property
    public $debugInfo = '';
    
    // Status options with colors
    public $statuses = [
        'draft' => ['label' => 'Draft', 'color' => 'bg-gray-100 text-gray-800'],
        'confirmed' => ['label' => 'Confirmed', 'color' => 'bg-blue-100 text-blue-800'],
        'processing' => ['label' => 'Processing', 'color' => 'bg-yellow-100 text-yellow-800'],
        'shipped' => ['label' => 'Shipped', 'color' => 'bg-purple-100 text-purple-800'],
        'delivered' => ['label' => 'Delivered', 'color' => 'bg-green-100 text-green-800'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'bg-red-100 text-red-800'],
        'return_requested' => ['label' => 'Return Requested', 'color' => 'bg-orange-100 text-orange-800'],
        'returned' => ['label' => 'Returned', 'color' => 'bg-red-100 text-red-800'],
    ];
    
    // Return reasons
    public $returnReasons = [
        'defective' => 'Defective/Damaged Product',
        'wrong_item' => 'Wrong Item Received',
        'not_as_described' => 'Not as Described',
        'size_issue' => 'Size Issue',
        'changed_mind' => 'Changed Mind',
        'other' => 'Other Reason'
    ];
    
    // Initialize orders as computed property using DB
    public function getOrdersProperty()
    {
        $userId = Auth::id();
        
        if (!$userId) {
            return collect();
        }
        
        // Get customer ID for the logged-in user
        $customer = DB::table('customers')
            ->where('user_id', $userId)
            ->first();
            
        if (!$customer) {
            return collect();
        }
        
        $customerId = $customer->customer_id;
        
        $query = DB::table('sales_orders')
            ->where('customer_id', $customerId)
            ->select(
                'order_id',
                'order_number',
                'order_date',
                'delivery_date',
                'total_amount',
                'tax_amount',
                'discount_amount',
                'grand_total',
                'status',
                'payment_status',
                'payment_method',
                'shipping_address'
            );
            
        // Apply filters
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }
        
        if ($this->search) {
            $query->where(function($q) {
                $q->where('order_number', 'like', '%' . $this->search . '%')
                  ->orWhereExists(function($subQuery) {
                      $subQuery->select(DB::raw(1))
                          ->from('order_items')
                          ->join('products', 'order_items.product_id', '=', 'products.product_id')
                          ->whereColumn('order_items.order_id', 'sales_orders.order_id')
                          ->where('products.product_name', 'like', '%' . $this->search . '%');
                  });
            });
        }
        
        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);
        
        // Get paginated results
        $orders = $query->paginate($this->perPage);
        
        // For each order, get item count and check if return requested
        foreach ($orders as $order) {
            $order->item_count = DB::table('order_items')
                ->where('order_id', $order->order_id)
                ->count();
        }
        
        return $orders;
    }
    
    public function viewOrderDetails($orderId)
    {
        $this->debugInfo = "viewOrderDetails called with orderId: " . $orderId;
        
        $userId = Auth::id();
        
        // Get customer ID
        $customer = DB::table('customers')
            ->where('user_id', $userId)
            ->first();
            
        if (!$customer) {
            $this->debugInfo .= " - Customer not found";
            return;
        }
        
        // Get order details
        $this->selectedOrder = DB::table('sales_orders')
            ->where('order_id', $orderId)
            ->where('customer_id', $customer->customer_id)
            ->first();
            
        if ($this->selectedOrder) {
            $this->debugInfo .= " - Order found: " . $this->selectedOrder->order_number . " Status: " . $this->selectedOrder->status;
            
            // Get order items with product details and custom options
            $this->orderItems = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.product_id')
                ->where('order_items.order_id', $orderId)
                ->select(
                    'order_items.*',
                    'products.product_name',
                    'products.images',
                    // Check if option columns exist in order_items
                    DB::raw('COALESCE(order_items.size, NULL) as size'),
                    DB::raw('COALESCE(order_items.flavor, NULL) as flavor'),
                    DB::raw('COALESCE(order_items.variety, NULL) as variety'),
                    DB::raw('COALESCE(order_items.base_price, order_items.price_per_unit) as base_price'),
                    DB::raw('COALESCE(order_items.price_adjustment_percent, 0) as price_adjustment_percent'),
                    DB::raw('COALESCE(order_items.final_price, order_items.price_per_unit) as final_price')
                )
                ->get();
                
            $this->showOrderDetails = true;
        } else {
            $this->debugInfo .= " - Order not found or doesn't belong to customer";
        }
    }
    
    public function closeOrderDetails()
    {
        $this->showOrderDetails = false;
        $this->selectedOrder = null;
        $this->orderItems = [];
    }
    
    public function cancelOrder($orderId)
    {
        $this->debugInfo = "cancelOrder called with orderId: " . $orderId;
        
        $userId = Auth::id();
        
        // Get customer ID
        $customer = DB::table('customers')
            ->where('user_id', $userId)
            ->first();
            
        if (!$customer) {
            session()->flash('error', 'Customer not found.');
            $this->debugInfo .= " - Customer not found";
            return;
        }
        
        // Check if order can be cancelled
        $order = DB::table('sales_orders')
            ->where('order_id', $orderId)
            ->where('customer_id', $customer->customer_id)
            ->whereIn('status', ['draft', 'confirmed'])
            ->first();
            
        if ($order) {
            $this->debugInfo .= " - Order found and can be cancelled. Current status: " . $order->status;
            
            DB::table('sales_orders')
                ->where('order_id', $orderId)
                ->update(['status' => 'cancelled', 'updated_at' => now()]);
                
            session()->flash('success', 'Order cancelled successfully.');
            $this->debugInfo .= " - Order cancelled successfully";
        } else {
            session()->flash('error', 'Order cannot be cancelled at this stage.');
            $this->debugInfo .= " - Order cannot be cancelled";
        }
    }
    
    // New method to prepare return request
    public function prepareReturnRequest($orderId)
    {
        $this->debugInfo = "prepareReturnRequest called with orderId: " . $orderId;
        
        $userId = Auth::id();
        
        // Get customer ID
        $customer = DB::table('customers')
            ->where('user_id', $userId)
            ->first();
            
        if (!$customer) {
            session()->flash('error', 'Customer not found.');
            $this->debugInfo .= " - Customer not found";
            return;
        }
        
        $this->debugInfo .= " - Customer ID: " . $customer->customer_id;
        
        // Check if order is eligible for return
        $order = DB::table('sales_orders')
            ->where('order_id', $orderId)
            ->where('customer_id', $customer->customer_id)
            ->first();
            
        if ($order) {
            $this->debugInfo .= " - Order found. Status: " . $order->status;
            
            if ($order->status !== 'delivered') {
                session()->flash('error', 'Order not found or not eligible for return. Only delivered orders can be returned. Current status: ' . $order->status);
                $this->debugInfo .= " - Order not delivered, status is: " . $order->status;
                return;
            }
        } else {
            session()->flash('error', 'Order not found.');
            $this->debugInfo .= " - Order not found";
            return;
        }
        
        // Check if return is already requested or processed
        $existingReturn = DB::table('sales_orders')
            ->where('order_id', $orderId)
            ->whereIn('status', ['return_requested', 'returned'])
            ->first();
            
        if ($existingReturn) {
            if ($existingReturn->status === 'return_requested') {
                session()->flash('error', 'Return already requested for this order. Please wait for processing.');
            } else {
                session()->flash('error', 'This order has already been returned.');
            }
            $this->debugInfo .= " - Return already exists with status: " . $existingReturn->status;
            return;
        }
        
        $this->returnOrderId = $orderId;
        $this->debugInfo .= " - Return prepared for order: " . $orderId;
        
        // Dispatch event to show modal via JavaScript
        $this->dispatch('showReturnModal', orderId: $orderId);
    }
    
    public function submitReturnRequest()
    {
        $this->debugInfo = "submitReturnRequest called for order: " . $this->returnOrderId;
        
        $this->validate([
            'returnReason' => 'required|in:' . implode(',', array_keys($this->returnReasons)),
            'returnNotes' => 'nullable|string|max:1000',
        ]);
        
        $this->debugInfo .= " - Validation passed";
        
        $userId = Auth::id();
        
        // Get customer ID
        $customer = DB::table('customers')
            ->where('user_id', $userId)
            ->first();
            
        if (!$customer) {
            session()->flash('error', 'Customer not found.');
            $this->debugInfo .= " - Customer not found";
            return;
        }
        
        $this->debugInfo .= " - Customer ID: " . $customer->customer_id;
        
        // Check if order is eligible for return
        $order = DB::table('sales_orders')
            ->where('order_id', $this->returnOrderId)
            ->where('customer_id', $customer->customer_id)
            ->first();
            
        if ($order) {
            $this->debugInfo .= " - Order found. Current status: " . $order->status;
            
            if ($order->status !== 'delivered') {
                session()->flash('error', 'Order not eligible for return. Current status: ' . $order->status);
                $this->debugInfo .= " - Order not delivered";
                return;
            }
        } else {
            session()->flash('error', 'Order not found.');
            $this->debugInfo .= " - Order not found";
            return;
        }
        
        // Check if return is already requested
        $hasReturnRequest = DB::table('sales_orders')
            ->where('order_id', $this->returnOrderId)
            ->whereIn('status', ['return_requested', 'returned'])
            ->exists();
            
        if ($hasReturnRequest) {
            session()->flash('error', 'Return already requested or processed for this order.');
            $this->debugInfo .= " - Return already requested";
            return;
        }
        
        try {
            // Update order status to return_requested
            $this->debugInfo .= " - Attempting to update order status...";
            
            $result = DB::table('sales_orders')
                ->where('order_id', $this->returnOrderId)
                ->update([
                    'status' => 'return_requested',
                    'updated_at' => now()
                ]);
            
            $this->debugInfo .= " - Update result: " . ($result ? "Success" : "Failed");
            
            if ($result) {
                session()->flash('success', 'Return request submitted successfully. We will contact you soon for further instructions.');
                $this->debugInfo .= " - Return request submitted successfully";
                
                // Hide modal via JavaScript
                $this->dispatch('hideReturnModal');
            } else {
                session()->flash('error', 'Failed to update order status.');
                $this->debugInfo .= " - Failed to update order status";
            }
            
            // Reset form
            $this->returnOrderId = null;
            $this->returnReason = '';
            $this->returnNotes = '';
            $this->closeOrderDetails();
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to submit return request. Error: ' . $e->getMessage());
            $this->debugInfo .= " - Exception: " . $e->getMessage();
        }
    }
    
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }
}
?>
<div>
    <div class="container mx-auto px-4 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">My Orders</h1>
            <p class="text-gray-600 mt-2">View and manage your order history</p>
        </div>

 
        <!-- Filters and Search -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <!-- Search -->
                <div class="w-full md:w-auto">
                    <div class="relative">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search orders or products..."
                            class="w-full md:w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                        >
                        <div class="absolute left-3 top-3 text-gray-400">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                </div>

                <!-- Status Filter -->
                <div class="flex flex-wrap gap-2">
                    <button
                        wire:click="$set('statusFilter', 'all')"
                        class="px-4 py-2 rounded-lg text-sm font-medium {{ $statusFilter === 'all' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                    >
                        All Orders
                    </button>
                    @foreach($statuses as $key => $status)
                        <button
                            wire:click="$set('statusFilter', '{{ $key }}')"
                            class="px-4 py-2 rounded-lg text-sm font-medium {{ $statusFilter === $key ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                        >
                            {{ $status['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            @if($this->orders->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="sortBy('order_number')">
                                    Order #
                                    @if($sortField === 'order_number')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1"></i>
                                    @else
                                        <i class="fas fa-sort ml-1 text-gray-300"></i>
                                    @endif
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="sortBy('order_date')">
                                    Date
                                    @if($sortField === 'order_date')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1"></i>
                                    @else
                                        <i class="fas fa-sort ml-1 text-gray-300"></i>
                                    @endif
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="sortBy('total_amount')">
                                    Total
                                    @if($sortField === 'total_amount')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1"></i>
                                    @else
                                        <i class="fas fa-sort ml-1 text-gray-300"></i>
                                    @endif
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($this->orders as $order)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $order->order_number }}</div>
                                        <div class="text-xs text-gray-500">ID: {{ $order->order_id }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            {{ $order->item_count ?? 0 }} item(s)
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            {{ \Carbon\Carbon::parse($order->order_date)->format('M d, Y') }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statuses[$order->status]['color'] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ $statuses[$order->status]['label'] ?? ucfirst($order->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            ₱{{ number_format($order->grand_total, 2) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button
                                                wire:click="viewOrderDetails({{ $order->order_id }})"
                                                class="text-green-600 hover:text-green-900"
                                                title="View Details"
                                            >
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            @if(in_array($order->status, ['draft', 'confirmed']))
                                                <button
                                                    wire:click="cancelOrder({{ $order->order_id }})"
                                                    class="text-red-600 hover:text-red-900"
                                                    title="Cancel Order"
                                                    onclick="return confirm('Are you sure you want to cancel this order?')"
                                                >
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            @endif
                                            
                                            @if($order->status === 'delivered')
                                                <!-- Return Button -->
                                                <button
                                                    onclick="showReturnModal({{ $order->order_id }})"
                                                    class="text-orange-600 hover:text-orange-900"
                                                    title="Return Order"
                                                >
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            @endif
                                            
                                            @if(in_array($order->order_id, ['return_requested', 'returned']))
                                                <span class="text-xs {{ $order->status === 'return_requested' ? 'text-orange-600' : 'text-red-600' }}">
                                                    {{ $order->status === 'return_requested' ? 'Return Requested' : 'Returned' }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $this->orders->links() }}
                </div>
            @else
                <!-- Empty State -->
                <div class="text-center py-12">
                    <div class="mx-auto w-24 h-24 text-gray-400 mb-4">
                        <i class="fas fa-box-open text-5xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No orders found</h3>
                    <p class="text-gray-500 mb-6">
                        @if($statusFilter !== 'all' || $search)
                            Try changing your filters or search terms
                        @else
                            You haven't placed any orders yet
                        @endif
                    </p>
                    @if(!$search && $statusFilter === 'all')
                        <a
                            href="{{ route('customer.products.index') }}"
                            class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700"
                        >
                            <i class="fas fa-shopping-cart mr-2"></i>
                            Start Shopping
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <!-- Order Details Modal -->
        @if($showOrderDetails && $selectedOrder)
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-40 p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                    <!-- Modal Header -->
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Order Details</h2>
                            <p class="text-sm text-gray-600">{{ $selectedOrder->order_number }}</p>
                            <p class="text-xs text-gray-500">ID: {{ $selectedOrder->order_id }}</p>
                        </div>
                        <button
                            wire:click="closeOrderDetails"
                            class="text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Modal Content -->
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-200px)]">
                        <!-- Order Summary -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Order Information</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Order Date:</span>
                                        <span class="font-medium">{{ \Carbon\Carbon::parse($selectedOrder->order_date)->format('F d, Y') }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Status:</span>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statuses[$selectedOrder->status]['color'] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ $statuses[$selectedOrder->status]['label'] ?? ucfirst($selectedOrder->status) }}
                                        </span>
                                    </div>
                                    @if($selectedOrder->delivery_date)
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Delivery Date:</span>
                                            <span class="font-medium">{{ \Carbon\Carbon::parse($selectedOrder->delivery_date)->format('F d, Y') }}</span>
                                        </div>
                                    @endif
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Payment Status:</span>
                                        <span class="font-medium capitalize">{{ $selectedOrder->payment_status }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Payment Method:</span>
                                        <span class="font-medium capitalize">{{ $selectedOrder->payment_method ?? 'N/A' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Shipping Address</h3>
                                <p class="text-gray-600 whitespace-pre-line">{{ $selectedOrder->shipping_address ?? 'No shipping address provided' }}</p>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Order Items</h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="space-y-4">
                                    @foreach($orderItems as $item)
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex items-start justify-between">
                                                <div class="flex items-start">
                                                    @if($item->images)
                                                        @php
                                                            $images = json_decode($item->images, true);
                                                            $image = $images[0] ?? null;
                                                        @endphp
                                                        @if($image)
                                                            <div class="w-20 h-20 bg-gray-200 rounded mr-4 flex-shrink-0">
                                                                <img src="{{ asset('storage/' . $image) }}" 
                                                                     class="w-full h-full object-cover rounded" 
                                                                     alt="{{ $item->product_name }}"
                                                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9IiNmMWY1ZjkiLz48dGV4dCB4PSI1MCUiIHk9IjUwJSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEyIiBmaWxsPSIjNzM3MzczIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+'">
                                                            </div>
                                                        @endif
                                                    @endif
                                                    <div>
                                                        <h4 class="font-medium text-gray-900">{{ $item->product_name }}</h4>
                                                        <p class="text-sm text-gray-600">Quantity: {{ $item->quantity }}</p>
                                                        
                                                        <!-- Display Size, Flavor, Variety -->
                                                        <div class="mt-2 flex flex-wrap gap-2">
                                                            @if($item->size)
                                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">
                                                                    <i class="fas fa-expand-alt text-xs"></i>
                                                                    Size: {{ $item->size }}
                                                                </span>
                                                            @endif
                                                            
                                                            @if($item->flavor)
                                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full font-medium">
                                                                    <i class="fas fa-utensils text-xs"></i>
                                                                    Flavor: {{ $item->flavor }}
                                                                </span>
                                                            @endif
                                                            
                                                            @if($item->variety)
                                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-100 text-indigo-800 text-xs rounded-full font-medium">
                                                                    <i class="fas fa-layer-group text-xs"></i>
                                                                    Variety: {{ $item->variety }}
                                                                </span>
                                                            @endif
                                                            
                                                            @if($item->price_adjustment_percent > 0)
                                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full font-medium">
                                                                    <i class="fas fa-percentage text-xs"></i>
                                                                    Price Adjustment: +{{ $item->price_adjustment_percent }}%
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <!-- Price breakdown -->
                                                    <div class="mb-2">
                                                        @if($item->base_price != $item->final_price)
                                                            <div class="flex items-center justify-end gap-2">
                                                                <span class="text-gray-500 line-through text-sm">
                                                                    ₱{{ number_format($item->base_price, 2) }} each
                                                                </span>
                                                                <span class="text-green-600 font-medium">
                                                                    ₱{{ number_format($item->final_price, 2) }} each
                                                                </span>
                                                            </div>
                                                        @else
                                                            <p class="text-green-600 font-medium">
                                                                ₱{{ number_format($item->final_price, 2) }} each
                                                            </p>
                                                        @endif
                                                    </div>
                                                    <p class="text-lg font-bold">₱{{ number_format($item->total, 2) }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Order Totals -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Order Summary</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-medium">₱{{ number_format($selectedOrder->total_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Tax:</span>
                                    <span class="font-medium">₱{{ number_format($selectedOrder->tax_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Discount:</span>
                                    <span class="font-medium text-green-600">-₱{{ number_format($selectedOrder->discount_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between border-t border-gray-300 pt-2 mt-2">
                                    <span class="text-lg font-bold text-gray-900">Grand Total:</span>
                                    <span class="text-lg font-bold text-gray-900">₱{{ number_format($selectedOrder->grand_total, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-4">
                        <button
                            wire:click="closeOrderDetails"
                            class="px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50"
                        >
                            Close
                        </button>
                        
                        @if($selectedOrder->status === 'delivered')
                            <button
                                onclick="showReturnModal({{ $selectedOrder->order_id }})"
                                class="px-4 py-2 bg-orange-600 text-white font-medium rounded-lg hover:bg-orange-700"
                            >
                                <i class="fas fa-undo mr-2"></i>
                                Request Return
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Return Request Modal (Hidden by default) -->
        <div id="returnModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Request Return</h2>
                        <p id="modalOrderNumber" class="text-sm text-gray-600">Order #</p>
                    </div>
                    <button
                        onclick="hideReturnModal()"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Modal Content -->
                <div class="p-6">
                    <form id="returnForm" onsubmit="submitReturnForm(event)">
                        <input type="hidden" id="modalOrderId" name="orderId">
                        
                        <!-- Return Reason -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Reason for Return <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="returnReasonSelect"
                                name="returnReason"
                                required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            >
                                <option value="">Select a reason</option>
                                @foreach($returnReasons as $key => $reason)
                                    <option value="{{ $key }}">{{ $reason }}</option>
                                @endforeach
                            </select>
                            <p id="reasonError" class="text-red-500 text-sm mt-1 hidden"></p>
                        </div>

                        <!-- Additional Notes -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Additional Notes (Optional)
                            </label>
                            <textarea
                                id="returnNotesText"
                                name="returnNotes"
                                rows="3"
                                placeholder="Please provide any additional details about your return..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            ></textarea>
                            <p id="notesError" class="text-red-500 text-sm mt-1 hidden"></p>
                        </div>

                        <!-- Return Policy Note -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                                <div>
                                    <h4 class="font-medium text-blue-800 mb-1">Return Policy</h4>
                                    <ul class="text-sm text-blue-700 list-disc list-inside space-y-1">
                                        <li>Returns are accepted within 30 days of delivery</li>
                                        <li>Items must be in original condition and packaging</li>
                                        <li>Refunds will be processed within 5-10 business days</li>
                                        <li>Shipping costs for returns may apply</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
                            <button
                                type="button"
                                onclick="hideReturnModal()"
                                class="px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-4 py-2 bg-orange-600 text-white font-medium rounded-lg hover:bg-orange-700"
                            >
                                <i class="fas fa-paper-plane mr-2"></i>
                                Submit Return Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        @if(session()->has('success'))
            <div class="fixed bottom-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-lg" id="flash-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session()->has('error'))
            <div class="fixed bottom-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg shadow-lg" id="flash-error">
                {{ session('error') }}
            </div>
        @endif
    </div>

    <!-- Simple JavaScript for Modal -->
    <script>
        // Show return modal
        function showReturnModal(orderId) {
            // First check with Livewire if order is eligible
            @this.call('prepareReturnRequest', orderId)
                .then(result => {
                    // If successful, show the modal
                    const modal = document.getElementById('returnModal');
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    
                    // Set order ID in hidden input
                    document.getElementById('modalOrderId').value = orderId;
                    document.getElementById('modalOrderNumber').textContent = 'Order #' + orderId;
                    
                    // Reset form
                    document.getElementById('returnReasonSelect').value = '';
                    document.getElementById('returnNotesText').value = '';
                    
                    // Clear errors
                    document.getElementById('reasonError').classList.add('hidden');
                    document.getElementById('notesError').classList.add('hidden');
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Livewire will show error via flash message
                });
        }

        // Hide return modal
        function hideReturnModal() {
            const modal = document.getElementById('returnModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Submit return form
        function submitReturnForm(event) {
            event.preventDefault();
            
            // Get form data
            const orderId = document.getElementById('modalOrderId').value;
            const returnReason = document.getElementById('returnReasonSelect').value;
            const returnNotes = document.getElementById('returnNotesText').value;
            
            // Validate
            if (!returnReason) {
                document.getElementById('reasonError').textContent = 'Please select a reason for return';
                document.getElementById('reasonError').classList.remove('hidden');
                return;
            }
            
            // Clear errors
            document.getElementById('reasonError').classList.add('hidden');
            document.getElementById('notesError').classList.add('hidden');
            
            // Set Livewire properties and submit
            @this.set('returnOrderId', orderId, true);
            @this.set('returnReason', returnReason, true);
            @this.set('returnNotes', returnNotes, true);
            
            // Call Livewire submit method
            @this.call('submitReturnRequest')
                .then(result => {
                    // Close modal on success
                    hideReturnModal();
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        // Listen for Livewire events to show/hide modal
        document.addEventListener('livewire:initialized', () => {
            @this.on('showReturnModal', (event) => {
                const modal = document.getElementById('returnModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                
                document.getElementById('modalOrderId').value = event.orderId;
                document.getElementById('modalOrderNumber').textContent = 'Order #' + event.orderId;
                
                // Reset form
                document.getElementById('returnReasonSelect').value = '';
                document.getElementById('returnNotesText').value = '';
            });
            
            @this.on('hideReturnModal', () => {
                hideReturnModal();
            });
        });

        // Auto-hide flash messages
        setTimeout(() => {
            const successMsg = document.getElementById('flash-success');
            const errorMsg = document.getElementById('flash-error');
            
            if (successMsg) successMsg.remove();
            if (errorMsg) errorMsg.remove();
        }, 5000);

        // Close modal when clicking outside
        document.getElementById('returnModal')?.addEventListener('click', function(event) {
            if (event.target === this) {
                hideReturnModal();
            }
        });
    </script>
</div>
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
    
    // Status options with colors
    public $statuses = [
        'draft' => ['label' => 'Draft', 'color' => 'bg-gray-100 text-gray-800'],
        'confirmed' => ['label' => 'Confirmed', 'color' => 'bg-blue-100 text-blue-800'],
        'processing' => ['label' => 'Processing', 'color' => 'bg-yellow-100 text-yellow-800'],
        'shipped' => ['label' => 'Shipped', 'color' => 'bg-purple-100 text-purple-800'],
        'delivered' => ['label' => 'Delivered', 'color' => 'bg-green-100 text-green-800'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'bg-red-100 text-red-800'],
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
        
        // For each order, get item count
        foreach ($orders as $order) {
            $order->item_count = DB::table('order_items')
                ->where('order_id', $order->order_id)
                ->count();
        }
        
        return $orders;
    }
    
    public function viewOrderDetails($orderId)
    {
        $userId = Auth::id();
        
        // Get customer ID
        $customer = DB::table('customers')
            ->where('user_id', $userId)
            ->first();
            
        if (!$customer) {
            return;
        }
        
        // Get order details
        $this->selectedOrder = DB::table('sales_orders')
            ->where('order_id', $orderId)
            ->where('customer_id', $customer->customer_id)
            ->first();
            
        if ($this->selectedOrder) {
            // Get order items with product details
            $this->orderItems = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.product_id')
                ->where('order_items.order_id', $orderId)
                ->select(
                    'order_items.*',
                    'products.product_name',
                    'products.images'
                )
                ->get();
                
            $this->showOrderDetails = true;
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
        $userId = Auth::id();
        
        // Get customer ID
        $customer = DB::table('customers')
            ->where('user_id', $userId)
            ->first();
            
        if (!$customer) {
            session()->flash('error', 'Customer not found.');
            return;
        }
        
        // Check if order can be cancelled
        $order = DB::table('sales_orders')
            ->where('order_id', $orderId)
            ->where('customer_id', $customer->customer_id)
            ->whereIn('status', ['draft', 'confirmed'])
            ->first();
            
        if ($order) {
            DB::table('sales_orders')
                ->where('order_id', $orderId)
                ->update(['status' => 'cancelled', 'updated_at' => now()]);
                
            session()->flash('success', 'Order cancelled successfully.');
        } else {
            session()->flash('error', 'Order cannot be cancelled at this stage.');
        }
    }
    
    public function reorder($orderId)
    {
        $userId = Auth::id();
        
        // Get customer ID
        $customer = DB::table('customers')
            ->where('user_id', $userId)
            ->first();
            
        if (!$customer) {
            session()->flash('error', 'Customer not found.');
            return;
        }
        
        // Get order items
        $items = DB::table('order_items')
            ->where('order_id', $orderId)
            ->get();
            
        if ($items->isEmpty()) {
            session()->flash('error', 'No items found in this order.');
            return;
        }
        
        $cart = session()->get('cart', []);
        
        foreach ($items as $item) {
            $productId = $item->product_id;
            if (isset($cart[$productId])) {
                $cart[$productId] += $item->quantity;
            } else {
                $cart[$productId] = $item->quantity;
            }
        }
        
        session()->put('cart', $cart);
        session()->flash('success', 'Items added to cart successfully.');
        
        return redirect()->route('customer.cart.index');
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
                                            ${{ number_format($order->grand_total, 2) }}
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
                                            
                                            @if(in_array($order->status, ['delivered']))
                                                <button
                                                    wire:click="reorder({{ $order->order_id }})"
                                                    class="text-blue-600 hover:text-blue-900"
                                                    title="Reorder"
                                                >
                                                    <i class="fas fa-redo"></i>
                                                </button>
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
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4" x-data="{ show: @entangle('showOrderDetails') }" x-show="show" @click.away="show = false">
                <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                    <!-- Modal Header -->
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Order Details</h2>
                            <p class="text-sm text-gray-600">{{ $selectedOrder->order_number }}</p>
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
                                        <div class="flex items-center justify-between py-3 border-b border-gray-200 last:border-b-0">
                                            <div class="flex items-center">
                                                @if($item->images)
                                                    @php
                                                        $images = json_decode($item->images, true);
                                                        $image = $images[0] ?? null;
                                                    @endphp
                                                    @if($image)
                                                        <div class="w-16 h-16 bg-gray-200 rounded mr-4">
                                                            <img src="{{ asset('storage/' . $image) }}" 
                                                                 class="w-full h-full object-cover rounded" 
                                                                 alt="{{ $item->product_name }}">
                                                        </div>
                                                    @endif
                                                @endif
                                                <div>
                                                    <h4 class="font-medium text-gray-900">{{ $item->product_name }}</h4>
                                                    <p class="text-sm text-gray-600">Quantity: {{ $item->quantity }}</p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <p class="font-medium">${{ number_format($item->price_per_unit, 2) }} each</p>
                                                <p class="text-lg font-bold">${{ number_format($item->total, 2) }}</p>
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
                                    <span class="font-medium">${{ number_format($selectedOrder->total_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Tax:</span>
                                    <span class="font-medium">${{ number_format($selectedOrder->tax_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Discount:</span>
                                    <span class="font-medium text-green-600">-${{ number_format($selectedOrder->discount_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between border-t border-gray-300 pt-2 mt-2">
                                    <span class="text-lg font-bold text-gray-900">Grand Total:</span>
                                    <span class="text-lg font-bold text-gray-900">${{ number_format($selectedOrder->grand_total, 2) }}</span>
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
                        @if(in_array($selectedOrder->status, ['delivered']))
                            <button
                                wire:click="reorder({{ $selectedOrder->order_id }})"
                                class="px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700"
                            >
                                <i class="fas fa-redo mr-2"></i>
                                Reorder
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Flash Messages -->
        @if(session()->has('success'))
            <div class="fixed bottom-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-lg">
                {{ session('success') }}
            </div>
        @endif

        @if(session()->has('error'))
            <div class="fixed bottom-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg shadow-lg">
                {{ session('error') }}
            </div>
        @endif
    </div>

    @script
    <script>
        // Auto-hide flash messages after 5 seconds
        setTimeout(() => {
            const flashMessages = document.querySelectorAll('[x-data]');
            flashMessages.forEach(msg => {
                if (msg.textContent.includes('success') || msg.textContent.includes('error')) {
                    msg.remove();
                }
            });
        }, 5000);
    </script>
    @endscript
</div>
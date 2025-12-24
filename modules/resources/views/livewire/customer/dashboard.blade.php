<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.customerapp')] class extends Component
{
    public array $orderSummary = [];
    public array $recentOrders = [];
    public array $spendingOverTime = [];
    public array $filteredOrders = [];
    public string $filterType = 'all';
    public ?array $customer = null;
    
    // Modal properties
    public bool $showOrderDetailsModal = false;
    public ?int $selectedOrderId = null;
    public array $selectedOrderItems = [];
    public ?object $selectedOrder = null;

    // Stats
    public int $cartItems = 0;
    public int $wishlistItems = 0;
    public int $openTickets = 0;
    public float $totalSpent = 0;

    public function mount()
    {
        $user = Auth::user();
        
        // Get customer by user_id instead of email
        $customer = DB::table('customers')->where('user_id', $user->id)->first();
        if (!$customer) {
            // Fallback to email if no user_id match
            $customer = DB::table('customers')->where('email', $user->email)->first();
        }
        
        if (!$customer) {
            $this->customer = null;
            return;
        }

        $this->customer = (array) $customer;
        $customerId = $customer->customer_id;

        // Order Summary
        $summary = DB::table('sales_orders')
            ->selectRaw("
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status IN ('pending', 'confirmed') THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status IN ('processing', 'shipped') THEN 1 ELSE 0 END) as processing_orders,
                SUM(grand_total) as total_spent
            ")
            ->where('customer_id', $customerId)
            ->first();

        $this->orderSummary = $summary ? (array) $summary : [];
        $this->totalSpent = $summary->total_spent ?? 0;

        // Recent 5 Orders
        $this->recentOrders = DB::table('sales_orders')
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->toArray();

        // Spending Over Time (Last 30 days)
        $this->spendingOverTime = DB::table('sales_orders')
            ->selectRaw('DATE(order_date) as order_date, SUM(grand_total) as daily_total')
            ->where('customer_id', $customerId)
            ->where('order_date', '>=', now()->subDays(30))
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->get()
            ->toArray();

        // Cart items count (from session or database)
        // For now, using a placeholder
        $this->cartItems = DB::table('order_items')
            ->join('sales_orders', 'order_items.order_id', '=', 'sales_orders.order_id')
            ->where('sales_orders.customer_id', $customerId)
            ->where('sales_orders.status', 'draft')
            ->count();

        // Wishlist items (placeholder)
        $this->wishlistItems = DB::table('products')
            ->join('product_category_pivot as w', 'products.product_id', '=', 'w.product_id')
            ->where('w.category_id', 1) // Assuming 1 is wishlist category
            ->count();

        // Open tickets
        $this->openTickets = DB::table('tickets')
            ->where('customer_id', $customerId)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        // Default filter
        $this->filterOrders('all');
    }

    public function filterOrders($type)
    {
        $this->filterType = $type;

        $query = DB::table('sales_orders')
            ->where('customer_id', $this->customer['customer_id']);

        if ($type === 'completed') {
            $query->where('status', 'delivered');
        } elseif ($type === 'pending') {
            $query->whereIn('status', ['draft', 'confirmed']);
        } elseif ($type === 'processing') {
            $query->whereIn('status', ['processing', 'shipped']);
        }

        $this->filteredOrders = $query
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function viewOrder(int $orderId)
    {
        $this->selectedOrderId = $orderId;
        $this->selectedOrder = DB::table('sales_orders')->where('order_id', $orderId)->first();

        // Load order items with product info
        $this->selectedOrderItems = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.product_id')
            ->where('order_items.order_id', $orderId)
            ->select(
                'products.product_name',
                'products.slug',
                'products.price as product_price',
                'order_items.quantity',
                'order_items.price_per_unit',
                'order_items.subtotal'
            )
            ->get()
            ->toArray();

        $this->showOrderDetailsModal = true;
    }

    public function closeOrderDetails()
    {
        $this->showOrderDetailsModal = false;
        $this->selectedOrderItems = [];
        $this->selectedOrderId = null;
        $this->selectedOrder = null;
    }

    public function returnOrder(int $orderId)
    {
        $order = DB::table('sales_orders')->where('order_id', $orderId)->first();
        if (!$order || $order->status !== 'delivered') {
            return;
        }

        // Update status
        DB::table('sales_orders')->where('order_id', $orderId)->update([
            'status' => 'cancelled',
            'updated_at' => now()
        ]);

        // Show success message
        session()->flash('message', 'Return request submitted successfully!');
        
        $this->filterOrders($this->filterType);
        $this->showOrderDetailsModal = false;
    }
};
?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-50">
    <!-- Quick Stats Bar -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Orders -->
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-200">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-blue-100">
                    <span class="text-blue-600 text-xl">📦</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-600">Total Orders</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $orderSummary['total_orders'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        <!-- Total Spent -->
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-200">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-green-100">
                    <span class="text-green-600 text-xl">💰</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-600">Total Spent</p>
                    <p class="text-2xl font-bold text-gray-900">₱{{ number_format($totalSpent, 2) }}</p>
                </div>
            </div>
        </div>

        <!-- Cart Items -->
        <a href="{{ route('customer.cart.index') }}" class="bg-white rounded-xl p-4 shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-purple-100">
                    <span class="text-purple-600 text-xl">🛒</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-600">Cart Items</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $cartItems }}</p>
                </div>
            </div>
        </a>

        <!-- Open Tickets -->
        <a href="{{ route('customer.support.tickets') }}" class="bg-white rounded-xl p-4 shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
            <div class="flex items-center">
                <div class="p-3 rounded-lg bg-orange-100">
                    <span class="text-orange-600 text-xl">🎫</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-600">Open Tickets</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $openTickets }}</p>
                </div>
            </div>
        </a>
    </div>

    <!-- Welcome Section -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    Welcome back, {{ $customer['first_name'] ?? 'Customer' }}!
                </h1>
                <p class="text-gray-600">
                    Here's what's happening with your orders and account.
                </p>
            </div>
            <a href="{{ route('customer.products.index') }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                🛍️ Shop Now
            </a>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Order Summary -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Order Status Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div wire:click="filterOrders('all')"
                     class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-5 rounded-xl cursor-pointer hover:shadow-lg transition-shadow">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm opacity-90">All Orders</p>
                            <p class="text-2xl font-bold mt-1">{{ $orderSummary['total_orders'] ?? 0 }}</p>
                        </div>
                        <span class="text-2xl">📋</span>
                    </div>
                </div>

                <div wire:click="filterOrders('processing')"
                     class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white p-5 rounded-xl cursor-pointer hover:shadow-lg transition-shadow">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm opacity-90">Processing</p>
                            <p class="text-2xl font-bold mt-1">{{ $orderSummary['processing_orders'] ?? 0 }}</p>
                        </div>
                        <span class="text-2xl">🔄</span>
                    </div>
                </div>

                <div wire:click="filterOrders('completed')"
                     class="bg-gradient-to-r from-green-500 to-green-600 text-white p-5 rounded-xl cursor-pointer hover:shadow-lg transition-shadow">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm opacity-90">Completed</p>
                            <p class="text-2xl font-bold mt-1">{{ $orderSummary['completed_orders'] ?? 0 }}</p>
                        </div>
                        <span class="text-2xl">✅</span>
                    </div>
                </div>
            </div>

            <!-- Order History Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-5 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-800">Recent Orders</h2>
                        <div class="flex space-x-2">
                            <button wire:click="filterOrders('all')" 
                                    class="px-3 py-1 text-sm rounded-lg {{ $filterType === 'all' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
                                All
                            </button>
                            <button wire:click="filterOrders('processing')"
                                    class="px-3 py-1 text-sm rounded-lg {{ $filterType === 'processing' ? 'bg-yellow-100 text-yellow-700' : 'text-gray-600 hover:bg-gray-100' }}">
                                Processing
                            </button>
                            <button wire:click="filterOrders('completed')"
                                    class="px-3 py-1 text-sm rounded-lg {{ $filterType === 'completed' ? 'bg-green-100 text-green-700' : 'text-gray-600 hover:bg-gray-100' }}">
                                Completed
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($filteredOrders as $order)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            #ORD-{{ str_pad($order->order_id, 6, '0', STR_PAD_LEFT) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            {{ date('M d, Y', strtotime($order->order_date)) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                            $statusColors = [
                                                'draft' => 'bg-gray-100 text-gray-800',
                                                'confirmed' => 'bg-blue-100 text-blue-800',
                                                'processing' => 'bg-yellow-100 text-yellow-800',
                                                'shipped' => 'bg-purple-100 text-purple-800',
                                                'delivered' => 'bg-green-100 text-green-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                            ];
                                            $colorClass = $statusColors[$order->status] ?? 'bg-gray-100 text-gray-800';
                                        @endphp
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $colorClass }}">
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        ₱{{ number_format($order->grand_total, 2) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <button wire:click="viewOrder({{ $order->order_id }})"
                                                class="text-blue-600 hover:text-blue-900 font-medium">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        <div class="flex flex-col items-center">
                                            <span class="text-3xl mb-2">📦</span>
                                            <p>No orders found</p>
                                            <a href="{{ route('customer.products.index') }}" class="mt-2 text-blue-600 hover:text-blue-800">
                                                Start shopping →
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column: Quick Actions & Info -->
        <div class="space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="font-bold text-gray-800 mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="{{ route('customer.products.index') }}" 
                       class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <span class="text-xl mr-3">🛍️</span>
                        <span class="flex-1">Browse Products</span>
                        <span class="text-gray-400">→</span>
                    </a>
                    
                    <a href="{{ route('customer.support.ticket-create') }}" 
                       class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <span class="text-xl mr-3">🎫</span>
                        <span class="flex-1">Create Support Ticket</span>
                        <span class="text-gray-400">→</span>
                    </a>
                    
                    <a href="{{ route('customer.account.profile') }}" 
                       class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <span class="text-xl mr-3">👤</span>
                        <span class="flex-1">Update Profile</span>
                        <span class="text-gray-400">→</span>
                    </a>
                    
                    <a href="{{ route('customer.wishlist.index') }}" 
                       class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <span class="text-xl mr-3">❤️</span>
                        <span class="flex-1">View Wishlist ({{ $wishlistItems }})</span>
                        <span class="text-gray-400">→</span>
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="font-bold text-gray-800 mb-4">Recent Activity</h3>
                <div class="space-y-4">
                    @foreach(array_slice($recentOrders, 0, 3) as $order)
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <span class="text-blue-600 text-sm">📦</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-gray-900">
                                    Order #ORD-{{ str_pad($order->order_id, 6, '0', STR_PAD_LEFT) }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ date('M d', strtotime($order->order_date)) }} • 
                                    <span class="font-medium">₱{{ number_format($order->grand_total, 2) }}</span>
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Customer Info -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl p-5">
                <h3 class="font-bold mb-4">Your Account</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="opacity-90">Customer Since:</span>
                        <span class="font-medium">{{ date('M Y', strtotime($customer['date_registered'] ?? now())) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="opacity-90">Orders:</span>
                        <span class="font-medium">{{ $orderSummary['total_orders'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="opacity-90">Total Spent:</span>
                        <span class="font-medium">₱{{ number_format($totalSpent, 2) }}</span>
                    </div>
                </div>
                <a href="{{ route('customer.account.index') }}" 
                   class="mt-4 inline-block w-full text-center bg-white text-blue-700 hover:bg-blue-50 py-2 rounded-lg font-medium transition-colors">
                    Manage Account
                </a>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    @if($showOrderDetailsModal && $selectedOrder)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Order Details</h2>
                        <p class="text-sm text-gray-600">
                            #ORD-{{ str_pad($selectedOrder->order_id, 6, '0', STR_PAD_LEFT) }} • 
                            {{ date('F d, Y', strtotime($selectedOrder->order_date)) }}
                        </p>
                    </div>
                    <button wire:click="closeOrderDetails" class="text-gray-400 hover:text-gray-600 text-2xl">
                        &times;
                    </button>
                </div>

                <!-- Modal Content -->
                <div class="overflow-y-auto max-h-[calc(90vh-120px)]">
                    <div class="p-6">
                        <!-- Order Summary -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <h4 class="font-medium text-gray-700 mb-2">Shipping Address</h4>
                                <p class="text-gray-900">{{ $selectedOrder->shipping_address ?? 'Not specified' }}</p>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-700 mb-2">Order Status</h4>
                                @php
                                    $statusColors = [
                                        'draft' => 'bg-gray-100 text-gray-800',
                                        'confirmed' => 'bg-blue-100 text-blue-800',
                                        'processing' => 'bg-yellow-100 text-yellow-800',
                                        'shipped' => 'bg-purple-100 text-purple-800',
                                        'delivered' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                    ];
                                    $colorClass = $statusColors[$selectedOrder->status] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="px-4 py-2 inline-flex text-sm leading-5 font-semibold rounded-full {{ $colorClass }}">
                                    {{ ucfirst($selectedOrder->status) }}
                                </span>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <h4 class="font-medium text-gray-700 mb-4">Order Items</h4>
                        <div class="border rounded-lg overflow-hidden">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Product</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Price</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Qty</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($selectedOrderItems as $item)
                                        <tr>
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-gray-900">{{ $item->product_name }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-700">₱{{ number_format($item->price_per_unit, 2) }}</td>
                                            <td class="px-4 py-3 text-gray-700">{{ $item->quantity }}</td>
                                            <td class="px-4 py-3 font-medium text-gray-900">₱{{ number_format($item->subtotal, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Order Totals -->
                        <div class="mt-6 border-t pt-6">
                            <div class="max-w-md ml-auto space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-medium">₱{{ number_format($selectedOrder->total_amount, 2) }}</span>
                                </div>
                                @if($selectedOrder->discount_amount > 0)
                                <div class="flex justify-between text-green-600">
                                    <span>Discount:</span>
                                    <span>-₱{{ number_format($selectedOrder->discount_amount, 2) }}</span>
                                </div>
                                @endif
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Tax:</span>
                                    <span class="font-medium">₱{{ number_format($selectedOrder->tax_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between text-lg font-bold pt-2 border-t">
                                    <span>Grand Total:</span>
                                    <span>₱{{ number_format($selectedOrder->grand_total, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-6 flex justify-end space-x-4">
                            @if($selectedOrder->status === 'delivered')
                                <button wire:click="returnOrder({{ $selectedOrderId }})"
                                        class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors">
                                    Request Return
                                </button>
                            @endif
                            <a href="{{ route('customer.support.ticket-create') }}" 
                               class="px-5 py-2 border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium rounded-lg transition-colors">
                                Contact Support
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.ecommerce')] class extends Component
{
    public array $orders = [];
    public array $activeOrders = [];
    public bool $showCompleted = false;
    
    public ?int $selectedOrderId = null;
    public array $selectedOrder = [];
    public string $orderStatus = 'draft';
    
    // Map UI statuses to database statuses
    public array $statusOptions = [
        'draft' => 'Draft',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled'
    ];
    
    // Status flow for UI (what status comes next)
    public array $statusFlow = [
        'draft' => 'confirmed',
        'confirmed' => 'processing',
        'processing' => 'shipped',
        'shipped' => 'delivered',
        'delivered' => null, // Final status
        'cancelled' => null  // Final status
    ];

    public function mount()
    {
        $this->loadOrders();
    }

    public function loadOrders()
    {
        // First, get aggregated order items
        $orderItems = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.product_id')
            ->select(
                'order_items.order_id',
                DB::raw('GROUP_CONCAT(CONCAT(order_items.quantity, "x ", products.product_name) SEPARATOR ", ") as items'),
                DB::raw('SUM(order_items.quantity) as total_quantity')
            )
            ->groupBy('order_items.order_id');

        // Main query with left join subquery
        $this->orders = DB::table('sales_orders')
            ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.customer_id')
            ->leftJoinSub($orderItems, 'order_items_agg', function ($join) {
                $join->on('sales_orders.order_id', '=', 'order_items_agg.order_id');
            })
            ->select(
                'sales_orders.order_id',
                'sales_orders.order_number',
                'sales_orders.status',
                'sales_orders.order_date',
                'sales_orders.grand_total',
                'sales_orders.payment_status',
                'sales_orders.payment_method',
                DB::raw('CONCAT(customers.first_name, " ", customers.last_name) as customer_name'),
                'order_items_agg.items',
                'order_items_agg.total_quantity',
                'sales_orders.created_at'
            )
            ->orderByDesc('sales_orders.created_at')
            ->get()
            ->map(fn($o) => (array)$o)
            ->toArray();
        
        // Filter active orders (not delivered or cancelled)
        $this->activeOrders = array_filter($this->orders, fn($order) => 
            !in_array($order['status'] ?? '', ['delivered', 'cancelled'])
        );
    }

    public function viewOrder(int $orderId)
    {
        $this->selectedOrderId = $orderId;
        $this->selectedOrder = collect($this->orders)->first(fn($o) => $o['order_id'] == $orderId) ?? [];
        $this->orderStatus = $this->selectedOrder['status'] ?? 'draft';
    }

    public function closeOrderView()
    {
        $this->selectedOrderId = null;
        $this->selectedOrder = [];
        $this->orderStatus = 'draft';
    }

    public function updateOrderStatus(string $newStatus)
    {
        if (!$this->selectedOrderId) return;

        DB::table('sales_orders')
            ->where('order_id', $this->selectedOrderId)
            ->update([
                'status' => $newStatus,
                'updated_at' => now()
            ]);

        // If status is delivered, update payment status if not already paid
        if ($newStatus === 'delivered') {
            $order = DB::table('sales_orders')->where('order_id', $this->selectedOrderId)->first();
            if ($order->payment_status === 'pending') {
                DB::table('sales_orders')
                    ->where('order_id', $this->selectedOrderId)
                    ->update([
                        'payment_status' => 'paid',
                        'payment_method' => 'cash',
                        'updated_at' => now()
                    ]);
            }
        }

        $this->loadOrders();
        
        // Close the modal after updating
        $this->closeOrderView();
        
        session()->flash('message', 'Order status updated to ' . ($this->statusOptions[$newStatus] ?? $newStatus) . '!');
    }

    public function markNextStatus()
    {
        if (!$this->selectedOrderId || !$this->selectedOrder) return;
        
        $currentStatus = $this->selectedOrder['status'] ?? 'draft';
        $nextStatus = $this->statusFlow[$currentStatus] ?? null;
        
        if ($nextStatus) {
            $this->updateOrderStatus($nextStatus);
        }
    }

    public function cancelOrder(int $orderId)
    {
        DB::table('sales_orders')
            ->where('order_id', $orderId)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now()
            ]);

        $this->loadOrders();
        
        // If we're in the modal for this order, close it
        if ($this->selectedOrderId == $orderId) {
            $this->closeOrderView();
        }
        
        session()->flash('message', 'Order cancelled successfully!');
    }

    public function markAsPaid(int $orderId)
    {
        DB::table('sales_orders')
            ->where('order_id', $orderId)
            ->update([
                'payment_status' => 'paid',
                'payment_method' => 'cash',
                'updated_at' => now()
            ]);

        $this->loadOrders();
        
        // If we're in the modal for this order, close it
        if ($this->selectedOrderId == $orderId) {
            $this->closeOrderView();
        }
        
        session()->flash('message', 'Payment marked as paid!');
    }

    public function getStatusColor(string $status): string
    {
        return match($status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'confirmed' => 'bg-blue-100 text-blue-800',
            'processing' => 'bg-yellow-100 text-yellow-800',
            'shipped' => 'bg-purple-100 text-purple-800',
            'delivered' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }
    
    // Helper to get next status button label
    public function getNextStatusLabel(string $currentStatus): ?string
    {
        $nextStatus = $this->statusFlow[$currentStatus] ?? null;
        return $nextStatus ? ($this->statusOptions[$nextStatus] ?? $nextStatus) : null;
    }
};
?>

<div class="p-6 bg-gray-100 min-h-screen">
    <!-- Header -->
    <div class="mb-6 max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-green-800 mb-2">Restaurant Orders Dashboard</h1>
        <p class="text-gray-600">Manage and process customer orders</p>
    </div>

    <!-- Flash Message -->
    @if(session('message'))
        <div class="max-w-6xl mx-auto mb-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                {{ session('message') }}
            </div>
        </div>
    @endif

    <!-- Controls -->
    <div class="mb-4 flex justify-start max-w-6xl mx-auto gap-2">
        <button wire:click="$toggle('showCompleted')" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded text-sm">
            {{ $showCompleted ? 'Hide Completed Orders' : 'Show Completed Orders' }}
        </button>
        <!-- <button onclick="window.location='{{ route('inventory.home') }}'" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
            Back to Inventory
        </button> -->
    </div>

    <!-- Active Orders Section -->
    <div class="mb-8 max-w-6xl mx-auto">
        <h2 class="text-xl font-semibold text-green-700 mb-3">Active Orders ({{ count($activeOrders) }})</h2>
        @if(count($activeOrders) > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($activeOrders as $order)
                    <div class="bg-white rounded-lg shadow border hover:shadow-md transition-shadow">
                        <div class="p-4 border-b">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-bold text-lg">Order #{{ $order['order_number'] }}</h3>
                                    <p class="text-sm text-gray-600">{{ $order['customer_name'] ?? 'Walk-in Customer' }}</p>
                                </div>
                                <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $this->getStatusColor($order['status']) }}">
                                    {{ $statusOptions[$order['status']] ?? ucfirst($order['status']) }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">
                                {{ date('h:i A', strtotime($order['order_date'])) }}
                            </p>
                        </div>
                        
                        <div class="p-4">
                            <p class="text-gray-700 mb-2"><strong>Items:</strong> {{ $order['items'] }}</p>
                            <p class="text-gray-700 mb-2"><strong>Quantity:</strong> {{ $order['total_quantity'] }} items</p>
                            <p class="text-gray-700 mb-3"><strong>Total:</strong> ₱{{ number_format($order['grand_total'], 2) }}</p>
                            
                            <div class="flex flex-wrap gap-2">
                                <button wire:click="viewOrder({{ $order['order_id'] }})" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm flex-1">
                                    View Details
                                </button>
                                @if($order['payment_status'] !== 'paid')
                                    <button wire:click="markAsPaid({{ $order['order_id'] }})" 
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                        Mark Paid
                                    </button>
                                @endif
                                @if($order['status'] !== 'cancelled' && $order['status'] !== 'delivered')
                                    <button wire:click="cancelOrder({{ $order['order_id'] }})" 
                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                                        Cancel
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <p class="text-gray-500 text-lg">No active orders at the moment.</p>
            </div>
        @endif
    </div>

    <!-- All Orders Table -->
    <div class="bg-white shadow rounded-lg overflow-x-auto max-w-6xl mx-auto">
        <div class="p-4 border-b">
            <h2 class="text-xl font-semibold text-green-700">All Orders</h2>
        </div>
        <table class="min-w-full table-auto border-collapse">
            <thead class="bg-green-700 text-white">
                <tr class="text-left">
                    <th class="px-4 py-3">Order #</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Items</th>
                    <th class="px-4 py-3">Qty</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Payment</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    @if($showCompleted || $order['status'] !== 'delivered')
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium">#{{ $order['order_number'] }}</td>
                            <td class="px-4 py-3">{{ $order['customer_name'] ?? 'Walk-in' }}</td>
                            <td class="px-4 py-3 text-sm">{{ Str::limit($order['items'] ?? 'No items', 50) }}</td>
                            <td class="px-4 py-3">{{ $order['total_quantity'] ?? 0 }}</td>
                            <td class="px-4 py-3">₱{{ number_format($order['grand_total'], 2) }}</td>
                            <td class="px-4 py-3">
                               <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $this->getStatusColor($order['status']) }}">
                                    {{ $statusOptions[$order['status']] ?? ucfirst($order['status']) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($order['payment_status'] === 'paid')
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                        Paid ({{ $order['payment_method'] ?? 'cash' }})
                                    </span>
                                @else
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                                        Pending
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <button wire:click="viewOrder({{ $order['order_id'] }})" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                                    View
                                </button>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                            No orders found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Order Details Modal -->
    @if($selectedOrder)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="p-6 border-b">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-2xl font-bold text-green-800">Order #{{ $selectedOrder['order_number'] }}</h2>
                            <p class="text-gray-600">{{ $selectedOrder['customer_name'] ?? 'Walk-in Customer' }}</p>
                            <p class="text-sm text-gray-500">{{ date('F j, Y h:i A', strtotime($selectedOrder['order_date'])) }}</p>
                        </div>
                        <button wire:click="closeOrderView" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Order Details -->
                <div class="p-6">
                    <!-- Items List -->
                    <div class="mb-6">
                        <h3 class="font-semibold text-lg text-gray-700 mb-3">Order Items</h3>
                        <div class="bg-gray-50 rounded-lg p-4">
                            @php
                                $items = explode(', ', $selectedOrder['items'] ?? '');
                            @endphp
                            @foreach($items as $item)
                                @if(!empty($item))
                                    <div class="flex justify-between py-2 border-b last:border-b-0">
                                        <span class="text-gray-700">{{ $item }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-sm text-gray-600">Total Quantity</p>
                            <p class="font-semibold">{{ $selectedOrder['total_quantity'] }} items</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Order Total</p>
                            <p class="font-semibold text-green-700">₱{{ number_format($selectedOrder['grand_total'], 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Payment Status</p>
                            <p class="font-semibold">
                                @if($selectedOrder['payment_status'] === 'paid')
                                    <span class="text-green-600">Paid ({{ $selectedOrder['payment_method'] ?? 'cash' }})</span>
                                @else
                                    <span class="text-yellow-600">Pending</span>
                                    <button wire:click="markAsPaid({{ $selectedOrder['order_id'] }})" 
                                            class="ml-2 bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs">
                                        Mark as Paid
                                    </button>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Current Status</p>
                            <p class="font-semibold {{ $this->getStatusColor($selectedOrder['status']) }} px-2 py-1 rounded inline-block">
                                {{ $statusOptions[$selectedOrder['status']] ?? ucfirst($selectedOrder['status']) }}
                            </p>
                        </div>
                    </div>

                    <!-- Status Update -->
                    <div class="mb-6">
                        <h3 class="font-semibold text-lg text-gray-700 mb-3">Update Order Status</h3>
                        
                        <!-- Quick Action: Mark Next Status -->
                        @if($this->getNextStatusLabel($selectedOrder['status']))
                            <button wire:click="markNextStatus" 
                                    class="w-full px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold mb-4">
                                Mark as {{ $this->getNextStatusLabel($selectedOrder['status']) }}
                            </button>
                        @endif
                        
                        <!-- All Status Options -->
                        <div class="flex flex-wrap gap-2">
                            @foreach($statusOptions as $key => $label)
                                <button wire:click="updateOrderStatus('{{ $key }}')" 
                                        class="px-4 py-2 rounded-lg border {{ $selectedOrder['status'] === $key ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button wire:click="closeOrderView" 
                                class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">
                            Close
                        </button>
                        @if($selectedOrder['status'] !== 'cancelled' && $selectedOrder['status'] !== 'delivered')
                            <button wire:click="cancelOrder({{ $selectedOrder['order_id'] }})" 
                                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg">
                                Cancel Order
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
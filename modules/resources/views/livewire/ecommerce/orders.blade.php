<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.ecommerce')] class extends Component
{
    public array $orders = [];
    public array $products = [];
    public array $customers = [];
    public bool $showCompleted = false;
    
    public string $paymentMethod = '';
    public float $paymentAmount = 0;
    public string $paymentStatus = 'Pending';
    public ?int $currentOrderId = null;
    public ?int $selectedOrderId = null;
    public array $selectedOrder = [];
    public int $step = 1;

    // Customer input
    public string $customer = '';
    public ?int $customerId = null;

    // Order input
    public array $selectedProducts = [];
    public float $totalPrice = 0;

    public function mount()
    {
        $this->products = DB::table('products')->orderBy('product_name')->get()->toArray();
        $this->customers = DB::table('customers')
            ->select(DB::raw('CONCAT(first_name," ",last_name) as name'), 'customer_id')
            ->get()
            ->toArray();

        $this->loadOrders();
    }

    public function viewPayment(int $orderId)
    {
        $this->selectedOrderId = $orderId;
        $this->selectedOrder = collect($this->orders)->first(fn($o) => $o['order_id'] == $orderId) ?? [];
        $this->step = 2;
    }

    public function backToOrders()
    {
        $this->selectedOrderId = null;
        $this->selectedOrder = [];
        $this->step = 1;
    }

    public function loadOrders()
    {
        $this->orders = DB::table('sales_orders')
            ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.customer_id')
            ->leftJoin('order_items', 'sales_orders.order_id', '=', 'order_items.order_id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.product_id')
            ->leftJoin('payments', function($join) {
                $join->on('sales_orders.order_id', '=', 'payments.reference_id')
                    ->where('payments.reference_type', 'sales_order');
            })
            ->select(
                'sales_orders.order_id',
                'sales_orders.status',
                'sales_orders.order_number',
                'sales_orders.grand_total',
                'sales_orders.payment_method',
                'sales_orders.payment_status',
                DB::raw('CONCAT(customers.first_name, " ", customers.last_name) as customer_name'),
                DB::raw('GROUP_CONCAT(products.product_name SEPARATOR ", ") as items'),
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('MAX(payments.payment_date) as payment_date'),
                DB::raw('SUM(payments.amount) as paid_amount')
            )
            ->groupBy(
                'sales_orders.order_id',
                'sales_orders.status',
                'sales_orders.order_number',
                'sales_orders.grand_total',
                'sales_orders.payment_method',
                'sales_orders.payment_status',
                'customers.first_name',
                'customers.last_name'
            )
            ->orderByDesc('sales_orders.created_at')
            ->get()
            ->map(fn($o) => (array)$o)
            ->toArray();
    }

    public function selectCustomer()
    {
        $this->customerId = DB::table('customers')
            ->where(DB::raw('CONCAT(first_name," ",last_name)'), $this->customer)
            ->value('customer_id');

        if (!$this->customerId) {
            $names = explode(' ', $this->customer, 2);
            $first = $names[0] ?? '';
            $last = $names[1] ?? '';
            $this->customerId = DB::table('customers')->insertGetId([
                'first_name' => $first,
                'last_name' => $last,
                'email' => strtolower($first.$last).'@example.com',
                'date_registered' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->step = 2;
    }

    public function calculateTotal()
    {
        $this->totalPrice = 0;
        foreach ($this->selectedProducts as $productId => $qty) {
            $product = collect($this->products)->first(fn($p) => $p->product_id == $productId);
            if ($product && $qty > 0) {
                $this->totalPrice += $product->price * $qty;
            }
        }
        $this->step = 3;
    }

    public function confirmOrder()
    {
        if (!$this->customerId || empty($this->selectedProducts)) return;

        // Generate order number
        $orderNumber = 'SO-' . date('Ymd') . '-' . str_pad(DB::table('sales_orders')->count() + 1, 4, '0', STR_PAD_LEFT);

        $orderId = DB::table('sales_orders')->insertGetId([
            'customer_id' => $this->customerId,
            'order_number' => $orderNumber,
            'order_date' => now(),
            'total_amount' => $this->totalPrice,
            'grand_total' => $this->totalPrice,
            'status' => 'draft',
            'payment_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->selectedProducts as $productId => $qty) {
            $product = collect($this->products)->first(fn($p) => $p->product_id == $productId);
            if ($product) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'price_per_unit' => $product->price,
                    'subtotal' => $product->price * $qty,
                    'total' => $product->price * $qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Move to Step 4: Payment
        $this->step = 4;
        $this->currentOrderId = $orderId;
        $this->customer = '';
        $this->selectedProducts = [];
        $this->customerId = $orderId;
    }

    public function addPayment()
    {
        if (!$this->currentOrderId || $this->paymentAmount <= 0 || !$this->paymentMethod) return;

        // Generate payment number
        $paymentNumber = 'PAY-' . date('Ymd') . '-' . str_pad(DB::table('payments')->count() + 1, 4, '0', STR_PAD_LEFT);

        DB::table('payments')->insert([
            'payment_number' => $paymentNumber,
            'reference_type' => 'sales_order',
            'reference_id' => $this->currentOrderId,
            'amount' => $this->paymentAmount,
            'payment_date' => now(),
            'method' => $this->paymentMethod,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update order payment status
        $order = DB::table('sales_orders')->where('order_id', $this->currentOrderId)->first();
        $totalPaid = DB::table('payments')
            ->where('reference_type', 'sales_order')
            ->where('reference_id', $this->currentOrderId)
            ->where('status', 'completed')
            ->sum('amount');

        $paymentStatus = 'partial';
        if ($totalPaid >= $order->grand_total) {
            $paymentStatus = 'paid';
        }

        DB::table('sales_orders')->where('order_id', $this->currentOrderId)->update([
            'payment_status' => $paymentStatus,
            'payment_method' => $this->paymentMethod,
            'status' => 'confirmed',
            'updated_at' => now()
        ]);

        // Reset fields
        $this->step = 1;
        $this->customer = '';
        $this->currentOrderId = null;
        $this->totalPrice = 0;
        $this->paymentAmount = 0;
        $this->paymentMethod = '';
        $this->paymentStatus = 'Pending';

        // Reload orders
        $this->loadOrders();
    }

    public function updateStatus(int $orderId, string $newStatus)
    {
        DB::table('sales_orders')->where('order_id', $orderId)->update([
            'status' => $newStatus,
            'updated_at' => now()
        ]);

        $this->loadOrders();
    }
};
?>
<div class="p-6 bg-gray-100 min-h-screen">

    <div class="mb-4 flex justify-start max-w-6xl mx-auto gap-2">
    <button wire:click="$toggle('showCompleted')" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded text-sm">
        {{ $showCompleted ? 'Hide Completed Orders' : 'Show Completed Orders' }}
    </button>

    <!-- Back to Orders -->
    <button onclick="window.location='{{ route('inventory.home') }}'" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
        Back to Orders
    </button>
    </div>


    <div class="bg-white shadow rounded-lg overflow-x-auto max-w-6xl mx-auto">
        <table class="min-w-full table-auto border-collapse">
            <thead class="bg-green-700 text-white">
                <tr class="text-left">
                    @foreach(['Order ID','Customer','Items','Quantity','Total','Status','Actions'] as $th)
                        <th class="px-4 py-2">{{ $th }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    @if($order['status'] !== 'Completed' || $showCompleted)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2">#{{ $order['order_id'] }}</td>
                            <td class="px-4 py-2">{{ $order['customer_name'] }}</td>
                            <td class="px-4 py-2">{{ $order['items'] }}</td>
                            <td class="px-4 py-2">{{ $order['total_quantity'] }}</td>
                            <td class="px-4 py-2">₱{{ number_format($order['total_amount'],2) }}</td>
                            <td class="px-4 py-2">{{ $order['status'] }}</td>
                            <td class="px-4 py-2">
                                <button wire:click="viewPayment({{ $order['order_id'] }})" class="bg-green-700 hover:bg-green-800 text-white px-2 py-1 rounded text-xs">Payment</button>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-4 text-center text-gray-500">No orders yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

@if($selectedOrder)
    <div class="bg-white shadow-lg rounded-lg p-6 max-w-md mx-auto mt-4">
        <h2 class="text-2xl font-bold text-green-800 mb-4">Payment Details for Order #{{ $selectedOrder['order_id'] }}</h2>
        <p><strong>Customer:</strong> {{ $selectedOrder['customer_name'] }}</p>
        <p><strong>Total Amount:</strong> ₱{{ number_format($selectedOrder['total_amount'], 2) }}</p>
        <p><strong>Amount Paid:</strong> ₱{{ number_format($selectedOrder['paid_amount'] ?? 0, 2) }}</p>
        <p><strong>Balance:</strong> ₱{{ number_format($selectedOrder['total_amount'] - ($selectedOrder['paid_amount'] ?? 0), 2) }}</p>
        <p><strong>Payment Method:</strong> {{ $selectedOrder['payment_method'] ?? '-' }}</p>
        <p><strong>Payment Status:</strong> {{ $selectedOrder['payment_status'] ?? '-' }}</p>
        <p><strong>Payment Date:</strong> {{ $selectedOrder['payment_date'] ?? '-' }}</p>

        @if($selectedOrder['status'] !== 'Completed')
            @php
                $nextStatus = $selectedOrder['status'] === 'Pending' ? 'Processing' : ($selectedOrder['status'] === 'Processing' ? 'Completed' : null);
                $btnClass = match($nextStatus) {
                    'Processing' => 'bg-blue-600 hover:bg-blue-700',
                    'Completed' => 'bg-green-700 hover:bg-green-800',
                    default => '',
                };
            @endphp
            @if($nextStatus)
                <button wire:click="updateStatus({{ $selectedOrder['order_id'] }}, '{{ $nextStatus }}')" class="px-4 py-2 rounded text-white font-semibold {{ $btnClass }} mb-4 w-full">
                    {{ $nextStatus }}
                </button>
            @endif
        @endif


        <!-- New button: Back to Home -->
        <button onclick="window.location='{{ route('pages.home') }}'" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded w-full">
            Back to Orders
        </button>
    </div>
@endif


</div>

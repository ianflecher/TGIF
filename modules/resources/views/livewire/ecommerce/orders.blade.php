<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use App\Services\OrderWorkflow;

new #[Layout('components.layouts.ecommerce')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    #[Locked] public ?int $selectedOrderId = null;
    #[Locked] public string $expectedStatus = '';

    public function mount(): void
    {
        $orderId = request()->integer('order');
        if ($orderId > 0) $this->viewOrder($orderId);
    }

    private function authorizeOrders(): void
    {
        abort_unless(auth()->user()?->role === 'admin' && !auth()->user()->trashed(), 403);
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status'])) $this->resetPage();
    }

    public function viewOrder(int $orderId): void
    {
        $this->authorizeOrders();
        $order = DB::table('sales_orders')->where('order_id', $orderId)->first();
        abort_if(!$order, 404);
        $this->selectedOrderId = $orderId;
        $this->expectedStatus = $order->status;
        $this->resetValidation();
    }

    public function closeOrderView(): void
    {
        $this->selectedOrderId = null;
        $this->expectedStatus = '';
        $this->resetValidation();
    }

    public function advanceOrder(): void
    {
        $this->authorizeOrders();
        if (!$this->selectedOrderId) return;
        app(OrderWorkflow::class)->advance(auth()->user(), $this->selectedOrderId, $this->expectedStatus);
        $this->closeOrderView();
        session()->flash('message', 'Order moved to the next stage. Payment status is unchanged.');
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    public function with(): array
    {
        $this->authorizeOrders();
        $query = DB::table('sales_orders')->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.customer_id')
            ->select('sales_orders.*', 'customers.first_name', 'customers.last_name');
        $search = substr(trim($this->search), 0, 100);
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('sales_orders.order_number', 'like', '%'.$search.'%')
                    ->orWhere('customers.first_name', 'like', '%'.$search.'%')
                    ->orWhere('customers.last_name', 'like', '%'.$search.'%');
            });
        }
        if (in_array($this->status, ['draft', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'return_requested', 'return_approved', 'return_rejected', 'returned'], true)) {
            $query->where('sales_orders.status', $this->status);
        }
        $orders = $query->orderByDesc('sales_orders.created_at')->orderByDesc('sales_orders.order_id')->paginate(15);
        $selected = $this->selectedOrderId ? DB::table('sales_orders')
            ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.customer_id')
            ->select('sales_orders.*', 'customers.first_name', 'customers.last_name', 'customers.email')
            ->where('sales_orders.order_id', $this->selectedOrderId)->first() : null;
        $items = $selected ? DB::table('order_items')->leftJoin('products', 'order_items.product_id', '=', 'products.product_id')
            ->where('order_id', $selected->order_id)->select('order_items.*')
            ->selectRaw('COALESCE(order_items.product_name, products.product_name) as product_name')->get() : collect();
        return [
            'orders' => $orders, 'selected' => $selected, 'items' => $items,
            'nextStatus' => OrderWorkflow::NEXT[$this->expectedStatus] ?? null,
            'summary' => [
                'active' => DB::table('sales_orders')->whereIn('status', ['draft', 'confirmed', 'processing', 'shipped'])->count(),
                'ready' => DB::table('sales_orders')->where('status', 'confirmed')->count(),
                'shipped' => DB::table('sales_orders')->where('status', 'shipped')->count(),
                'unpaid' => DB::table('sales_orders')->where('status', '!=', 'cancelled')->whereIn('payment_status', ['pending', 'partial'])->count(),
            ],
        ];
    }
};
?>
<div>
    <div class="erp-page-heading"><div><p class="erp-eyebrow">Commerce operations</p><h1>Customer orders</h1><p>Find an order, review its details and move fulfillment forward.</p></div><a href="{{ route('ecommerce.products') }}" class="erp-button secondary"><i class="fa-solid fa-box" aria-hidden="true"></i> Manage products</a></div>
    <div class="erp-metrics">
        @foreach([['Active orders', $summary['active'], 'Draft through shipped'], ['Awaiting preparation', $summary['ready'], 'Confirmed and ready to process'], ['On the way', $summary['shipped'], 'Shipped, awaiting delivery'], ['Awaiting full payment', $summary['unpaid'], 'Pending or partially paid orders']] as [$label, $value, $note])
            <div class="erp-metric"><div class="erp-metric-label">{{ $label }}</div><div class="erp-metric-value">{{ $value }}</div><div class="erp-metric-note">{{ $note }}</div></div>
        @endforeach
    </div>
    @if(session('message'))<div class="erp-notice" role="status">{{ session('message') }}</div>@endif
    <section class="erp-panel" aria-label="Orders">
        <div class="erp-toolbar">
            <label class="erp-field grow">Search orders<input wire:model.live.debounce.300ms="search" maxlength="100" placeholder="Order number or customer name" type="search"></label>
            <label class="erp-field">Fulfillment status<select wire:model.live="status"><option value="">All statuses</option>@foreach(['draft', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'return_requested', 'return_approved', 'return_rejected', 'returned'] as $value)<option value="{{ $value }}">{{ ucfirst(str_replace('_', ' ', $value)) }}</option>@endforeach</select></label>
            @if($search || $status)<button wire:click="clearFilters" class="erp-button secondary">Clear filters</button>@endif
        </div>
        <div class="erp-table-wrap"><table class="erp-table"><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Fulfillment</th><th>Payment</th><th class="numeric">Total</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
            @forelse($orders as $order)
                <tr wire:key="order-{{ $order->order_id }}"><td><button class="erp-text-link" wire:click="viewOrder({{ $order->order_id }})">{{ $order->order_number }}</button></td><td>{{ trim($order->first_name.' '.$order->last_name) ?: 'Customer unavailable' }}</td><td>{{ \Carbon\Carbon::parse($order->order_date)->format('M d, Y') }}</td><td><span class="erp-badge {{ $order->status === 'delivered' ? 'good' : ($order->status === 'cancelled' ? 'danger' : 'warning') }}">{{ str_replace('_', ' ', $order->status) }}</span></td><td><span class="erp-badge {{ $order->payment_status === 'paid' ? 'good' : 'warning' }}">{{ $order->payment_status }}</span></td><td class="numeric">₱{{ number_format($order->grand_total, 2) }}</td><td><button wire:click="viewOrder({{ $order->order_id }})" class="erp-button secondary" aria-label="Review order {{ $order->order_number }}">Review →</button></td></tr>
            @empty
                <tr><td colspan="7"><div class="erp-empty"><strong>{{ $search || $status ? 'No orders match your filters' : 'No customer orders yet' }}</strong>{{ $search || $status ? 'Try a different name, order number or status.' : 'Customer orders will appear here when they are placed.' }}</div></td></tr>
            @endforelse
        </tbody></table></div>
        <div style="padding:18px 20px">{{ $orders->links() }}</div>
    </section>
    @if($selected)
        <div class="erp-modal-layer" x-data x-trap.inert.noscroll="true" @keydown.escape.window="$wire.closeOrderView()" wire:key="review-{{ $selected->order_id }}">
            <section class="erp-modal" role="dialog" aria-modal="true" aria-labelledby="order-review-title">
                <div class="erp-panel-heading"><div><h2 id="order-review-title">{{ $selected->order_number }}</h2><p>Review order details before advancing fulfillment</p></div><button wire:click="closeOrderView" class="erp-button secondary" aria-label="Close order review">✕</button></div>
                <div class="erp-modal-body">
                    @error('order')<div class="erp-notice error" role="alert">{{ $message }}</div>@enderror
                    <dl class="erp-detail-grid"><div><dt>Customer</dt><dd>{{ trim($selected->first_name.' '.$selected->last_name) ?: 'Unavailable' }}</dd></div><div><dt>Email</dt><dd>{{ $selected->email ?? 'Not provided' }}</dd></div><div><dt>Fulfillment</dt><dd>{{ ucfirst(str_replace('_', ' ', $selected->status)) }}</dd></div><div><dt>Payment</dt><dd>{{ ucfirst($selected->payment_status) }} · {{ $selected->payment_method ? ucfirst(str_replace('_', ' ', $selected->payment_method)) : 'Method not recorded' }}</dd></div><div style="grid-column:1/-1"><dt>{{ $selected->fulfillment_mode === 'pickup' ? 'Pickup location' : 'Delivery address' }}</dt><dd>{{ ($selected->fulfillment_mode === 'pickup' ? $selected->pickup_location : $selected->shipping_address) ?: 'Not provided' }}</dd></div></dl>
                    <div class="erp-table-wrap"><table class="erp-table"><thead><tr><th>Item</th><th class="numeric">Qty</th><th class="numeric">Line total</th></tr></thead><tbody>@forelse($items as $item)<tr><td>{{ $item->product_name ?? 'Product unavailable' }}</td><td class="numeric">{{ $item->quantity }}</td><td class="numeric">₱{{ number_format($item->total, 2) }}</td></tr>@empty<tr><td colspan="3">No order items recorded.</td></tr>@endforelse<tr><td colspan="2"><strong>Order total</strong></td><td class="numeric"><strong>₱{{ number_format($selected->grand_total, 2) }}</strong></td></tr></tbody></table></div>
                    @if($selected->customer_notes)<p style="font-size:12px;color:#526858;margin-top:18px"><strong>Customer note:</strong> {{ $selected->customer_notes }}</p>@endif
                    <p style="margin-top:18px"><a class="erp-text-link" href="{{ route('finance.payments', ['order' => $selected->order_id]) }}">Review payments & receipts ↗</a></p><p style="font-size:11px;color:#819086;line-height:1.7;margin-top:18px">Fulfillment and payment are tracked separately. Record payments and financial adjustments in Finance. Cancellations and returns require stock and financial review.</p>
                </div>
                <div class="erp-modal-actions"><button wire:click="closeOrderView" class="erp-button secondary">Close</button>@if($nextStatus)<button wire:click="advanceOrder" wire:loading.attr="disabled" wire:target="advanceOrder" class="erp-button">Mark as {{ \App\Services\CustomerOrderAccess::label($nextStatus, $selected->fulfillment_mode) }} <span aria-hidden="true">→</span></button>@endif</div>
            </section>
        </div>
    @endif
</div>

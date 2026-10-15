<?php

namespace App\Livewire;

use App\Services\CustomerOrderAccess;
use App\Services\OrderPayments;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.storefront')]
class CustomerOrder extends Component
{
    #[Locked] public int $orderId;
    public function mount(int $order): void { app(CustomerOrderAccess::class)->order($order); $this->orderId = $order; }

    public function reorder()
    {
        app(CustomerOrderAccess::class)->reorder($this->orderId);
        session()->flash('storefront.notice', 'Your previous favorites were added at current menu prices. Review your bag before checkout.');
        return redirect()->route('customer.cart.index');
    }

    public function render()
    {
        $order = app(CustomerOrderAccess::class)->order($this->orderId);
        return view('livewire.customer-order', [
            'order' => $order,
            'items' => DB::table('order_items')->leftJoin('products', 'products.product_id', '=', 'order_items.product_id')->where('order_id', $this->orderId)->select('order_items.*', 'products.images')->get(),
            'events' => DB::table('order_events')->where('order_id', $this->orderId)->orderBy('id')->get(),
            'payments' => DB::table('payments')->where('reference_type', 'sales_order')->where('reference_id', $this->orderId)->where('status', 'completed')->orderByDesc('payment_id')->get(),
            'paymentSummary' => app(OrderPayments::class)->summary($this->orderId),
        ]);
    }
}

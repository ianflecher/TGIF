<?php

namespace App\Livewire;

use App\Services\CustomerOrderAccess;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.storefront')]
class CustomerOrders extends Component
{
    use WithPagination;
    public string $search = '';
    public string $status = '';
    public function updated(): void { $this->resetPage(); }

    public function render()
    {
        $query = app(CustomerOrderAccess::class)->query();
        if ($this->search !== '') $query->where('order_number', 'like', '%'.substr($this->search, 0, 100).'%');
        if (in_array($this->status, ['confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'], true)) $query->where('status', $this->status);
        $query->select('sales_orders.*')->selectSub(DB::table('order_items')->whereColumn('order_items.order_id', 'sales_orders.order_id')->selectRaw('COALESCE(SUM(quantity), 0)'), 'item_count');
        return view('livewire.customer-orders', ['orders' => $query->orderByDesc('sales_orders.order_id')->paginate(10)]);
    }
}

<?php
namespace App\Livewire;

use App\Services\AccessControl;
use App\Services\OrderAdjustments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.ecommerce')]
class OrderAdjustmentDesk extends Component
{
    #[Locked] public ?int $orderId = null;
    #[Locked] public string $requestKey = '';
    public string $search = ''; public string $kind = 'cancel'; public string $reason = ''; public string $feeCredit = '0'; public array $quantities = []; public array $restock = [];
    public function selectOrder(int $id): void
    {
        app(AccessControl::class)->authorize(auth()->user(), 'ecommerce', 'approve');
        $order = DB::table('sales_orders')->where('order_id', $id)->first(); abort_unless($order, 404);
        $this->orderId = $id; $this->kind = $order->status === 'delivered' ? 'return' : 'cancel'; $this->reason = ''; $this->feeCredit = '0'; $this->quantities = $this->restock = [];
        foreach (DB::table('order_items')->where('order_id', $id)->get() as $line) { $this->quantities[$line->order_item_id] = $line->quantity - DB::table('order_adjustment_items')->where('order_item_id', $line->order_item_id)->sum('quantity'); $this->restock[$line->order_item_id] = 0; }
        $this->requestKey = (string) Str::uuid(); $this->resetErrorBag();
    }
    public function record(): void
    {
        app(AccessControl::class)->authorize(auth()->user(), 'ecommerce', 'approve');
        $this->validate(['feeCredit' => 'required|numeric|min:0|max:9999999|decimal:0,2', 'quantities.*' => 'required|integer|min:0', 'restock.*' => 'required|integer|min:0']);
        if (!$this->orderId) return;
        $items = []; foreach ($this->quantities as $id => $qty) if ((int) $qty > 0) $items[] = ['id' => (int) $id, 'quantity' => (int) $qty, 'restock' => (int) ($this->restock[$id] ?? 0)];
        app(OrderAdjustments::class)->record(auth()->user(), $this->orderId, $this->kind, $this->reason, $this->requestKey, $items, (int) round((float) $this->feeCredit * 100));
        $this->orderId = null; $this->requestKey = (string) Str::uuid(); session()->flash('message', 'Adjustment recorded. Any cash refund must be recorded separately in Finance.');
    }
    public function render()
    {
        app(AccessControl::class)->authorize(auth()->user(), 'ecommerce', 'approve');
        return view('livewire.order-adjustment-desk', ['orders' => DB::table('sales_orders')->when($this->search !== '', fn ($q) => $q->where('order_number', 'like', '%'.$this->search.'%'))->latest('order_id')->limit(30)->get(),
            'order' => $this->orderId ? DB::table('sales_orders')->where('order_id', $this->orderId)->first() : null,
            'items' => DB::table('order_items')->where('order_id', $this->orderId)->get(),
            'history' => DB::table('order_adjustments')->where('order_id', $this->orderId)->latest('id')->get()]);
    }
}

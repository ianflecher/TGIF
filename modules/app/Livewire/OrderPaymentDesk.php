<?php

namespace App\Livewire;

use App\Services\OrderPayments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class OrderPaymentDesk extends Component
{
    public string $search = '';
    #[Locked] public ?int $orderId = null;
    #[Locked] public string $requestKey = '';
    public string $kind = 'receipt';
    public string $receiptId = '';
    public string $amount = '';
    public string $note = '';

    private function authorizeDesk(): void { abort_unless(auth()->user()?->role === 'admin' && !auth()->user()->trashed(), 403); }
    public function mount(): void { $this->authorizeDesk(); if (request()->integer('order') > 0) $this->selectOrder(request()->integer('order')); }
    public function selectOrder(int $order): void
    {
        $this->authorizeDesk();
        abort_unless(DB::table('sales_orders')->where('order_id', $order)->exists(), 404);
        $this->orderId = $order; $this->requestKey = (string) Str::uuid();
        $this->reset('amount', 'note', 'receiptId', 'kind'); $this->resetValidation();
    }
    public function updatedKind(): void { $this->requestKey = (string) Str::uuid(); $this->reset('amount', 'note', 'receiptId'); $this->resetValidation(); }
    public function record(): void
    {
        $this->authorizeDesk();
        if (!$this->orderId) return;
        $this->validate(['kind' => 'in:receipt,refund', 'amount' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'note' => 'required|string|min:5|max:1000', 'receiptId' => $this->kind === 'refund' ? 'required|integer' : 'nullable']);
        app(OrderPayments::class)->record(auth()->user(), $this->orderId, (int) round((float) $this->amount * 100), $this->requestKey, $this->note, $this->kind === 'refund' ? (int) $this->receiptId : null);
        $this->requestKey = (string) Str::uuid(); $this->reset('amount', 'note', 'receiptId');
        session()->flash('message', 'Cash transaction recorded. Review the receipt and ledger status below.');
    }
    public function render()
    {
        $this->authorizeDesk();
        $orders = DB::table('sales_orders')->when($this->search !== '', fn ($q) => $q->where('order_number', 'like', '%'.substr($this->search, 0, 100).'%'))->orderByDesc('order_id')->limit(30)->get();
        return view('livewire.order-payment-desk', [
            'orders' => $orders, 'selected' => $this->orderId ? DB::table('sales_orders')->where('order_id', $this->orderId)->first() : null,
            'summary' => $this->orderId ? app(OrderPayments::class)->summary($this->orderId) : null,
            'transactions' => $this->orderId ? DB::table('payments')->where('reference_type', 'sales_order')->where('reference_id', $this->orderId)->orderByDesc('payment_id')->get() : collect(),
        ]);
    }
}

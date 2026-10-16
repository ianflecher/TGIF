<?php
namespace App\Livewire;

use App\Services\AccessControl;
use App\Services\SupplierOrders;
use App\Services\SupplierPayables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SupplierInvoiceDesk extends Component
{
    #[Locked] public ?int $invoiceId = null;
    #[Locked] public ?int $poId = null;
    #[Locked] public string $invoiceKey = '';
    #[Locked] public string $paymentKey = '';
    public string $invoiceNumber = ''; public string $invoiceDate = ''; public string $dueDate = '';
    public array $lines = []; public string $search = ''; public string $orderSearch = '';
    public string $debitAccount = ''; public string $payableAccount = ''; public string $cashAccount = '';
    public string $amount = ''; public string $reason = ''; public string $paymentDate = ''; public string $reference = '';
    protected function supplierMode(): bool { return false; }
    private function access(): void
    {
        if ($this->supplierMode()) app(SupplierOrders::class)->supplierId();
        else app(AccessControl::class)->authorize(auth()->user(), 'finance');
    }
    private function invoices()
    {
        $this->access(); $query = DB::table('supplier_invoices');
        if ($this->supplierMode()) $query->where('supplier_id', app(SupplierOrders::class)->supplierId());
        return $query;
    }
    public function mount(): void
    {
        $this->access(); $this->invoiceDate = $this->dueDate = $this->paymentDate = now()->toDateString();
        $this->invoiceKey = (string) Str::uuid(); $this->paymentKey = (string) Str::uuid();
    }
    public function selectOrder(int $id): void
    {
        $this->access();
        if (!$this->supplierMode()) app(AccessControl::class)->authorize(auth()->user(), 'procurement', 'write');
        $query = DB::table('purchase_orders')->where('po_id', $id)->whereIn('status', ['confirmed', 'partially_received', 'completed']);
        if ($this->supplierMode()) $query->where('supplier_id', app(SupplierOrders::class)->supplierId());
        abort_unless($query->exists(), 404); $this->poId = $id; $this->invoiceKey = (string) Str::uuid();
        $this->lines = DB::table('purchase_order_items as l')->join('inventories as i', 'i.inventory_id', '=', 'l.inventory_id')->where('l.po_id', $id)->select('l.id as po_item_id', 'i.product_name', 'l.unit_price')->get()->map(fn ($l) => ['po_item_id' => $l->po_item_id, 'name' => $l->product_name, 'unit_price' => (string) $l->unit_price, 'quantity' => 0])->all();
        $this->resetErrorBag();
    }
    public function submitInvoice(): void
    {
        $this->access(); abort_unless($this->poId, 422);
        $this->invoiceId = app(SupplierPayables::class)->submit($this->supplierMode() ? null : auth()->user(), $this->poId, ['invoice_number' => $this->invoiceNumber, 'invoice_date' => $this->invoiceDate, 'due_date' => $this->dueDate], array_values(array_filter($this->lines, fn ($l) => (string) ($l['quantity'] ?? '') !== '0')), $this->invoiceKey);
        $this->poId = null; $this->lines = []; $this->invoiceNumber = ''; session()->flash('message', 'Invoice submitted for matching and Finance review.');
    }
    public function selectInvoice(int $id): void
    {
        abort_unless($this->invoices()->where('id', $id)->exists(), 404); $this->invoiceId = $id;
        $this->paymentKey = (string) Str::uuid(); $this->resetErrorBag();
    }
    private function internal(): void { abort_if($this->supplierMode(), 403); $this->access(); abort_unless($this->invoiceId, 422); }
    public function approveInvoice(): void { $this->internal(); app(SupplierPayables::class)->approveInvoice(auth()->user(), $this->invoiceId, (int) $this->debitAccount, (int) $this->payableAccount); }
    public function rejectInvoice(): void { $this->internal(); app(SupplierPayables::class)->rejectInvoice(auth()->user(), $this->invoiceId, $this->reason); }
    public function requestPayment(): void { $this->internal(); app(SupplierPayables::class)->requestPayment(auth()->user(), $this->invoiceId, $this->amount, $this->paymentKey); $this->paymentKey = (string) Str::uuid(); $this->amount = ''; }
    private function payment(int $id): void { $this->internal(); abort_unless(DB::table('supplier_disbursements')->where('id', $id)->where('invoice_id', $this->invoiceId)->exists(), 404); }
    public function approvePayment(int $id): void { $this->payment($id); app(SupplierPayables::class)->approvePayment(auth()->user(), $id); }
    public function cancelPayment(int $id): void { $this->payment($id); app(SupplierPayables::class)->cancelPayment(auth()->user(), $id, $this->reason); }
    public function recordPayment(int $id): void { $this->payment($id); app(SupplierPayables::class)->recordPayment(auth()->user(), $id, $this->paymentDate, $this->reference, (int) $this->cashAccount); $this->reference = ''; }
    public function render()
    {
        $this->access(); $supplier = $this->supplierMode();
        $orders = DB::table('purchase_orders as p')->join('suppliers as s', 's.supplier_id', '=', 'p.supplier_id')->whereIn('p.status', ['confirmed', 'partially_received', 'completed']);
        $orders->where('p.po_number', 'like', '%'.$this->orderSearch.'%');
        if ($supplier) $orders->where('p.supplier_id', app(SupplierOrders::class)->supplierId());
        $invoice = $this->invoiceId ? $this->invoices()->where('id', $this->invoiceId)->first() : null;
        abort_if($this->invoiceId && !$invoice, 404);
        $list = $this->invoices()->where('invoice_number', 'like', '%'.$this->search.'%')->orderByDesc('id')->limit(100)->get();
        foreach ($list as $row) { $row->supplier_name = DB::table('suppliers')->where('supplier_id', $row->supplier_id)->value('name'); $row->balances = app(SupplierPayables::class)->balances($row->id); }
        return view('livewire.supplier-invoice-desk', [
            'canSubmit' => $supplier || app(AccessControl::class)->allows(auth()->user(), 'procurement', 'write'), 'supplierMode' => $supplier, 'invoices' => $list, 'invoice' => $invoice,
            'orders' => $orders->select('p.po_id', 'p.po_number', 's.name')->orderByDesc('p.po_id')->limit(100)->get(),
            'match' => $invoice ? app(SupplierPayables::class)->matching($invoice->id) : null,
            'balance' => $invoice ? app(SupplierPayables::class)->balances($invoice->id) : null,
            'payments' => $invoice ? DB::table('supplier_disbursements as p')->leftJoin('journal_entries as j', 'j.journal_id', '=', 'p.journal_id')->where('p.invoice_id', $invoice->id)->select('p.*', 'j.status as ledger_status')->get() : collect(),
            'journalStatus' => $invoice?->journal_id ? DB::table('journal_entries')->where('journal_id', $invoice->journal_id)->value('status') : null,
            'accounts' => $supplier ? collect() : DB::table('chart_of_accounts')->where('is_active', true)->orderBy('account_code')->get(),
        ])->layout($supplier ? 'components.layouts.supplier' : 'components.layouts.finance');
    }
}

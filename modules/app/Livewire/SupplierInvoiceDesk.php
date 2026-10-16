<?php
namespace App\Livewire;

use App\Services\AccessControl;
use App\Services\SupplierOrders;
use App\Services\SupplierPayables;
use App\Services\SupplierCorrections;
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
    #[Locked] public string $creditKey = '';
    #[Locked] public string $correctionKey = '';
    public string $creditNumber = ''; public string $creditDate = ''; public string $creditReason = '';
    public array $creditLines = [];
    public string $correctionKind = 'refund'; public string $correctionPayment = ''; public string $correctionAmount = '';
    public string $correctionDate = ''; public string $correctionReference = ''; public string $correctionReason = ''; public string $reviewReason = '';
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
        $this->creditKey = (string) Str::uuid(); $this->correctionKey = (string) Str::uuid();
        $this->creditDate = $this->correctionDate = now()->toDateString();
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
        $this->creditKey = (string) Str::uuid(); $this->correctionKey = (string) Str::uuid();
        $this->creditNumber = $this->creditReason = $this->correctionPayment = $this->correctionReference = $this->correctionReason = $this->correctionAmount = '';
        $this->creditLines = $this->invoiceLines($id);
    }
    private function invoiceLines(int $id): array
    {
        return DB::table('supplier_invoice_lines as l')->join('purchase_order_items as p', 'p.id', '=', 'l.po_item_id')->join('inventories as i', 'i.inventory_id', '=', 'p.inventory_id')
            ->where('l.invoice_id', $id)->select('l.id', 'l.quantity', 'l.unit_price', 'i.product_name')->get()
            ->map(fn ($l) => ['invoice_line_id' => $l->id, 'name' => $l->product_name, 'quantity' => $l->quantity, 'unit_price' => $l->unit_price, 'amount' => '0', 'return_quantity' => 0])->all();
    }
    private function internal(): void { abort_if($this->supplierMode(), 403); $this->access(); abort_unless($this->invoiceId, 422); }
    public function approveInvoice(): void { $this->internal(); app(SupplierPayables::class)->approveInvoice(auth()->user(), $this->invoiceId, (int) $this->debitAccount, (int) $this->payableAccount); }
    public function rejectInvoice(): void { $this->internal(); app(SupplierPayables::class)->rejectInvoice(auth()->user(), $this->invoiceId, $this->reason); }
    public function requestPayment(): void { $this->internal(); app(SupplierPayables::class)->requestPayment(auth()->user(), $this->invoiceId, $this->amount, $this->paymentKey); $this->paymentKey = (string) Str::uuid(); $this->amount = ''; }
    private function payment(int $id): void { $this->internal(); abort_unless(DB::table('supplier_disbursements')->where('id', $id)->where('invoice_id', $this->invoiceId)->exists(), 404); }
    public function approvePayment(int $id): void { $this->payment($id); app(SupplierPayables::class)->approvePayment(auth()->user(), $id); }
    public function cancelPayment(int $id): void { $this->payment($id); app(SupplierPayables::class)->cancelPayment(auth()->user(), $id, $this->reason); }
    public function recordPayment(int $id): void { $this->payment($id); app(SupplierPayables::class)->recordPayment(auth()->user(), $id, $this->paymentDate, $this->reference, (int) $this->cashAccount); $this->reference = ''; }
    public function requestCredit(): void
    {
        $this->internal();
        app(SupplierCorrections::class)->requestCredit(auth()->user(), $this->invoiceId,
            ['number' => $this->creditNumber, 'date' => $this->creditDate, 'reason' => $this->creditReason],
            array_values(array_filter($this->creditLines, fn ($l) => (string) ($l['amount'] ?? '') !== '0' || (string) ($l['return_quantity'] ?? '') !== '0')), $this->creditKey);
        $this->creditKey = (string) Str::uuid(); $this->creditNumber = $this->creditReason = ''; $this->creditLines = $this->invoiceLines($this->invoiceId);
        session()->flash('message', 'Credit submitted. A different approver must review it before stock or balances change.');
    }
    private function correctionSource(string $table, int $id): void
    {
        $this->internal(); abort_unless(DB::table($table)->where('id', $id)->where('invoice_id', $this->invoiceId)->exists(), 404);
    }
    public function approveCredit(int $id): void { $this->correctionSource('supplier_credits', $id); app(SupplierCorrections::class)->approveCredit(auth()->user(), $id); }
    public function rejectCredit(int $id): void { $this->correctionSource('supplier_credits', $id); app(SupplierCorrections::class)->reject(auth()->user(), 'credit', $id, $this->reviewReason); }
    public function requestPaymentCorrection(): void
    {
        $this->payment((int) $this->correctionPayment);
        app(SupplierCorrections::class)->requestPaymentCorrection(auth()->user(), (int) $this->correctionPayment,
            ['kind' => $this->correctionKind, 'amount' => $this->correctionAmount, 'date' => $this->correctionDate, 'reference' => $this->correctionReference, 'reason' => $this->correctionReason], $this->correctionKey);
        $this->correctionKey = (string) Str::uuid(); $this->correctionReference = $this->correctionReason = $this->correctionAmount = '';
        session()->flash('message', 'Payment correction submitted for independent review.');
    }
    public function approvePaymentCorrection(int $id): void { $this->correctionSource('supplier_payment_corrections', $id); app(SupplierCorrections::class)->approvePaymentCorrection(auth()->user(), $id); }
    public function rejectPaymentCorrection(int $id): void { $this->correctionSource('supplier_payment_corrections', $id); app(SupplierCorrections::class)->reject(auth()->user(), 'payment', $id, $this->reviewReason); }
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
        $credits = $invoice ? DB::table('supplier_credits as c')->leftJoin('journal_entries as j', 'j.journal_id', '=', 'c.journal_id')->where('c.invoice_id', $invoice->id)->select('c.*', 'j.status as ledger_status')->orderByDesc('c.id')->get() : collect();
        foreach ($credits as $credit) $credit->lines = DB::table('supplier_credit_lines as c')->join('supplier_invoice_lines as l', 'l.id', '=', 'c.invoice_line_id')->join('purchase_order_items as p', 'p.id', '=', 'l.po_item_id')->join('inventories as i', 'i.inventory_id', '=', 'p.inventory_id')->where('c.credit_id', $credit->id)->select('c.amount', 'c.return_quantity', 'i.product_name')->get();
        return view('livewire.supplier-invoice-desk', [
            'canSubmit' => $supplier || app(AccessControl::class)->allows(auth()->user(), 'procurement', 'write'), 'supplierMode' => $supplier, 'invoices' => $list, 'invoice' => $invoice,
            'orders' => $orders->select('p.po_id', 'p.po_number', 's.name')->orderByDesc('p.po_id')->limit(100)->get(),
            'match' => $invoice ? app(SupplierPayables::class)->matching($invoice->id) : null,
            'balance' => $invoice ? app(SupplierPayables::class)->balances($invoice->id) : null,
            'payments' => $invoice ? DB::table('supplier_disbursements as p')->leftJoin('journal_entries as j', 'j.journal_id', '=', 'p.journal_id')->where('p.invoice_id', $invoice->id)->select('p.*', 'j.status as ledger_status')->get() : collect(),
            'journalStatus' => $invoice?->journal_id ? DB::table('journal_entries')->where('journal_id', $invoice->journal_id)->value('status') : null,
            'credits' => $credits,
            'corrections' => $invoice ? DB::table('supplier_payment_corrections as c')->leftJoin('journal_entries as j', 'j.journal_id', '=', 'c.journal_id')->where('c.invoice_id', $invoice->id)->select('c.*', 'j.status as ledger_status')->orderByDesc('c.id')->get() : collect(),
            'reconciliation' => $invoice && $invoice->status === 'approved' && !$supplier ? app(SupplierCorrections::class)->reconciliation($invoice->id) : null,
            'accounts' => $supplier ? collect() : DB::table('chart_of_accounts')->where('is_active', true)->orderBy('account_code')->get(),
        ])->layout($supplier ? 'components.layouts.supplier' : 'components.layouts.finance');
    }
}

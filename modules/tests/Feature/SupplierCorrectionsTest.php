<?php
namespace Tests\Feature;

use App\Models\User;
use App\Livewire\SupplierInvoiceDesk;
use App\Livewire\SupplierInvoicePortal;
use App\Services\{AccountingLedger, AccessControl, SupplierCorrections, SupplierPayables, WarehouseReceipts};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierCorrectionsTest extends TestCase
{
    use RefreshDatabase;
    private function fixture(bool $paid = false): array
    {
        $a = User::factory()->withoutTwoFactor()->create(['role' => 'admin']);
        $b = User::factory()->withoutTwoFactor()->create(['role' => 'admin']);
        $supplier = DB::table('suppliers')->insertGetId(['name' => 'Correction supplier', 'email' => Str::random(10).'@example.test', 'status' => 'active']);
        $stock = DB::table('inventories')->insertGetId(['sku' => Str::random(12), 'product_name' => 'Return potatoes', 'quantity' => 0]);
        $po = DB::table('purchase_orders')->insertGetId(['supplier_id' => $supplier, 'po_number' => Str::random(10), 'order_date' => now()->toDateString(), 'status' => 'confirmed', 'total_amount' => 100]);
        $poLine = DB::table('purchase_order_items')->insertGetId(['po_id' => $po, 'inventory_id' => $stock, 'quantity' => 10, 'unit_price' => 10, 'total_price' => 100]);
        $accounts = [];
        foreach (['Inventory' => 'asset', 'Payable' => 'liability', 'Bank' => 'asset'] as $name => $type) $accounts[] = DB::table('chart_of_accounts')->insertGetId(['account_code' => Str::random(12), 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $type === 'liability' ? 'credit' : 'debit', 'is_active' => true]);
        app(WarehouseReceipts::class)->receive($a, $po, (string) Str::uuid(), [['inventory_id' => $stock, 'quantity_received' => 10, 'quality_status' => 'passed']]);
        $s = app(SupplierPayables::class);
        $invoice = $s->submit($a, $po, ['invoice_number' => 'INV-'.Str::random(8), 'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString()], [['po_item_id' => $poLine, 'quantity' => 10, 'unit_price' => 10]], (string) Str::uuid());
        $s->approveInvoice($b, $invoice, $accounts[0], $accounts[1]);
        app(AccountingLedger::class)->period($a, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $this->postJournal(DB::table('supplier_invoices')->find($invoice)->journal_id, $a);
        $line = DB::table('supplier_invoice_lines')->where('invoice_id', $invoice)->value('id'); $payment = null;
        if ($paid) {
            $payment = $s->requestPayment($a, $invoice, '100', (string) Str::uuid()); $s->approvePayment($b, $payment);
            $s->recordPayment($a, $payment, now()->toDateString(), 'PAY-'.Str::random(8), $accounts[2]);
            $this->postJournal(DB::table('supplier_disbursements')->find($payment)->journal_id, $b);
        }
        return compact('a', 'b', 'supplier', 'stock', 'po', 'invoice', 'line', 'accounts', 'payment');
    }
    private function postJournal(int $id, User $reviewer): void
    {
        $ledger = app(AccountingLedger::class); $ledger->submit($reviewer, $id); $ledger->approve($reviewer, $id); $ledger->post($reviewer, $id);
    }
    private function rejects(callable $action): void
    {
        try { $action(); $this->fail('Expected validation rejection'); } catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
    }
    private function credit(array $f, string $amount = '20', int $qty = 2, ?string $key = null, string $number = 'CN-1'): int
    {
        return app(SupplierCorrections::class)->requestCredit($f['a'], $f['invoice'], ['number' => $number, 'date' => now()->toDateString(), 'reason' => 'Supplier agreed damaged goods credit'], [['invoice_line_id' => $f['line'], 'amount' => $amount, 'return_quantity' => $qty]], $key ?? (string) Str::uuid());
    }
    private function correction(array $f, string $kind = 'refund', string $amount = '20', ?string $key = null, string $reference = 'REFUND-1'): int
    {
        return app(SupplierCorrections::class)->requestPaymentCorrection($f['a'], $f['payment'], ['kind' => $kind, 'amount' => $amount, 'date' => now()->toDateString(), 'reference' => $reference, 'reason' => $kind === 'void' ? 'Confirmed payment entry never occurred' : 'Bank confirms cash received from supplier'], $key ?? (string) Str::uuid());
    }
    public function test_paid_return_credit_and_partial_cash_refunds_reconcile(): void
    {
        $f = $this->fixture(true); $c = app(SupplierCorrections::class); $s = app(SupplierPayables::class);
        $key = (string) Str::uuid(); $id = $this->credit($f, '20', 2, $key); $this->assertSame($id, $this->credit($f, '20.00', 2, $key));
        $this->assertDatabaseHas('inventories', ['inventory_id' => $f['stock'], 'quantity' => 10]);
        $this->rejects(fn () => $c->approveCredit($f['a'], $id)); $c->approveCredit($f['b'], $id); $c->approveCredit($f['b'], $id);
        $this->assertDatabaseHas('inventories', ['inventory_id' => $f['stock'], 'quantity' => 8]);
        $this->assertEquals(20, $s->balances($f['invoice'])['refund_due']);
        $this->rejects(fn () => $this->correction($f));
        $r = $c->reconciliation($f['invoice']); $this->assertEquals(20, $r['difference']); $this->assertEquals(1, $r['unposted']); $this->assertTrue($r['stock_matches']);
        $this->postJournal(DB::table('supplier_credits')->find($id)->journal_id, $f['a']);
        foreach (['5', '15'] as $amount) {
            $key = (string) Str::uuid(); $p = $this->correction($f, 'refund', $amount, $key, 'REF-'.$amount);
            $this->assertSame($p, $this->correction($f, 'refund', $amount, $key, 'REF-'.$amount));
            $this->rejects(fn () => $c->approvePaymentCorrection($f['a'], $p)); $c->approvePaymentCorrection($f['b'], $p); $c->approvePaymentCorrection($f['b'], $p);
            $this->postJournal(DB::table('supplier_payment_corrections')->find($p)->journal_id, $f['a']);
        }
        $balance = $s->balances($f['invoice']); $this->assertEquals(80, $balance['paid']); $this->assertEquals(20, $balance['refunded']); $this->assertEquals(0, $balance['refund_due']); $this->assertEquals(0, $balance['outstanding']);
        $r = $c->reconciliation($f['invoice']); $this->assertEquals(0, $r['difference']); $this->assertEquals(0, $r['cash_difference']); $this->assertEquals(-80, $r['cash_posted']); $this->assertEquals(2, $r['returned']); $this->assertEquals(0, $r['unposted']);
        $this->rejects(fn () => $this->correction($f, 'refund', '0.01', null, 'EXCESS'));
    }
    public function test_discount_changes_payable_without_moving_stock_and_respects_payment_reservations(): void
    {
        $f = $this->fixture(); $c = app(SupplierCorrections::class); $s = app(SupplierPayables::class);
        $payment = $s->requestPayment($f['a'], $f['invoice'], '100', (string) Str::uuid());
        $id = $this->credit($f, '10', 0); $this->rejects(fn () => $c->approveCredit($f['b'], $id));
        $s->cancelPayment($f['b'], $payment, 'Credit reduces payable balance'); $c->approveCredit($f['b'], $id);
        $this->assertDatabaseHas('inventories', ['inventory_id' => $f['stock'], 'quantity' => 10]);
        $this->assertEquals(90, $s->balances($f['invoice'])['outstanding']);
        $this->rejects(fn () => $s->requestPayment($f['a'], $f['invoice'], '90', (string) Str::uuid()));
        $this->postJournal(DB::table('supplier_credits')->find($id)->journal_id, $f['a']);
        $s->requestPayment($f['a'], $f['invoice'], '90', (string) Str::uuid());
        $this->assertEquals(0, $s->balances($f['invoice'])['available']); $this->assertTrue($c->reconciliation($f['invoice'])['stock_matches']);
    }
    public function test_full_void_preserves_original_and_reopens_payable(): void
    {
        $f = $this->fixture(true); $c = app(SupplierCorrections::class); $s = app(SupplierPayables::class);
        $this->rejects(fn () => $this->correction($f, 'void', '50'));
        $id = $this->correction($f, 'void', '100'); $c->approvePaymentCorrection($f['b'], $id);
        $this->assertEquals(100, $s->balances($f['invoice'])['outstanding']);
        $this->assertDatabaseHas('supplier_disbursements', ['id' => $f['payment'], 'status' => 'recorded', 'amount' => 100]);
        $this->postJournal(DB::table('supplier_payment_corrections')->find($id)->journal_id, $f['a']);
        $this->assertEquals(0, $c->reconciliation($f['invoice'])['cash_posted']);
        $this->rejects(fn () => $this->correction($f, 'void', '100', null, 'SECOND-VOID'));
        $new = $s->requestPayment($f['a'], $f['invoice'], '60', (string) Str::uuid()); $this->assertNotEquals($f['payment'], $new);
        $this->assertEquals(0, $c->reconciliation($f['invoice'])['difference']);
    }
    public function test_stock_and_credit_limits_rollback_all_side_effects(): void
    {
        $f = $this->fixture(); $c = app(SupplierCorrections::class);
        $this->rejects(fn () => $this->credit($f, '10', 2)); $this->rejects(fn () => $this->credit($f, '110', 11));
        $id = $this->credit($f, '30', 3); DB::table('inventories')->where('inventory_id', $f['stock'])->update(['quantity' => 2]);
        $this->rejects(fn () => $c->approveCredit($f['b'], $id)); $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseHas('supplier_credits', ['id' => $id, 'status' => 'pending']);
        DB::table('inventories')->where('inventory_id', $f['stock'])->update(['quantity' => 10]); $c->approveCredit($f['b'], $id);
        $this->postJournal(DB::table('supplier_credits')->find($id)->journal_id, $f['a']);
        $this->rejects(fn () => $this->credit($f, '80', 8, null, 'TOO-MUCH'));
        $this->assertEquals(1, DB::table('stock_transactions')->where('reference_type', 'supplier_credit')->count());
    }
    public function test_pending_refunds_reserve_due_and_rejection_releases_it(): void
    {
        $f = $this->fixture(true); $c = app(SupplierCorrections::class); $id = $this->credit($f, '30', 0); $c->approveCredit($f['b'], $id); $this->postJournal(DB::table('supplier_credits')->find($id)->journal_id, $f['a']);
        $pending = $this->correction($f, 'refund', '25');
        $this->rejects(fn () => $this->correction($f, 'refund', '6', null, 'TOO-MUCH'));
        $this->rejects(fn () => $this->correction($f, 'void', '100', null, 'VOID-WITH-REFUND'));
        $c->reject($f['b'], 'payment', $pending, 'Incorrect refund reference supplied');
        $new = $this->correction($f, 'refund', '30', null, 'CORRECTED'); $c->approvePaymentCorrection($f['b'], $new);
        $this->assertEquals(0, app(SupplierPayables::class)->balances($f['invoice'])['refund_due']);
        $this->rejects(fn () => $c->reject($f['b'], 'payment', $new, 'Cannot reject approved money'));
    }
    public function test_permissions_supplier_ownership_and_form_review_are_enforced(): void
    {
        $f = $this->fixture(); $this->actingAs($f['a']);
        Livewire::test(SupplierInvoiceDesk::class)->call('selectInvoice', $f['invoice'])->set('creditNumber', 'FORM-CREDIT')->set('creditReason', 'Agreed invoice discount')->set('creditLines.0.amount', '5')->call('requestCredit')->assertHasNoErrors()->assertSee('FORM-CREDIT');
        $id = DB::table('supplier_credits')->value('id');
        $this->actingAs($f['b']); Livewire::test(SupplierInvoiceDesk::class)->call('selectInvoice', $f['invoice'])->call('approveCredit', $id)->assertHasNoErrors()->assertSee('Supplier reconciliation');
        $this->withSession(['supplier' => (object) ['supplier_id' => $f['supplier']]]);
        Livewire::test(SupplierInvoicePortal::class)->call('selectInvoice', $f['invoice'])->assertSee('FORM-CREDIT')->call('approveCredit', $id)->assertForbidden();
        app(AccessControl::class)->configure($f['a'], $f['b']->getKey(), ['finance' => ['read', 'write', 'approve']]);
        $this->postJournal(DB::table('supplier_credits')->find($id)->journal_id, $f['a']);
        try { app(SupplierCorrections::class)->requestCredit($f['b'], $f['invoice'], ['number' => 'NO-WAREHOUSE', 'date' => now()->toDateString(), 'reason' => 'Warehouse access is required'], [['invoice_line_id' => $f['line'], 'amount' => '10', 'return_quantity' => 1]], (string) Str::uuid()); $this->fail('Expected forbidden'); } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertEquals(403, $e->getStatusCode()); }
    }
    public function test_correction_journals_obey_closed_periods_source_checks_and_immutability(): void
    {
        $f = $this->fixture(true); $c = app(SupplierCorrections::class); $id = $this->credit($f, '10', 0); $c->approveCredit($f['b'], $id); $j = DB::table('supplier_credits')->find($id)->journal_id;
        DB::table('journal_details')->where('journal_id', $j)->where('debit', '>', 0)->update(['debit' => 9]); DB::table('journal_details')->where('journal_id', $j)->where('credit', '>', 0)->update(['credit' => 9]); DB::table('journal_entries')->where('journal_id', $j)->update(['total_debit' => 9, 'total_credit' => 9]);
        $this->rejects(fn () => $this->postJournal($j, $f['a']));
        app(AccountingLedger::class)->returnForReview($f['a'], $j, 'Restore approved source amounts');
        DB::table('journal_details')->where('journal_id', $j)->where('debit', '>', 0)->update(['debit' => 10]); DB::table('journal_details')->where('journal_id', $j)->where('credit', '>', 0)->update(['credit' => 10]); DB::table('journal_entries')->where('journal_id', $j)->update(['total_debit' => 10, 'total_credit' => 10]);
        $this->postJournal($j, $f['a']); $this->rejects(fn () => app(AccountingLedger::class)->reverse($f['a'], $j, now()->toDateString(), 'Cannot reverse source independently'));
        $correction = $this->correction($f, 'refund', '10'); $c->approvePaymentCorrection($f['b'], $correction);
        $this->rejects(fn () => app(AccountingLedger::class)->closePeriod($f['a'], DB::table('financial_periods')->value('id')));
        $this->assertEquals(1, $c->reconciliation($f['invoice'])['unposted']);
        $this->postJournal(DB::table('supplier_payment_corrections')->find($correction)->journal_id, $f['a']);
        $pending = $this->credit($f, '5', 0, null, 'CLOSED-PERIOD');
        app(AccountingLedger::class)->closePeriod($f['a'], DB::table('financial_periods')->value('id'));
        $this->rejects(fn () => $c->approveCredit($f['b'], $pending));
        $this->assertDatabaseHas('supplier_credits', ['id' => $pending, 'status' => 'pending']);
    }
}

<?php
namespace Tests\Feature;
use App\Models\User;
use App\Livewire\SupplierInvoiceDesk;
use App\Livewire\SupplierInvoicePortal;
use App\Services\{AccountingLedger,SupplierOrders,SupplierPayables,WarehouseReceipts};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;
class SupplierPayablesTest extends TestCase
{
    use RefreshDatabase;
    private function admin(): User { return User::factory()->withoutTwoFactor()->create(['role'=>'admin']); }
    private function setupOrder(): array {
        $s=DB::table('suppliers')->insertGetId(['name'=>'Test supplier','email'=>Str::random(8).'@example.test','status'=>'active']);
        $i=DB::table('inventories')->insertGetId(['sku'=>Str::random(12),'product_name'=>'Test potatoes','quantity'=>0]);
        $p=DB::table('purchase_orders')->insertGetId(['supplier_id'=>$s,'po_number'=>Str::random(12),'order_date'=>now()->toDateString(),'status'=>'sent','total_amount'=>100]);
        $l=DB::table('purchase_order_items')->insertGetId(['po_id'=>$p,'inventory_id'=>$i,'quantity'=>10,'unit_price'=>10,'total_price'=>100]);
        $this->withSession(['supplier'=>(object)['supplier_id'=>$s,'name'=>'Test supplier']]);
        $accounts=[]; foreach(['Inventory'=>'asset','Payable'=>'liability','Bank'=>'asset'] as $name=>$type) $accounts[]=DB::table('chart_of_accounts')->insertGetId(['account_code'=>Str::random(10),'account_name'=>$name,'account_type'=>$type,'normal_balance'=>$type==='liability'?'credit':'debit','is_active'=>true]);
        app(SupplierOrders::class)->accept($p); return compact('s','i','p','l','accounts');
    }
    private function submit(array $f,int $qty=10,string $price='10',?User $actor=null,?string $key=null,string $number='INV-1'): int {
        return app(SupplierPayables::class)->submit($actor,$f['p'],['invoice_number'=>$number,'invoice_date'=>now()->toDateString(),'due_date'=>now()->addDays(30)->toDateString()],[['po_item_id'=>$f['l'],'quantity'=>$qty,'unit_price'=>$price]],$key??(string)Str::uuid());
    }
    private function receive(array $f,User $actor,int $qty=10,string $quality='passed'): void { app(WarehouseReceipts::class)->receive($actor,$f['p'],(string)Str::uuid(),[['inventory_id'=>$f['i'],'quantity_received'=>$qty,'quality_status'=>$quality]]); }
    private function rejects(callable $action): void { try{$action();$this->fail('Expected validation rejection');}catch(ValidationException $e){$this->assertNotEmpty($e->errors());} }
    private function postJournal(int $id,User $b): void { $l=app(AccountingLedger::class);$l->submit($b,$id);$l->approve($b,$id);$l->post($b,$id); }
    public function test_full_cycle_reconciles_stock_partial_payments_and_ledger(): void {
        $f=$this->setupOrder();$a=$this->admin();$b=$this->admin();$s=app(SupplierPayables::class);$l=app(AccountingLedger::class);
        app(SupplierOrders::class)->dispatch($f['p']);$this->receive($f,$a);$this->assertDatabaseHas('purchase_orders',['po_id'=>$f['p'],'status'=>'completed']);
        $id=$this->submit($f);$this->assertTrue($s->matching($id)['matched']);$s->approveInvoice($a,$id,$f['accounts'][0],$f['accounts'][1]);
        $j=DB::table('supplier_invoices')->find($id)->journal_id;$this->rejects(fn()=>$s->requestPayment($a,$id,'40',(string)Str::uuid()));
        $l->period($b,now()->startOfMonth()->toDateString(),now()->endOfMonth()->toDateString());$this->postJournal($j,$b);
        foreach([40,60] as $amount){$key=(string)Str::uuid();$p=$s->requestPayment($a,$id,(string)$amount,$key);$this->assertSame($p,$s->requestPayment($a,$id,(string)$amount,$key));$this->rejects(fn()=>$s->approvePayment($a,$p));$s->approvePayment($b,$p);$s->recordPayment($a,$p,now()->toDateString(),'BANK-'.$amount,$f['accounts'][2]);$s->recordPayment($a,$p,now()->toDateString(),'BANK-'.$amount,$f['accounts'][2]);$this->postJournal(DB::table('supplier_disbursements')->find($p)->journal_id,$b);}
        $this->assertEquals(100,$s->balances($id)['paid']);$this->assertEquals(0,$s->balances($id)['outstanding']);$this->assertDatabaseCount('supplier_disbursements',2);$this->assertDatabaseCount('journal_entries',3);
        $this->assertEquals(0,DB::table('journal_details')->where('account_id',$f['accounts'][1])->sum(DB::raw('debit-credit')));$this->assertEquals(-100,DB::table('journal_details')->where('account_id',$f['accounts'][2])->sum(DB::raw('debit-credit')));$this->assertDatabaseHas('inventories',['inventory_id'=>$f['i'],'quantity'=>10]);
        $this->rejects(fn()=>$s->requestPayment($a,$id,'0.01',(string)Str::uuid()));$this->rejects(fn()=>$l->reverse($b,$j,now()->toDateString(),'Reconcile the supplier source'));
    }
    public function test_match_excludes_failed_goods_and_prevents_duplicate_billing(): void {
        $f=$this->setupOrder();$a=$this->admin();$s=app(SupplierPayables::class);$id=$this->submit($f,6);$this->assertFalse($s->matching($id)['matched']);$this->receive($f,$a,4,'failed');$this->assertFalse($s->matching($id)['matched']);$this->receive($f,$a,5);
        $this->rejects(fn()=>$s->approveInvoice($a,$id,$f['accounts'][0],$f['accounts'][1]));$wrong=$this->submit($f,1,'11',null,null,'WRONG');$this->assertFalse($s->matching($wrong)['matched']);$this->rejects(fn()=>$s->approveInvoice($a,$wrong,$f['accounts'][0],$f['accounts'][1]));
        $first=$this->submit($f,4,'10',null,null,'PART-1');$s->approveInvoice($a,$first,$f['accounts'][0],$f['accounts'][1]);$second=$this->submit($f,2,'10',null,null,'PART-2');$this->assertFalse($s->matching($second)['matched']);$this->receive($f,$a,5);$this->assertTrue($s->matching($second)['matched']);$s->approveInvoice($a,$second,$f['accounts'][0],$f['accounts'][1]);$this->assertTrue($s->matching($first)['matched']);
    }
    public function test_duplicate_invoice_and_self_approval_are_rejected(): void {
        $f=$this->setupOrder();$a=$this->admin();$this->receive($f,$a);$s=app(SupplierPayables::class);$key=(string)Str::uuid();$id=$this->submit($f,10,'10',$a,$key);$this->assertSame($id,$this->submit($f,10,'10.00',$a,$key));$this->rejects(fn()=>$this->submit($f,9,'10',$a,$key));$this->rejects(fn()=>$this->submit($f));$this->rejects(fn()=>$s->approveInvoice($a,$id,$f['accounts'][0],$f['accounts'][1]));$s->rejectInvoice($this->admin(),$id,'Supplier must correct invoice');$this->rejects(fn()=>$s->approveInvoice($this->admin(),$id,$f['accounts'][0],$f['accounts'][1]));$this->assertDatabaseCount('journal_entries',0);
    }
    public function test_payment_reservations_cancellation_evidence_and_overpayment(): void {
        $f=$this->setupOrder();$a=$this->admin();$b=$this->admin();$s=app(SupplierPayables::class);$this->receive($f,$a);$id=$this->submit($f);$s->approveInvoice($a,$id,$f['accounts'][0],$f['accounts'][1]);app(AccountingLedger::class)->period($b,now()->startOfMonth()->toDateString(),now()->endOfMonth()->toDateString());$this->postJournal(DB::table('supplier_invoices')->find($id)->journal_id,$b);
        $p=$s->requestPayment($a,$id,'80',(string)Str::uuid());$this->rejects(fn()=>$s->requestPayment($b,$id,'21',(string)Str::uuid()));$this->rejects(fn()=>$s->recordPayment($a,$p,now()->toDateString(),'BANK-1',$f['accounts'][2]));$s->cancelPayment($b,$p,'Wrong requested amount');$this->assertEquals(100,$s->balances($id)['available']);
        $p=$s->requestPayment($a,$id,'50',(string)Str::uuid());$s->approvePayment($b,$p);$this->rejects(fn()=>$s->recordPayment($a,$p,now()->addDay()->toDateString(),'BANK-1',$f['accounts'][2]));$s->recordPayment($a,$p,now()->toDateString(),'BANK-1',$f['accounts'][2]);$this->rejects(fn()=>$s->recordPayment($a,$p,now()->toDateString(),'BANK-2',$f['accounts'][2]));$p2=$s->requestPayment($a,$id,'50',(string)Str::uuid());$s->approvePayment($b,$p2);$this->rejects(fn()=>$s->recordPayment($a,$p2,now()->toDateString(),' bank-1 ',$f['accounts'][2]));$this->rejects(fn()=>$s->cancelPayment($b,$p,'Cannot cancel money already paid'));
    }
    public function test_supplier_and_finance_permissions_are_enforced_on_pages_and_actions(): void {
        $f=$this->setupOrder();$id=$this->submit($f);$this->get('/supplier/invoices')->assertOk()->assertSee('INV-1');Livewire::test(SupplierInvoicePortal::class)->call('selectInvoice',$id)->call('approveInvoice')->assertForbidden();
        $other=DB::table('suppliers')->insertGetId(['name'=>'Other','email'=>'other@example.test','status'=>'active']);$this->withSession(['supplier'=>(object)['supplier_id'=>$other]]);Livewire::test(SupplierInvoicePortal::class)->call('selectInvoice',$id)->assertNotFound();Livewire::test(SupplierInvoicePortal::class)->call('selectOrder',$f['p'])->assertNotFound();DB::table('suppliers')->where('supplier_id',$other)->update(['status'=>'suspended']);Livewire::test(SupplierInvoicePortal::class)->assertForbidden();
        $a=$this->admin();$this->actingAs($a)->get('/finance/supplier-payables')->assertOk();DB::table('users')->where('user_id',$a->getKey())->update(['module_permissions'=>json_encode(['procurement'=>['read','write']])]);$this->get('/finance/supplier-payables')->assertForbidden();Livewire::test(SupplierInvoiceDesk::class)->assertForbidden();
    }
    public function test_source_journal_cannot_post_with_changed_approved_amounts(): void {
        $f=$this->setupOrder();$a=$this->admin();$b=$this->admin();$s=app(SupplierPayables::class);$this->receive($f,$a);$id=$this->submit($f);$s->approveInvoice($a,$id,$f['accounts'][0],$f['accounts'][1]);
        $j=DB::table('supplier_invoices')->find($id)->journal_id;
        DB::table('journal_details')->where('journal_id',$j)->where('debit','>',0)->update(['debit'=>90]);DB::table('journal_details')->where('journal_id',$j)->where('credit','>',0)->update(['credit'=>90]);DB::table('journal_entries')->where('journal_id',$j)->update(['total_debit'=>90,'total_credit'=>90]);
        app(AccountingLedger::class)->period($b,now()->startOfMonth()->toDateString(),now()->endOfMonth()->toDateString());$this->rejects(fn()=>$this->postJournal($j,$b));$this->assertDatabaseHas('journal_entries',['journal_id'=>$j,'status'=>'draft']);
    }
    public function test_supplier_invoice_form_submits_server_validated_lines(): void {
        $f=$this->setupOrder();$a=$this->admin();$this->receive($f,$a);
        Livewire::test(SupplierInvoicePortal::class)->call('selectOrder',$f['p'])->set('invoiceNumber','FORM-1')->set('lines.0.quantity',3)->call('submitInvoice')->assertHasNoErrors()->assertSee('FORM-1');
        $this->assertDatabaseHas('supplier_invoices',['invoice_number'=>'FORM-1','total'=>30,'created_by'=>null]);
        $this->assertDatabaseCount('supplier_invoice_events',1);
        $this->actingAs($a);$invoice=DB::table('supplier_invoices')->first();
        Livewire::test(SupplierInvoiceDesk::class)->call('selectInvoice',$invoice->id)->set('debitAccount',(string)$f['accounts'][0])->set('payableAccount',(string)$f['accounts'][1])->call('approveInvoice')->assertHasNoErrors()->assertSee('Payment balance')->assertSee('draft');
    }
}


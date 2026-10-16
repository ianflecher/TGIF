<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\AccessControl;
use App\Services\AccountingLedger;
use App\Services\CommerceSettings;
use App\Services\LedgerReports;
use App\Services\OrderAdjustments;
use App\Services\OrderPayments;
use App\Services\OrderPlacement;
use App\Services\OrderWorkflow;
use App\Services\StorefrontCart;
use App\Services\WarehouseReceipts;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuditControlsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->withoutTwoFactor()->create(['role' => 'admin']); }
    private function rejects(callable $action, string $field): void
    {
        try { $action(); $this->fail('Expected validation rejection'); }
        catch (ValidationException $error) { $this->assertArrayHasKey($field, $error->errors()); }
    }
    private function accounts(): array
    {
        return [DB::table('chart_of_accounts')->insertGetId(['account_code' => 'CASH-'.Str::random(8), 'account_name' => 'Test cash', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_active' => true]),
            DB::table('chart_of_accounts')->insertGetId(['account_code' => 'REV-'.Str::random(8), 'account_name' => 'Test revenue', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'is_active' => true])];
    }
    private function draft(User $author, string $date = '2026-10-15', float $amount = 100): int
    {
        [$cash, $sales] = $this->accounts();
        return app(AccountingLedger::class)->draft($author->getKey(), $date, 'Synthetic accounting test', [['account_id' => $cash, 'debit' => $amount, 'credit' => 0], ['account_id' => $sales, 'debit' => 0, 'credit' => $amount]]);
    }
    private function postJournal(int $id, User $reviewer): void
    {
        $ledger = app(AccountingLedger::class); $ledger->submit($reviewer, $id); $ledger->approve($reviewer, $id); $ledger->post($reviewer, $id);
    }
    private function order(): object
    {
        $shopper = User::factory()->withoutTwoFactor()->create(); $this->actingAs($shopper);
        $inventory = DB::table('inventories')->insertGetId(['sku' => 'AUDIT-'.Str::random(10), 'product_name' => 'Test ingredient', 'cost_price' => 20, 'quantity' => 50]);
        $id = DB::table('products')->insertGetId(['product_name' => 'Test meal', 'slug' => 'meal-'.Str::random(10), 'price' => 100, 'stock_quantity' => 20, 'status' => 'published', 'inventory_id' => $inventory]);
        app(StorefrontCart::class)->add($id, 2); $service = app(OrderPlacement::class); $quote = $service->preview(['mode' => 'delivery']);
        return $service->place($shopper, ['name' => 'Test Shopper', 'phone' => '09170000000', 'address' => 'Synthetic test address', 'notes' => ''], ['mode' => 'delivery'], $quote['fingerprint'], (string) Str::uuid());
    }
    private function item(object $order, int $quantity, int $restock): array
    {
        return [['id' => (int) DB::table('order_items')->where('order_id', $order->order_id)->value('order_item_id'), 'quantity' => $quantity, 'restock' => $restock]];
    }
    private function application(User $owner): int
    {
        return DB::table('job_applications')->insertGetId(['user_id' => $owner->getKey(), 'position_applied' => 'Test position', 'years_experience' => '1', 'application_date' => '2026-10-15', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_applicant_files_are_private_and_owner_or_authorized_hr_only(): void
    {
        Storage::fake('applicant_private'); Storage::fake('public');
        $owner = User::factory()->withoutTwoFactor()->create(['role' => 'employee']); $this->application($owner); $this->actingAs($owner);
        Volt::test('applicant.index')->set('newDocuments', [UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf')])->call('uploadDocuments')->assertHasNoErrors();
        $document = DB::table('application_documents')->first(); $this->assertSame('applicant_private', $document->disk);
        Storage::disk('applicant_private')->assertExists($document->filepath); Storage::disk('public')->assertMissing($document->filepath);
        $this->get(route('application.documents.download', $document->id))->assertOk()->assertDownload('resume.pdf');
        $this->actingAs(User::factory()->withoutTwoFactor()->create())->get(route('application.documents.download', $document->id))->assertNotFound();
        $hr = $this->admin(); DB::table('users')->where('user_id', $hr->getKey())->update(['module_permissions' => json_encode(['hr' => ['read']])]);
        $this->actingAs($hr)->get(route('application.documents.download', $document->id))->assertOk();
        DB::table('users')->where('user_id', $hr->getKey())->update(['module_permissions' => json_encode(['finance' => ['read']])]);
        $this->get(route('application.documents.download', $document->id))->assertNotFound();
        $this->actingAs($this->admin()); app(\App\Services\ApplicantDocuments::class)->restore($document->id);
        $this->actingAs($owner)->get(route('application.documents.download', $document->id))->assertOk();
        $this->actingAs($owner); Volt::test('applicant.index')->call('deleteDocument', $document->id);
        $this->assertNotNull(DB::table('application_documents')->value('deleted_at')); Storage::disk('applicant_private')->assertExists($document->filepath);
        $this->get(route('application.documents.download', $document->id))->assertNotFound();
    }

    public function test_public_document_migration_is_dry_by_default_and_verifies_content(): void
    {
        Storage::fake('public'); Storage::fake('applicant_private'); $owner = User::factory()->withoutTwoFactor()->create(); $app = $this->application($owner);
        $path = 'application_documents/'.$app.'/legacy.pdf'; Storage::disk('public')->put($path, 'synthetic private content');
        $id = DB::table('application_documents')->insertGetId(['application_id' => $app, 'user_id' => $owner->getKey(), 'filename' => 'legacy.pdf', 'filepath' => $path, 'filetype' => 'application/pdf', 'filesize' => 25, 'disk' => 'public']);
        $this->artisan('applicants:privatize')->assertSuccessful(); Storage::disk('public')->assertExists($path);
        $this->artisan('applicants:privatize --execute')->assertSuccessful(); Storage::disk('public')->assertMissing($path);
        $this->assertSame('synthetic private content', Storage::disk('applicant_private')->get($path));
        $this->assertDatabaseHas('application_documents', ['id' => $id, 'disk' => 'applicant_private', 'sha256' => hash('sha256', 'synthetic private content')]);
    }

    public function test_module_permissions_apply_to_page_and_direct_livewire_actions(): void
    {
        $root = $this->admin(); $limited = $this->admin();
        app(AccessControl::class)->configure($root, $limited->getKey(), ['ecommerce' => ['read']]);
        $this->actingAs($limited)->get('/finance/accounts')->assertForbidden();
        $this->get('/ecommerce/orders')->assertOk();
        Volt::test('ecommerce.orders')->call('advanceOrder')->assertForbidden();
        Volt::test('finance.accounts')->assertForbidden();
        $this->assertFalse(app(AccessControl::class)->allows($limited, 'ecommerce', 'write'));
    }

    public function test_unconfigured_employees_cannot_open_warehouse(): void
    {
        $employee = User::factory()->withoutTwoFactor()->create(['role' => 'employee']);
        DB::table('employees')->insert(['user_id' => $employee->getKey(), 'job_title' => 'Test staff', 'hire_date' => '2026-01-01', 'status' => 'active']);
        $this->actingAs($employee)->get('/employee/warehouse')->assertForbidden();
        app(AccessControl::class)->configure($this->admin(), $employee->getKey(), ['inventory' => ['read', 'write']]);
        $this->get('/employee/warehouse')->assertOk();
    }

    public function test_journal_requires_independent_approval_open_period_and_balanced_lines(): void
    {
        $author = $this->admin(); $reviewer = $this->admin(); $ledger = app(AccountingLedger::class); $id = $this->draft($author);
        $ledger->submit($author, $id);
        $this->rejects(fn () => $ledger->approve($author, $id), 'ledger');
        $ledger->approve($reviewer, $id);
        $this->rejects(fn () => $ledger->post($reviewer, $id), 'ledger');
        $period = $ledger->period($reviewer, '2026-10-01', '2026-10-31'); $ledger->post($reviewer, $id); $ledger->post($reviewer, $id);
        $this->assertDatabaseHas('journal_entries', ['journal_id' => $id, 'status' => 'posted', 'approved_by' => $reviewer->getKey()]);
        $this->assertSame(10000, app(LedgerReports::class)->totals()['revenue']);
        $this->rejects(fn () => $ledger->period($reviewer, '2026-10-15', '2026-11-15'), 'ledger');
        $ledger->closePeriod($reviewer, $period);
        $another = $this->draft($author); $ledger->submit($author, $another); $ledger->approve($reviewer, $another);
        $this->rejects(fn () => $ledger->post($reviewer, $another), 'ledger');
    }

    public function test_posted_journals_and_submitted_details_are_immutable_even_through_legacy_sql(): void
    {
        $author = $this->admin(); $reviewer = $this->admin(); $ledger = app(AccountingLedger::class); $id = $this->draft($author);
        $ledger->period($author, '2026-10-01', '2026-10-31'); $this->postJournal($id, $reviewer);
        foreach ([fn () => DB::table('journal_entries')->where('journal_id', $id)->update(['total_debit' => 1]), fn () => DB::table('journal_details')->where('journal_id', $id)->delete(), fn () => DB::table('journal_entries')->where('journal_id', $id)->delete()] as $mutation) {
            try { $mutation(); $this->fail('Expected immutable journal rejection'); } catch (QueryException) { $this->assertTrue(true); }
        }
        $this->assertDatabaseHas('journal_entries', ['journal_id' => $id, 'status' => 'posted', 'total_debit' => 100]);
    }

    public function test_reversal_is_a_distinct_replay_safe_draft_and_reports_net_correctly(): void
    {
        $author = $this->admin(); $reviewer = $this->admin(); $ledger = app(AccountingLedger::class);
        $ledger->period($author, '2026-10-01', '2026-10-31'); $id = $this->draft($author); $this->postJournal($id, $reviewer);
        $reversal = $ledger->reverse($author, $id, '2026-10-16', 'Synthetic correction');
        $this->assertSame($reversal, $ledger->reverse($author, $id, '2026-10-16', 'Synthetic correction'));
        $this->assertSame(10000, app(LedgerReports::class)->totals()['revenue']);
        $this->postJournal($reversal, $reviewer); $totals = app(LedgerReports::class)->totals();
        $this->assertSame(0, $totals['revenue']); $this->assertTrue($totals['balanced']);
        $this->assertDatabaseHas('journal_entries', ['journal_id' => $id, 'status' => 'posted']);
    }

    public function test_cancellation_reconciles_stock_credit_and_paid_cash_without_fabricating_refund(): void
    {
        $order = $this->order(); $actor = $this->admin(); $payments = app(OrderPayments::class);
        $receipt = $payments->record($actor, $order->order_id, 20000, (string) Str::uuid(), 'Synthetic cash collection');
        $items = $this->item($order, 2, 1); $key = (string) Str::uuid(); $service = app(OrderAdjustments::class);
        $id = $service->record($actor, $order->order_id, 'cancel', 'Synthetic cancellation', $key, $items);
        $this->assertSame($id, $service->record($actor, $order->order_id, 'cancel', 'Synthetic cancellation', $key, $items));
        $this->assertDatabaseHas('sales_orders', ['order_id' => $order->order_id, 'status' => 'cancelled', 'grand_total' => 200]);
        $product = DB::table('order_items')->where('order_id', $order->order_id)->value('product_id');
        $this->assertSame(19, (int) DB::table('products')->where('product_id', $product)->value('stock_quantity'));
        $this->assertSame(20000, $payments->summary($order->order_id)['refund_due']); $this->assertDatabaseCount('payments', 1);
        $payments->record($actor, $order->order_id, 20000, (string) Str::uuid(), 'Cash returned after cancellation', $receipt);
        $this->assertSame(0, $payments->summary($order->order_id)['refund_due']); $this->assertDatabaseCount('order_adjustments', 1);
    }

    public function test_partial_returns_preserve_original_total_and_limit_remaining_quantities(): void
    {
        $order = $this->order(); $actor = $this->admin();
        foreach (['confirmed', 'processing', 'shipped'] as $status) app(OrderWorkflow::class)->advance($actor, $order->order_id, $status);
        $service = app(OrderAdjustments::class); $service->record($actor, $order->order_id, 'return', 'One item returned', (string) Str::uuid(), $this->item($order, 1, 1));
        $this->assertSame(10000, app(OrderPayments::class)->summary($order->order_id)['due']);
        $this->rejects(fn () => $service->record($actor, $order->order_id, 'return', 'Too many returned', (string) Str::uuid(), $this->item($order, 2, 0)), 'adjustment');
        $service->record($actor, $order->order_id, 'return', 'Second item disposed', (string) Str::uuid(), $this->item($order, 1, 0));
        $this->assertDatabaseHas('sales_orders', ['order_id' => $order->order_id, 'status' => 'returned', 'grand_total' => 200]);
        $this->assertSame(0, app(OrderPayments::class)->summary($order->order_id)['adjusted_total']);
    }

    public function test_cancellation_in_open_period_is_not_required_to_record_operational_credit(): void
    {
        $order = $this->order(); $actor = $this->admin();
        app(OrderAdjustments::class)->record($actor, $order->order_id, 'cancel', 'Unprepared order cancelled', (string) Str::uuid(), $this->item($order, 2, 2));
        $this->assertDatabaseHas('journal_entries', ['reference_type' => 'sales_order', 'reference_id' => $order->order_id, 'status' => 'cancelled']);
        $this->assertSame(0, app(LedgerReports::class)->totals()['revenue']);
    }

    public function test_warehouse_receipts_validate_po_ownership_and_are_replay_safe(): void
    {
        $actor = $this->admin(); $supplier = DB::table('suppliers')->insertGetId(['name' => 'Test supplier', 'status' => 'active']);
        $po = DB::table('purchase_orders')->insertGetId(['supplier_id' => $supplier, 'po_number' => 'CONTROLS-PO', 'order_date' => '2026-10-15', 'status' => 'confirmed']);
        $stock = DB::table('inventories')->insertGetId(['sku' => 'CONTROLS-STOCK', 'product_name' => 'Test inventory', 'quantity' => 10]);
        DB::table('purchase_order_items')->insert(['po_id' => $po, 'inventory_id' => $stock, 'quantity' => 5, 'unit_price' => 20, 'total_price' => 100]);
        $service = app(WarehouseReceipts::class); $key = (string) Str::uuid();
        $input = [['inventory_id' => $stock, 'quantity_received' => 3, 'quality_status' => 'passed', 'expiry_date' => null]];
        $batch = $service->receive($actor, $po, $key, $input); $this->assertSame($batch, $service->receive($actor, $po, $key, $input));
        $this->assertDatabaseHas('inventories', ['inventory_id' => $stock, 'quantity' => 13]);
        $this->assertDatabaseHas('purchase_orders', ['po_id' => $po, 'status' => 'partially_received']);
        $this->rejects(fn () => $service->receive($actor, $po, (string) Str::uuid(), $input), 'receipts');
        $input[0]['quantity_received'] = 2; $input[0]['quality_status'] = 'failed'; $service->receive($actor, $po, (string) Str::uuid(), $input);
        $this->assertDatabaseHas('inventories', ['inventory_id' => $stock, 'quantity' => 13]);
        $input[0]['quality_status'] = 'passed'; $service->receive($actor, $po, (string) Str::uuid(), $input);
        $this->assertDatabaseHas('inventories', ['inventory_id' => $stock, 'quantity' => 15]);
        $this->assertDatabaseHas('purchase_orders', ['po_id' => $po, 'status' => 'completed']);
    }

    public function test_posted_sale_partial_return_and_cash_refund_reconcile_to_the_ledger(): void
    {
        $order = $this->order(); $actor = $this->admin(); $reviewer = $this->admin(); $ledger = app(AccountingLedger::class);
        $ledger->period($actor, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $original = DB::table('journal_entries')->where('reference_type', 'sales_order')->where('reference_id', $order->order_id)->value('journal_id');
        $this->postJournal($original, $actor);
        [$cash] = $this->accounts(); $ar = DB::table('chart_of_accounts')->where('account_code', '1100')->value('account_id');
        app(CommerceSettings::class)->save(['cash_account_id' => $cash, 'receivable_account_id' => $ar]);
        $receipt = app(OrderPayments::class)->record($actor, $order->order_id, 20000, (string) Str::uuid(), 'Synthetic full cash collection');
        $receiptJournal = DB::table('payments')->where('payment_id', $receipt)->value('journal_id'); $this->postJournal($receiptJournal, $reviewer);
        foreach (['confirmed', 'processing', 'shipped'] as $status) app(OrderWorkflow::class)->advance($actor, $order->order_id, $status);
        $adjustment = app(OrderAdjustments::class)->record($actor, $order->order_id, 'return', 'One unit returned safely', (string) Str::uuid(), $this->item($order, 1, 1));
        $this->postJournal(DB::table('order_adjustments')->where('id', $adjustment)->value('journal_id'), $reviewer);
        $this->rejects(fn () => app(OrderPayments::class)->record($actor, $order->order_id, 10001, (string) Str::uuid(), 'Too large cash refund', $receipt), 'payment');
        $refund = app(OrderPayments::class)->record($actor, $order->order_id, 10000, (string) Str::uuid(), 'Actual synthetic cash returned', $receipt);
        $this->postJournal(DB::table('payments')->where('payment_id', $refund)->value('journal_id'), $reviewer);
        $totals = app(LedgerReports::class)->totals();
        $this->assertSame(10000, $totals['revenue']); $this->assertSame(2000, $totals['expense']); $this->assertSame(8000, $totals['income']);
        $this->assertSame(10000, app(LedgerReports::class)->accountBalance($cash)); $this->assertSame(0, app(LedgerReports::class)->accountBalance($ar));
        $this->assertSame(0, app(OrderPayments::class)->summary($order->order_id)['refund_due']); $this->assertTrue($totals['balanced']);
    }

    public function test_financial_statements_use_accounting_dates_and_exclude_drafts(): void
    {
        $author = $this->admin(); $reviewer = $this->admin(); $this->actingAs($author);
        $ledger = app(AccountingLedger::class); $ledger->period($author, '2026-10-01', '2026-10-31');
        $posted = $this->draft($author, '2026-10-10', 100); $this->postJournal($posted, $reviewer);
        $this->draft($author, '2026-10-11', 900);
        DB::table('journal_details')->where('journal_id', '!=', $posted)->update(['created_at' => '2020-01-01']);
        $component = Volt::test('finance.accounts')->set('statementType', 'income_statement')->set('statementPeriod', 'current_month')->call('generateFinancialStatement');
        $this->assertEquals(100, $component->get('financialStatementData')['total_revenue']);
        $component->set('statementType', 'balance_sheet')->call('generateFinancialStatement'); $balance = $component->get('financialStatementData');
        $this->assertEquals(100, $balance['total_assets']); $this->assertEquals(100, $balance['total_equity']);
        $component->set('startDate', '2026-10-01')->set('endDate', '2026-10-31')->call('generateTrialBalance');
        $trial = $component->get('trialBalanceData'); $this->assertEquals(100, $trial->sum('total_debit')); $this->assertEquals(100, $trial->sum('total_credit'));
    }

    public function test_returning_a_journal_for_review_clears_approval_and_preserves_audit(): void
    {
        $author = $this->admin(); $reviewer = $this->admin(); $id = $this->draft($author); $ledger = app(AccountingLedger::class);
        $ledger->submit($author, $id); $ledger->approve($reviewer, $id);
        try { DB::table('journal_entries')->where('journal_id', $id)->update(['entry_date' => '2026-10-16']); $this->fail('Submitted header should be frozen'); } catch (QueryException) { $this->assertTrue(true); }
        $ledger->returnForReview($reviewer, $id, 'Supporting evidence needs revision');
        $this->assertDatabaseHas('journal_entries', ['journal_id' => $id, 'status' => 'draft', 'submitted_at' => null, 'approved_by' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'return_for_review', 'record_id' => $id]);
    }

    public function test_staff_tickets_recheck_assignment_and_attendance_cannot_change_another_employee(): void
    {
        $user = User::factory()->withoutTwoFactor()->create(['role' => 'employee']); $this->actingAs($user);
        $employee = DB::table('employees')->insertGetId(['user_id' => $user->getKey(), 'job_title' => 'Test agent', 'hire_date' => '2026-01-01', 'status' => 'active']);
        $other = User::factory()->withoutTwoFactor()->create(['role' => 'employee']); $otherEmployee = DB::table('employees')->insertGetId(['user_id' => $other->getKey(), 'job_title' => 'Other agent', 'hire_date' => '2026-01-01', 'status' => 'active']);
        app(\App\Services\StaffAttendance::class)->record(true); app(\App\Services\StaffAttendance::class)->record(true); app(\App\Services\StaffAttendance::class)->record(false);
        $this->assertDatabaseCount('hr_attendance', 1); $this->assertDatabaseHas('hr_attendance', ['employee_id' => $employee]);
        $customer = DB::table('customers')->insertGetId(['first_name' => 'Test', 'last_name' => 'Customer', 'email' => 'ticket@example.test', 'date_registered' => '2026-10-15']);
        $ticket = DB::table('tickets')->insertGetId(['customer_id' => $customer, 'assigned_agent' => $employee, 'ticket_number' => 'CONTROLS-TICKET', 'subject' => 'Test ticket', 'issue_description' => 'Synthetic', 'priority' => 'medium', 'category' => 'general', 'status' => 'open']);
        $component = Volt::test('employee.support')->call('selectTicket', $ticket);
        DB::table('tickets')->where('ticket_id', $ticket)->update(['assigned_agent' => $otherEmployee]);
        $component->set('newNote', 'Must not reach another agent ticket')->call('addNote')->assertNotFound(); $this->assertDatabaseCount('ticket_notes', 0);
    }

    public function test_hr_permission_does_not_allow_role_escalation_and_promotions_need_explicit_grants(): void
    {
        $root = $this->admin(); $hr = $this->admin(); $target = User::factory()->withoutTwoFactor()->create(['role' => 'employee']);
        app(AccessControl::class)->configure($root, $hr->getKey(), ['hr' => ['read', 'write', 'approve']]);
        $this->actingAs($hr);
        $component = Volt::test('hr.applications')->call('openRoleChangeModal', $target->getKey());
        $this->assertObjectNotHasProperty('password', $component->get('selectedUserForRoleChange'));
        $component->set('newRole', 'admin')->call('changeUserRole')->assertForbidden();
        $this->assertDatabaseHas('users', ['user_id' => $target->getKey(), 'role' => 'employee']);
        app(\App\Services\IdentityAdministration::class)->changeRole($root, $target->getKey(), 'admin');
        $this->assertFalse(app(AccessControl::class)->allows($target->fresh(), 'finance'));
        $this->assertSame('profile.edit', app(AccessControl::class)->home($target->fresh()));
    }
}

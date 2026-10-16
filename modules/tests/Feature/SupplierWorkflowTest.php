<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SupplierOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SupplierWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(string $name = 'Supplier One'): int
    {
        return DB::table('suppliers')->insertGetId(['name' => $name, 'email' => str_replace(' ', '', $name).'@example.test', 'password' => Hash::make('unique-test-password'), 'status' => 'active']);
    }

    private function signIn(int $id): void
    {
        $supplier = DB::table('suppliers')->where('supplier_id', $id)->first();
        unset($supplier->password);
        $this->withSession(['supplier' => $supplier]);
    }

    private function order(int $supplier, string $status = 'sent'): int
    {
        return DB::table('purchase_orders')->insertGetId(['supplier_id' => $supplier, 'po_number' => 'PO-'.\Illuminate\Support\Str::random(10), 'order_date' => '2026-10-15', 'status' => $status, 'total_amount' => 500]);
    }

    public function test_supplier_can_only_list_and_view_their_issued_orders(): void
    {
        $first = $this->supplier();
        $second = $this->supplier('Supplier Two');
        $own = $this->order($first);
        $draft = $this->order($first, 'draft');
        $other = $this->order($second);
        $this->signIn($first);
        $component = Volt::test('supplier.dashboard')->assertCount('purchaseOrders', 1)->call('viewOrder', $own)->assertSet('selectedOrder.po_id', $own);
        $component->call('loadOrders', $second)->assertCount('purchaseOrders', 1)->assertSet('purchaseOrders.0.po_id', $own);
        Volt::test('supplier.dashboard')->call('viewOrder', $other)->assertNotFound();
        Volt::test('supplier.dashboard')->call('viewOrder', $draft)->assertNotFound();
    }

    public function test_supplier_cannot_accept_or_dispatch_another_suppliers_order(): void
    {
        $first = $this->supplier();
        $other = $this->order($this->supplier('Supplier Two'));
        $this->signIn($first);
        Volt::test('supplier.dashboard')->call('acceptOrder', $other)->assertNotFound();
        Volt::test('supplier.dashboard')->call('markAsDelivered', $other)->assertNotFound();
        $this->assertDatabaseHas('purchase_orders', ['po_id' => $other, 'status' => 'sent', 'supplier_dispatched_at' => null]);
        $this->assertDatabaseCount('supplier_order_events', 0);
    }

    public function test_acceptance_and_dispatch_are_replay_safe_and_do_not_receive_stock(): void
    {
        $supplier = $this->supplier();
        $order = $this->order($supplier);
        $this->signIn($supplier);
        $component = Volt::test('supplier.dashboard')->call('acceptOrder', $order)->call('acceptOrder', $order)->assertHasNoErrors();
        $this->assertDatabaseHas('purchase_orders', ['po_id' => $order, 'status' => 'confirmed']);
        $component->call('markAsDelivered', $order)->call('markAsDelivered', $order)->assertHasNoErrors()->assertSee('Dispatch reported');
        $this->assertDatabaseHas('purchase_orders', ['po_id' => $order, 'status' => 'confirmed']);
        $this->assertNotNull(DB::table('purchase_orders')->where('po_id', $order)->value('supplier_dispatched_at'));
        $this->assertDatabaseCount('supplier_order_events', 2);
        $this->assertDatabaseCount('goods_receipts', 0);
        $this->assertDatabaseCount('stock_transactions', 0);
    }

    public function test_supplier_cannot_accept_drafts_or_change_terminal_orders(): void
    {
        $supplier = $this->supplier();
        $this->signIn($supplier);
        $draft = $this->order($supplier, 'draft');
        Volt::test('supplier.dashboard')->call('acceptOrder', $draft)->assertNotFound();
        foreach (['cancelled', 'completed', 'partially_received'] as $status) {
            $order = $this->order($supplier, $status);
            Volt::test('supplier.dashboard')->call('acceptOrder', $order)->assertHasErrors('order');
            Volt::test('supplier.dashboard')->call('markAsDelivered', $order)->assertHasErrors('order');
            $this->assertDatabaseHas('purchase_orders', ['po_id' => $order, 'status' => $status]);
        }
    }

    public function test_supplier_cannot_access_internal_supplier_management(): void
    {
        $this->signIn($this->supplier());
        $this->get('/supplier/list')->assertRedirect(route('portals.procurement.login'));
        Volt::test('supplier.list')->assertForbidden();
        $this->actingAs(User::factory()->withoutTwoFactor()->create(['role' => 'admin']));
        $this->get('/supplier/list')->assertOk();
        $component = Volt::test('supplier.list');
        $this->assertObjectNotHasProperty('password', $component->get('suppliers')[0]);
    }

    public function test_disabled_supplier_loses_access_and_cannot_submit_actions(): void
    {
        $supplier = $this->supplier();
        $order = $this->order($supplier);
        $this->signIn($supplier);
        $component = Volt::test('supplier.dashboard');
        DB::table('suppliers')->where('supplier_id', $supplier)->update(['status' => 'suspended']);
        $component->call('acceptOrder', $order)->assertForbidden();
        $this->get('/supplier')->assertRedirect(route('supplier.login'));
        $this->assertDatabaseHas('purchase_orders', ['po_id' => $order, 'status' => 'sent']);
    }

    public function test_supplier_login_logout_and_invalid_password(): void
    {
        $this->get('/supplier/login')->assertOk()->assertDontSee('supplier123')->assertDontSee('Test Supplier Account');
        $supplier = $this->supplier();
        $email = DB::table('suppliers')->where('supplier_id', $supplier)->value('email');
        Volt::test('auth.supplier')->set('email', $email)->set('password', 'wrong')->call('login')->assertHasErrors('password');
        Volt::test('auth.supplier')->set('email', $email)->set('password', 'unique-test-password')->call('login')->assertRedirect(route('supplier.dashboard'));
        $this->get('/supplier')->assertOk()->assertSee('Supplier One');
        $this->post('/supplier/logout')->assertRedirect(route('supplier.login'));
        $this->assertNull(session('supplier'));
        $this->get('/supplier')->assertRedirect(route('supplier.login'));
    }

    public function test_order_items_are_not_multiplied_by_multiple_catalog_products(): void
    {
        $supplier = $this->supplier();
        $order = $this->order($supplier);
        $inventory = DB::table('inventories')->insertGetId(['sku' => 'SUPPLIER-TEST', 'product_name' => 'Test potatoes', 'quantity' => 10]);
        DB::table('purchase_order_items')->insert(['po_id' => $order, 'inventory_id' => $inventory, 'quantity' => 5, 'unit_price' => 100, 'total_price' => 500]);
        foreach (['one', 'two'] as $slug) DB::table('products')->insert(['product_name' => $slug, 'slug' => $slug, 'inventory_id' => $inventory, 'price' => 100, 'status' => 'published']);
        $this->signIn($supplier);
        Volt::test('supplier.dashboard')->call('viewOrder', $order)->assertCount('orderItems', 1)->assertSee('Test potatoes')->assertSee('Not specified');
    }

    public function test_management_preserves_order_history_and_unique_login_email(): void
    {
        $supplier = $this->supplier();
        $order = $this->order($supplier);
        $this->actingAs(User::factory()->withoutTwoFactor()->create(['role' => 'admin']));
        Volt::test('supplier.list')->call('deleteSupplier', $supplier)->assertSee('purchase order history');
        $this->assertDatabaseHas('suppliers', ['supplier_id' => $supplier]);
        $this->assertDatabaseHas('purchase_orders', ['po_id' => $order]);
        Volt::test('supplier.list')->set('supplier_name', 'Duplicate login')->set('email', 'SupplierOne@example.test')->call('saveSupplier')->assertHasErrors('email');
        $this->assertDatabaseCount('suppliers', 1);
    }
}

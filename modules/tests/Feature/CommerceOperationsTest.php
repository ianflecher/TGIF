<?php

namespace Tests\Feature;

use App\Livewire\CommerceSettings as SettingsPage;
use App\Livewire\CustomerOrder;
use App\Livewire\OrderPaymentDesk;
use App\Models\User;
use App\Services\CommerceSettings;
use App\Services\CustomerOrderAccess;
use App\Services\FulfillmentRules;
use App\Services\OrderPayments;
use App\Services\OrderPlacement;
use App\Services\OrderWorkflow;
use App\Services\StorefrontCart;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CommerceOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $slug = 'meal', int $stock = 20): int
    {
        return DB::table('products')->insertGetId(['product_name' => ucfirst($slug), 'slug' => $slug, 'price' => 100, 'status' => 'published', 'stock_quantity' => $stock]);
    }

    private function order(?User $user = null, string $mode = 'delivery'): object
    {
        $user ??= User::factory()->withoutTwoFactor()->create();
        $this->actingAs($user);
        $product = $this->product('meal-'.Str::random(8));
        app(StorefrontCart::class)->add($product, 2);
        $quote = app(OrderPlacement::class)->preview(['mode' => $mode]);
        return app(OrderPlacement::class)->place($user, ['name' => 'Test Shopper', 'phone' => '09171234567', 'address' => '123 Test Street, Test City', 'notes' => 'Test note'], ['mode' => $mode], $quote['fingerprint'], (string) Str::uuid());
    }

    private function rules(array $changes = []): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));
        app(CommerceSettings::class)->save($changes + [
            'rules_enabled' => true, 'timezone' => 'UTC',
            'hours' => array_fill_keys(range(1, 7), ['open' => '09:00', 'close' => '21:00']),
            'zones' => [['code' => 'test-area', 'label' => 'Test area', 'fee_cents' => 5000, 'minimum_cents' => 15000, 'postal_codes' => ['1000']]],
        ]);
    }

    public function test_delivery_zone_fee_and_minimum_are_validated_on_the_server(): void
    {
        $this->rules();
        $quote = app(FulfillmentRules::class)->quote(20000, ['mode' => 'delivery', 'zone' => 'test-area', 'postal' => '1000']);
        $this->assertSame(25000, $quote['total']);
        $this->assertSame(5000, $quote['fee']);
        foreach ([['subtotal' => 10000, 'postal' => '1000', 'field' => 'checkout'], ['subtotal' => 20000, 'postal' => '9999', 'field' => 'postalCode']] as $case) {
            try {
                app(FulfillmentRules::class)->quote($case['subtotal'], ['mode' => 'delivery', 'zone' => 'test-area', 'postal' => $case['postal']]);
                $this->fail('Expected a fulfillment rule rejection.');
            } catch (ValidationException $error) { $this->assertArrayHasKey($case['field'], $error->errors()); }
        }
    }

    public function test_opening_hours_support_overnight_shifts_and_exclude_closing_time(): void
    {
        $hours = [4 => ['open' => '20:00', 'close' => '02:00']];
        $rules = app(FulfillmentRules::class);
        $this->assertTrue($rules->isOpen(CarbonImmutable::parse('2026-10-16 01:30:00'), $hours));
        $this->assertFalse($rules->isOpen(CarbonImmutable::parse('2026-10-16 02:00:00'), $hours));
        $this->assertFalse($rules->isOpen(CarbonImmutable::parse('2026-10-15 19:30:00'), $hours));
    }

    public function test_scheduling_enforces_lead_time_and_store_hours(): void
    {
        $this->rules(['scheduling_enabled' => true, 'lead_minutes' => 30]);
        $input = ['mode' => 'delivery', 'zone' => 'test-area', 'postal' => '1000'];
        $this->assertSame('2026-10-15 13:00:00', app(FulfillmentRules::class)->quote(20000, $input + ['scheduled' => '2026-10-15T13:00'])['scheduled']);
        foreach (['2026-10-15T12:10', '2026-10-15T22:00', 'not-a-time'] as $time) {
            try { app(FulfillmentRules::class)->quote(20000, $input + ['scheduled' => $time]); $this->fail('Expected schedule rejection.'); }
            catch (ValidationException $error) { $this->assertArrayHasKey('scheduledFor', $error->errors()); }
        }
    }

    public function test_pickup_uses_configured_location_and_has_no_delivery_fee(): void
    {
        $this->rules(['pickup_enabled' => true, 'pickup_address' => '123 Store Street, Test City']);
        $quote = app(FulfillmentRules::class)->quote(20000, ['mode' => 'pickup']);
        $this->assertSame(0, $quote['fee']);
        $this->assertSame('123 Store Street, Test City', $quote['pickup']);
    }

    public function test_changed_catalog_price_requires_review_without_losing_the_bag(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $this->actingAs($user);
        $id = $this->product();
        app(StorefrontCart::class)->add($id);
        $checkout = Volt::test('customer.checkout')->set('name', 'Test Shopper')->set('phone', '09171234567')->set('address', '123 Test Street, Test City');
        DB::table('products')->where('product_id', $id)->update(['price' => 150]);
        $checkout->call('placeOrder')->assertHasErrors('quote')->assertSet('total', 150.0)->assertSet('address', '123 Test Street, Test City');
        $this->assertDatabaseCount('sales_orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $checkout->call('placeOrder')->assertHasNoErrors();
        $this->assertDatabaseHas('sales_orders', ['grand_total' => 150]);
    }

    public function test_checkout_token_is_idempotent_and_creates_one_timeline(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $this->actingAs($user);
        app(StorefrontCart::class)->add($this->product());
        $quote = app(OrderPlacement::class)->preview([]);
        $details = ['name' => 'Test Shopper', 'phone' => '09171234567', 'address' => '123 Test Street, Test City', 'notes' => ''];
        $token = (string) Str::uuid();
        $one = app(OrderPlacement::class)->place($user, $details, [], $quote['fingerprint'], $token);
        $two = app(OrderPlacement::class)->place($user, $details, [], $quote['fingerprint'], $token);
        $this->assertSame($one->order_id, $two->order_id);
        $this->assertDatabaseCount('sales_orders', 1);
        $this->assertDatabaseCount('order_events', 1);
    }

    public function test_customers_cannot_read_other_orders_or_receipts(): void
    {
        $order = $this->order();
        $admin = User::factory()->withoutTwoFactor()->create(['role' => 'admin']);
        $receipt = app(OrderPayments::class)->record($admin, $order->order_id, 20000, (string) Str::uuid(), 'Cash collected by test staff');
        $this->get(route('customer.orders.show', $order->order_id))->assertOk()->assertSee($order->order_number);
        $response = $this->get(route('customer.orders.receipt', [$order->order_id, $receipt]))->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->actingAs(User::factory()->withoutTwoFactor()->create());
        $this->get(route('customer.orders.show', $order->order_id))->assertNotFound();
        $this->get(route('customer.orders.receipt', [$order->order_id, $receipt]))->assertNotFound();
    }

    public function test_fulfillment_updates_are_visible_in_the_customer_timeline(): void
    {
        $order = $this->order();
        app(OrderWorkflow::class)->advance(User::factory()->withoutTwoFactor()->create(['role' => 'admin']), $order->order_id, 'confirmed');
        $this->get(route('customer.orders.show', $order->order_id))->assertOk()->assertSee('Order update: Processing.');
        $this->assertDatabaseCount('order_events', 2);
    }

    public function test_repeat_order_rolls_back_all_additions_if_any_item_is_unavailable(): void
    {
        $order = $this->order();
        $item = DB::table('order_items')->where('order_id', $order->order_id)->first();
        $unavailable = $this->product('sold-out', 0);
        DB::table('order_items')->insert(['order_id' => $order->order_id, 'product_id' => $unavailable, 'product_name' => 'Sold out', 'quantity' => 1, 'price_per_unit' => 100, 'subtotal' => 100, 'total' => 100]);
        app(StorefrontCart::class)->add($item->product_id);
        try { app(CustomerOrderAccess::class)->reorder($order->order_id); $this->fail('Expected availability rejection.'); }
        catch (ValidationException) { $this->assertDatabaseHas('cart_items', ['product_id' => $item->product_id, 'quantity' => 1]); }
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_cash_receipt_replay_cannot_collect_twice_or_overpay(): void
    {
        $order = $this->order();
        $actor = User::factory()->withoutTwoFactor()->create(['role' => 'admin']);
        $key = (string) Str::uuid();
        $service = app(OrderPayments::class);
        $id = $service->record($actor, $order->order_id, 20000, $key, 'Cash collection reference');
        $this->assertSame($id, $service->record($actor, $order->order_id, 20000, $key, 'Cash collection reference'));
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('sales_orders', ['order_id' => $order->order_id, 'payment_status' => 'paid']);
        $this->expectException(ValidationException::class);
        $service->record($actor, $order->order_id, 100, (string) Str::uuid(), 'Extra cash should be rejected');
    }

    public function test_refunds_are_receipt_scoped_and_cannot_exceed_collected_cash(): void
    {
        $order = $this->order();
        $actor = User::factory()->withoutTwoFactor()->create(['role' => 'admin']);
        $service = app(OrderPayments::class);
        $receipt = $service->record($actor, $order->order_id, 20000, (string) Str::uuid(), 'Cash collection reference');
        $key = (string) Str::uuid();
        $refund = $service->record($actor, $order->order_id, 5000, $key, 'Cash returned to customer', $receipt);
        $this->assertSame($refund, $service->record($actor, $order->order_id, 5000, $key, 'Cash returned to customer', $receipt));
        $this->assertSame(15000, $service->summary($order->order_id)['net']);
        $this->assertDatabaseCount('payments', 2);
        $this->expectException(ValidationException::class);
        $service->record($actor, $order->order_id, 15001, (string) Str::uuid(), 'Over refund should be rejected', $receipt);
    }

    public function test_mapped_cash_receipt_creates_a_balanced_draft_journal(): void
    {
        $order = $this->order();
        $cash = DB::table('chart_of_accounts')->insertGetId(['account_code' => 'TEST-CASH', 'account_name' => 'Test cash', 'account_type' => 'asset', 'normal_balance' => 'debit']);
        $ar = DB::table('chart_of_accounts')->where('account_code', '1100')->value('account_id');
        app(CommerceSettings::class)->save(['cash_account_id' => $cash, 'receivable_account_id' => $ar]);
        $payment = app(OrderPayments::class)->record(User::factory()->withoutTwoFactor()->create(['role' => 'admin']), $order->order_id, 20000, (string) Str::uuid(), 'Cash collected for journal test');
        $journal = DB::table('payments')->where('payment_id', $payment)->value('journal_id');
        $this->assertNotNull($journal);
        $this->assertDatabaseHas('journal_entries', ['journal_id' => $journal, 'status' => 'draft', 'total_debit' => 200, 'total_credit' => 200]);
        $this->assertEquals(200, DB::table('journal_details')->where('journal_id', $journal)->sum('debit'));
        $this->assertEquals(200, DB::table('journal_details')->where('journal_id', $journal)->sum('credit'));
    }

    public function test_staff_settings_and_payment_controls_are_not_available_to_customers(): void
    {
        $this->actingAs(User::factory()->withoutTwoFactor()->create());
        Livewire::test(SettingsPage::class)->assertForbidden();
        Livewire::test(OrderPaymentDesk::class)->assertForbidden();
    }

    public function test_settings_cannot_enable_incomplete_delivery_rules(): void
    {
        $this->actingAs(User::factory()->withoutTwoFactor()->create(['role' => 'admin']));
        Livewire::test(SettingsPage::class)->set('rulesEnabled', true)->call('save')->assertHasErrors('rulesEnabled');
        $this->assertFalse(app(CommerceSettings::class)->get()['rules_enabled']);
    }

    public function test_order_flows_from_checkout_through_delivery_payment_receipt_and_reorder(): void
    {
        $shopper = User::factory()->withoutTwoFactor()->create();
        $order = $this->order($shopper);
        $product = DB::table('order_items')->where('order_id', $order->order_id)->value('product_id');
        $this->assertSame(18, (int) DB::table('products')->where('product_id', $product)->value('stock_quantity'));
        $admin = User::factory()->withoutTwoFactor()->create(['role' => 'admin']);
        foreach (['confirmed', 'processing', 'shipped'] as $status) app(OrderWorkflow::class)->advance($admin, $order->order_id, $status);
        $this->assertDatabaseHas('sales_orders', ['order_id' => $order->order_id, 'status' => 'delivered', 'payment_status' => 'pending']);
        $receipt = app(OrderPayments::class)->record($admin, $order->order_id, 20000, (string) Str::uuid(), 'Synthetic end to end cash receipt');
        $this->assertDatabaseHas('sales_orders', ['order_id' => $order->order_id, 'payment_status' => 'paid']);
        $this->actingAs($shopper)->get(route('customer.orders.show', $order->order_id))->assertOk()->assertSee('Delivered')->assertSee('200.00');
        $this->get(route('customer.orders.receipt', [$order->order_id, $receipt]))->assertOk()->assertSee($order->order_number);
        app(CustomerOrderAccess::class)->reorder($order->order_id);
        $this->assertSame(20000, app(StorefrontCart::class)->summary()['total']);
        $this->assertSame(18, (int) DB::table('products')->where('product_id', $product)->value('stock_quantity'));
    }
}

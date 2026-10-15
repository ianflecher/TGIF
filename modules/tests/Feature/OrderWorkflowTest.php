<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = 'shipped'): int
    {
        $customer = DB::table('customers')->insertGetId(['first_name' => 'Jane', 'last_name' => 'Customer', 'email' => 'jane@example.com', 'date_registered' => '2026-01-01']);
        return DB::table('sales_orders')->insertGetId([
            'customer_id' => $customer, 'order_number' => 'ORD-TEST', 'order_date' => '2026-01-01',
            'status' => $status, 'payment_status' => 'pending', 'grand_total' => 250,
        ]);
    }

    public function test_delivery_never_fabricates_payment_and_records_an_audit(): void
    {
        $admin = User::factory()->withoutTwoFactor()->create(['role' => 'admin']);
        $orderId = $this->order();
        app(OrderWorkflow::class)->advance($admin, $orderId, 'shipped');
        $this->assertDatabaseHas('sales_orders', ['order_id' => $orderId, 'status' => 'delivered', 'payment_status' => 'pending', 'payment_method' => null]);
        $this->assertDatabaseHas('audit_logs', ['record_id' => $orderId, 'table_name' => 'sales_orders', 'user_id' => $admin->getKey()]);
    }

    public function test_stale_submissions_do_not_advance_an_order_twice(): void
    {
        $admin = User::factory()->withoutTwoFactor()->create(['role' => 'admin']);
        $orderId = $this->order('confirmed');
        app(OrderWorkflow::class)->advance($admin, $orderId, 'confirmed');
        try {
            app(OrderWorkflow::class)->advance($admin, $orderId, 'confirmed');
            $this->fail('Expected stale order rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order', $exception->errors());
        }
        $this->assertDatabaseHas('sales_orders', ['order_id' => $orderId, 'status' => 'processing']);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_terminal_orders_cannot_advance(): void
    {
        $admin = User::factory()->withoutTwoFactor()->create(['role' => 'admin']);
        $orderId = $this->order('cancelled');
        $this->expectException(ValidationException::class);
        app(OrderWorkflow::class)->advance($admin, $orderId, 'cancelled');
    }

    public function test_customer_cannot_invoke_order_management_actions(): void
    {
        $this->actingAs(User::factory()->withoutTwoFactor()->create());
        Volt::test('ecommerce.orders')->assertForbidden();
    }

    public function test_order_search_works_on_the_test_database(): void
    {
        $this->actingAs(User::factory()->withoutTwoFactor()->create(['role' => 'admin']));
        $this->order('confirmed');
        Volt::test('ecommerce.orders')->assertSee('ORD-TEST')
            ->set('search', 'not-a-match')->assertDontSee('ORD-TEST')->assertSee('No orders match your filters')
            ->call('clearFilters')->assertSee('ORD-TEST')
            ->call('viewOrder', 1)->assertSee('Review order details')
            ->call('advanceOrder')->assertHasNoErrors();
        $this->assertDatabaseHas('sales_orders', ['order_id' => 1, 'status' => 'processing']);
    }

    public function test_review_preserves_the_ordered_product_name_after_a_catalog_rename(): void
    {
        $this->actingAs(User::factory()->withoutTwoFactor()->create(['role' => 'admin']));
        $orderId = $this->order();
        $product = DB::table('products')->insertGetId(['product_name' => 'Renamed catalog product', 'slug' => 'renamed-product', 'price' => 125]);
        DB::table('order_items')->insert([
            'order_id' => $orderId, 'product_id' => $product, 'product_name' => 'Original ordered product',
            'quantity' => 2, 'price_per_unit' => 125, 'subtotal' => 250, 'total' => 250,
        ]);
        Volt::test('ecommerce.orders')->call('viewOrder', $orderId)
            ->assertSee('Original ordered product')->assertDontSee('Renamed catalog product');
    }
}

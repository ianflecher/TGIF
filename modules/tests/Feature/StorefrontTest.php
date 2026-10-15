<?php

namespace Tests\Feature;

use App\Livewire\Storefront;
use App\Models\User;
use App\Services\StorefrontCart;
use App\Services\StorefrontCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $changes = []): int
    {
        return DB::table('products')->insertGetId($changes + [
            'product_name' => 'Test Burger', 'slug' => 'test-burger', 'price' => 100,
            'description' => 'A delicious test meal', 'status' => 'published', 'stock_quantity' => 5,
            'category' => 'Burgers', 'attributes' => json_encode(['sizes' => ['Large'], 'flavors' => ['Cheese']]),
        ]);
    }

    public function test_search_never_exposes_unpublished_products(): void
    {
        $this->product(['status' => 'draft', 'description' => 'needle']);
        $this->assertSame(0, app(StorefrontCatalog::class)->query('needle')->count());
        $this->get('/products')->assertOk()->assertDontSee('Test Burger');
    }

    public function test_guest_can_customize_a_bag_without_deducting_stock(): void
    {
        $id = $this->product();
        Livewire::test(Storefront::class)->call('openProduct', $id)
            ->set('quantity', 2)->set('options.size', 'Large')->call('addToBag')->assertHasNoErrors()->assertDispatched('bag-updated');
        $summary = app(StorefrontCart::class)->summary();
        $this->assertSame(2, $summary['count']);
        $this->assertSame(20200, $summary['total']);
        $this->assertDatabaseHas('products', ['product_id' => $id, 'stock_quantity' => 5]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_unknown_options_are_rejected(): void
    {
        $id = $this->product();
        Livewire::test(Storefront::class)->call('openProduct', $id)->set('options.size', 'FREE UPGRADE')
            ->call('addToBag')->assertHasErrors('options.size');
        $this->assertSame(0, app(StorefrontCart::class)->summary()['count']);
    }

    public function test_combined_variant_quantities_cannot_exceed_stock(): void
    {
        $id = $this->product();
        app(StorefrontCart::class)->add($id, 3);
        $this->expectException(ValidationException::class);
        app(StorefrontCart::class)->add($id, 3, ['size' => 'Large']);
    }

    public function test_guest_bag_merges_once_after_customer_sign_in(): void
    {
        $id = $this->product();
        app(StorefrontCart::class)->add($id, 2, ['flavor' => 'Cheese']);
        $customer = User::factory()->withoutTwoFactor()->create();
        $this->actingAs($customer);
        $this->assertSame(2, app(StorefrontCart::class)->summary()['count']);
        $this->assertSame(2, app(StorefrontCart::class)->summary()['count']);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['user_id' => $customer->getKey(), 'quantity' => 2, 'unit_price' => 101]);
        $this->assertDatabaseHas('products', ['product_id' => $id, 'stock_quantity' => 5]);
    }

    public function test_customer_cannot_remove_another_customers_cart_item(): void
    {
        $id = $this->product();
        $first = User::factory()->withoutTwoFactor()->create();
        $this->actingAs($first);
        app(StorefrontCart::class)->add($id);
        $line = DB::table('cart_items')->first();
        $this->actingAs(User::factory()->withoutTwoFactor()->create());
        app(StorefrontCart::class)->remove('db-'.$line->id);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_checkout_reprices_from_catalog_instead_of_stale_cart_values(): void
    {
        $id = $this->product();
        $this->actingAs(User::factory()->withoutTwoFactor()->create());
        app(StorefrontCart::class)->add($id, 2);
        DB::table('cart_items')->update(['unit_price' => 1, 'total_price' => 2]);
        DB::table('products')->where('product_id', $id)->update(['price' => 120]);
        app(StorefrontCart::class)->reprice();
        $this->assertDatabaseHas('cart_items', ['unit_price' => 120, 'total_price' => 240]);
    }

    public function test_bag_prevents_checkout_after_an_item_is_unpublished(): void
    {
        $id = $this->product();
        app(StorefrontCart::class)->add($id);
        DB::table('products')->where('product_id', $id)->update(['status' => 'archived']);
        $this->assertFalse(app(StorefrontCart::class)->summary()['canCheckout']);
    }

    public function test_checkout_places_one_order_and_deducts_stock_only_once(): void
    {
        $id = $this->product();
        $this->actingAs(User::factory()->withoutTwoFactor()->create());
        app(StorefrontCart::class)->add($id, 2, ['size' => 'Large']);
        $checkout = Volt::test('customer.checkout')
            ->set('name', 'Test Customer')->set('phone', '09171234567')->set('address', '123 Test Street, Test City')->set('notes', 'No onions please')
            ->call('placeOrder')->assertHasNoErrors();
        $this->assertDatabaseCount('sales_orders', 1);
        $this->assertDatabaseHas('sales_orders', ['grand_total' => 202, 'status' => 'confirmed', 'payment_status' => 'pending', 'customer_notes' => 'No onions please']);
        $this->assertDatabaseHas('products', ['product_id' => $id, 'stock_quantity' => 3]);
        $this->assertDatabaseHas('order_items', ['product_id' => $id, 'size' => 'Large', 'quantity' => 2]);
        $this->assertDatabaseCount('cart_items', 0);
        $checkout->call('placeOrder');
        $this->assertDatabaseCount('sales_orders', 1);
        $this->assertDatabaseHas('products', ['product_id' => $id, 'stock_quantity' => 3]);
    }

    public function test_customer_sign_in_returns_to_checkout_and_preserves_the_guest_bag(): void
    {
        $id = $this->product();
        app(StorefrontCart::class)->add($id);
        $user = User::factory()->withoutTwoFactor()->create();
        DB::table('customers')->insert(['first_name' => 'Test', 'last_name' => 'Customer', 'email' => $user->email, 'user_id' => $user->getKey(), 'date_registered' => now()->toDateString()]);
        session(['url.intended' => route('customer.checkout')]);
        Volt::test('auth.login')->set('username', $user->username)->set('password', 'password')
            ->call('login')->assertHasNoErrors()->assertRedirect(route('customer.checkout'));
        $this->assertSame(1, app(StorefrontCart::class)->summary()['count']);
        $this->assertDatabaseHas('cart_items', ['user_id' => $user->getKey(), 'product_id' => $id, 'quantity' => 1]);
    }

    public function test_customer_registration_accepts_international_mobile_numbers(): void
    {
        Volt::test('auth.register')->set('full_name', 'New Customer With Surname')->set('username', 'new-customer')
            ->set('email', 'new-customer@example.test')->set('phone', '+639171234567')
            ->set('password', 'password123')->set('password_confirmation', 'password123')->call('register')->assertHasNoErrors();
        $this->assertDatabaseHas('customers', ['email' => 'new-customer@example.test', 'phone' => '639171234567', 'last_name' => 'Customer With Surname']);
    }
}

# TGIF storefront

The new storefront uses the supplied food photos and the existing `modules/public/TGIF.png` logo. The ordering header and menu browsing were informed by the supplied [Chowking ordering reference](https://order.chowking.ph/), with TGIF's own green and cream styling.

## Pages and interactions

- `/`: photo-led homepage, live product menu, category/search/sort controls, editorial sections and brand story.
- `/products`: dedicated menu. `/products/{product}` opens a published product's customization dialog.
- Shared product dialog, guest/customer bag drawer and saved delivery address across the storefront.
- `/cart`: customer cart review with quantities and current catalog prices.
- `/checkout`: matching checkout design, delivery details, customer notes, cash-on-delivery summary and confirmation.
- Guest bags survive customer sign-in. Sign-in/registration honors the intended destination. Account navigation includes profile, orders and sign-out; recovery and registration links point to real routes.

## Catalog and pricing

Actual products come from the existing `products` table; draft/archived products never appear through search. Categories support both `products.category` and category-pivot associations. Existing stored product image paths are supported alongside `images/storefront/...` public assets. Products without an image use the TGIF logo.

Sizes, flavors and varieties use the existing product `attributes` values. Invalid selections are rejected. The existing optional-selection rule is preserved and disclosed: each selected option adds 1% of the base price. Prices are computed in integer centavos, then stored in the existing decimal columns. The new bag does not add the old cart's inconsistent 8% tax and delivery surcharge; its total matches checkout. Real tax, delivery fees and coverage must be configured from approved business rules before enabling those charges.

There is one cart service for guest sessions and customer database carts. Adding/removing items does not deduct/restore inventory. Quantity limits account for all variants of a product. Checkout reloads the user's cart, validates availability, reprices from the current catalog, locks the customer's cart workflow, and deducts stock when placing the order. Retried submissions cannot place the same emptied cart twice. Payments remain pending until actually recorded. These changes do not reconstruct inventory previously affected by the legacy cart logic.

## Local preview and existing databases

Open `start.bat` from the repository root, then visit [the storefront](http://127.0.0.1:8075/).

When no `.env` exists, the launcher uses the isolated temporary SQLite preview and runs `StorefrontDemoSeeder`. Its eight new sample products and prices are **illustrative**, chosen to demonstrate the supplied photos; they are not approved business menu data. The demo seeder is restricted to local/testing environments and inserts only missing `demo-` product slugs. It does not replace existing catalog records. If `.env` exists, the launcher uses that configuration and does not seed demo products.

Existing configured databases need the new nullable customer-notes field:

```powershell
cd modules
php artisan migrate
```

This migration was applied only to the isolated preview and in-memory tests during development. Customer notes now persist on orders and appear in the admin order review.

## Implementation

- `app/Livewire/Storefront.php`: live menu, sorting, filters and customization.
- `app/Livewire/StorefrontBag.php` and `StorefrontCartPage.php`: bag/address/cart interactions.
- `app/Services/StorefrontCatalog.php` and `StorefrontCart.php`: published catalog queries, validated options, server pricing and cart persistence.
- `resources/views/components/layouts/storefront.blade.php` and `resources/css/storefront.css`: shared responsive design.
- The old Volt landing/menu/cart entry points redirect to the new routed components, eliminating duplicate legacy cart logic.

## Verification

**59 PHPUnit tests passed, 168 assertions.** Added coverage for draft search leakage, validated options, guest bags, stock behavior, combined variant quantity limits, cart ownership, guest-to-customer merge, current catalog prices, archived items, sign-in redirect, international mobile registration and complete/repeated checkout with persisted notes.

Vite build, Blade compilation, PHP syntax checks and `git diff --check` pass. Browser checks covered desktop/mobile layout, product dialog, guest bag, sign-in and restored checkout. The preview uses synthetic customer data. Production delivery integrations, approved menu prices, shipping/tax rules, online payment integration and complete MySQL/concurrent-load qualification remain separate deployment work.

## Commerce operations (items 2–6)

Added configurable fulfillment rules at `/admin/storefront-settings`: delivery postal-code zones, fees, minimum orders, weekly/overnight hours, pickup address, scheduling and preparation lead time. The real operating values remain unconfigured and rules remain disabled at the owner's request. Existing delivery checkout therefore retains its zero-fee behavior until approved rules are configured. Online payment checkout remains disabled; `HostedPaymentGateway` is an adapter contract only, not a working provider integration.

Customers can view their own paginated order history and tracking timeline at `/orders`, print private receipts, and repeat an order using current menu prices and availability. Historical product names and options remain snapshots. Pickup uses contextual ready/collected labels for the existing fulfillment statuses. Legacy orders show their known state without inventing earlier events.

Checkout saves form drafts, shows field/offline/loading feedback, rejects changed quotes until reviewed, and protects duplicate submissions with a server-owned token. Staff can record cash already received or refunded at `/finance/payments`; this records evidence and does not move money. Refunds must reference an eligible receipt and cannot exceed its remaining balance. Optional account mappings produce balanced draft journals requiring Finance review; unmapped receipts visibly await a ledger link. These controls do not consolidate every legacy Finance screen or implement stock/accounting compensation for cancellation/returns.

Existing configured databases must run `php artisan migrate` from `modules` before using these pages. Development migrated only the isolated temporary preview database. No real payment credentials, service areas, fees, hours or pickup location were supplied or invented.

Verification: **73 PHPUnit tests passed, 223 assertions**, including fulfillment validation, checkout price changes/retries, customer ownership, reorder rollback, payment replay/overpayment, refund bounds and draft journal balancing. Customer order tracking was visually checked using a synthetic local-preview order.

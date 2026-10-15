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

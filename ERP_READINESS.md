# TGIF ERP readiness and implementation plan

This is a Laravel 12 / Livewire Volt application with department screens and a shared enterprise schema. It is an ERP prototype; the presence of screens does not establish that the complete business process is correct or ready for production.

## Repairs in this change

- Enforce portal access on internal routes and the project Gantt endpoint. Customer, employee, admin and supplier sessions have separate access checks. Inactive employee records created through applicant registration cannot enter staff pages. Managers can enter the employee portal.
- Remove the supplier shared-password bypass. Supplier passwords are hashed and must be provisioned explicitly. Disabled suppliers lose portal access.
- Remove admin credential/password-hash logging, customer session-ID logging and manual password debug checks. Rate-limit custom portal login attempts.
- Add missing remember-token, verification, two-factor and password-reset schema. Exclude soft-deleted users from Eloquent authentication. Adapt Laravel name/id access to full_name/user_id.
- Make Fortify registration create a customer identity and customer record atomically instead of falling through to the employee default role.
- Restore settings routes and repair profile uniqueness validation against user_id. Repair the missing admin layout reference.
- Replace missing-view routes with redirects to existing screens. These redirects are compatibility fallbacks, **not implementations of the missing features**. Product details, customer order details, dedicated customer administration and some admin forms still need real pages.
- Handle missing projects and empty Gantt task/phase dates without dereferencing null.
- Reload checkout cart data before order placement and make stock deduction conditional on sufficient stock. Broader checkout pricing, duplicate-submission and concurrency tests are still required.
- Stop the demo admin seeder from deleting all employees or overwriting existing users. Restrict demo admin creation to local/testing environments.
- Update frontend dependencies and replace the obsolete Excel 1.x dependency, whose API did not match the existing financial statement exporter.

## Local setup

Run commands from `modules`:

```powershell
composer install
Copy-Item .env.example .env # first setup only; do not overwrite existing configuration
php artisan key:generate
# Configure DB_CONNECTION and database credentials in .env first.
php artisan migrate
npm ci
npm run build
php artisan serve
```

For local demo accounts only, set APP_ENV=local and run `php artisan db:seed --class=AdminSeeder`. Demo passwords are public and must never be used in production.

Existing supplier accounts have a null password after migration and cannot sign in until provisioned. Use `php artisan supplier:password SUPPLIER_ID`; it prompts for a password without placing it in command history. Back up existing databases before applying migrations. No existing business database was migrated during this repair.

The application uses explicit local configuration rather than merging framework defaults: the installed framework database defaults reference the PHP 8.4 Pdo\\Mysql class while this environment runs PHP 8.2. Local view/hashing configuration and facade aliases are included for that setup.

## Work needed for a complete ERP

| Priority | Area | Required outcome / acceptance check |
| --- | --- | --- |
| 1 | Permissions | Replace username-based department routing with roles and permissions. Scope every Livewire mutation and record lookup by permission and ownership. Verify denied actions through Livewire requests as well as page loads. Admins currently retain access to every department. |
| 1 | Sales and inventory | Central transaction service for cart pricing, stock reservations, fulfillment, returns and cancellations. Server computes option prices; concurrent orders cannot oversell; retries cannot duplicate orders or stock moves. |
| 1 | Accounting | Validate balanced journal entries, enforce posting/period locks, support reversals and reconcile sales, purchasing, inventory and payroll with the ledger. Independently test monetary rounding and report totals. |
| 1 | Procurement | Requisition → approval → purchase order → partial goods receipt → supplier invoice → payment, with quantity/value tolerances and three-way matching. |
| 1 | Security | Complete review of client-writable Livewire properties, public action methods, uploads, account recovery and session handling. Custom login flows still need unified Fortify two-factor enforcement. Review existing logs for previously exposed credentials and rotate affected passwords. |
| 2 | HR | Separate applicant identity from employment; hiring must explicitly activate employment. Define attendance corrections, leave balances and payroll approval/locking. Configure payroll rules for the intended jurisdiction before real use. |
| 2 | Warehouses | Consistent stock ledger by warehouse/bin, units, batches and expiry; atomic transfers, counts and adjustments; reconciliation against product stock. |
| 2 | Projects | Define budget versioning, approval authority, task dependencies and actual cost posting. Test empty plans, deleted resources and concurrent budget requests. |
| 2 | Supplier/customer portals | Real detail/edit pages with ownership policies, supplier password recovery, invitation flow and audit trail. Replace compatibility redirects with implemented screens. |
| 2 | Audit | Persist actor, action, entity, before/after values and correlation ID for critical transitions. Protect audit records from ordinary updates/deletes. |
| 3 | Operations | Deployment pipeline, production DB migration/rollback tests, encrypted backups with restore drills, queue supervision, monitoring and mail/storage configuration. |
| 3 | Reporting | Define KPI source records and accounting periods. Reconcile exports to ledger data; verify export formatting, dates and numeric cell values. |

## Delivery sequence

1. Establish permissions and automated cross-role/record-ownership tests.
2. Complete one vertical workflow: purchasing → receipt → warehouse stock → sale → fulfillment → payment → journal/report reconciliation.
3. Add cancellation, partial completion, returns, concurrent submissions and reversal tests for that workflow.
4. Complete HR/payroll and project costing with explicit approval policies.
5. Replace remaining placeholder routes, run user acceptance tests by department, and qualify deployment/backups before release.

Business decisions still needed: company/branch structure, user permission matrix, approval amounts, warehouse model, tax/currency rules, payroll jurisdiction, financial periods and whether multiple companies must be isolated. These should be recorded before implementing the remaining business rules.

## Verification (2026-10-15)

- `php vendor/phpunit/phpunit/phpunit`: **39 tests passed, 109 assertions**, including new portal-access, supplier-credential and non-destructive-seeding regressions. The suite uses an isolated in-memory SQLite database and runs the migrations.
- `npm run build`: passed.
- `php artisan view:cache`: passed for Blade templates.
- PHP syntax checks: 80 PHP files passed.
- `composer validate --no-check-publish`: passed.
- `composer audit --format=summary` and `npm audit --audit-level=low`: no known advisories reported at verification time.
- `git diff --check`: passed.

The Artisan test wrapper reports warnings when it attempts key generation without a local `.env`; direct PHPUnit runs are clean with the test-only APP_KEY in phpunit.xml. Generate a separate application key during local setup; never use the committed test key outside tests.

These checks do not establish complete business correctness. Browser acceptance testing, MySQL migration/rollback testing, real email/file-storage integrations, checkout concurrency, comprehensive record-ownership testing and accounting reconciliation remain outstanding. There is no configured application `.env` or connected business database in this checkout; production readiness is not claimed.

## Design and workflow improvement pass

The requested direction was to keep TGIF's existing green style and simplify it.

- Ten administrative layout variants now use one shared workspace shell, CSS system and navigation configuration (`config/erp.php`). Navigation stays stable instead of shifting the content on hover. Department tabs, current-page indicators, keyboard focus, skip navigation, mobile navigation and a consistent account menu are included. Existing employee, supplier, applicant and storefront portal layouts have not been comprehensively redesigned in this pass.
- Rebuilt the administrative overview with clearly labeled business metrics, recent orders, project progress, replenishment alerts and department work queues. Removed the unverified “all systems operational” claim. Replenishment counts and lists use the same threshold; project completion follows tasks → phases → projects rather than comparing unrelated IDs. Shipped orders remain included in active work.
- Rebuilt the customer order management screen with server-side search, status filters, pagination, empty states and a keyboard-accessible review dialog. Queries work on SQLite as well as using portable query-builder constructs for MySQL. Product names use order snapshots, preserving history after catalog renames.
- Administrative fulfillment transitions follow draft → confirmed → processing → shipped → delivered. Authorization is checked within actions, terminal states are protected, stale submissions cannot advance the order twice, and transitions record an audit entry in the same database transaction. Delivery does not manufacture payment or a cash payment method.
- Removed the order screen's direct “mark paid” and “cancel” shortcuts. Payment entry and cancellation/return compensation need the Finance/stock reconciliation workflow described above. This is a deliberate restriction: those complete workflows are still outstanding, and other legacy order-changing code also needs consolidation into shared services.

Validation after this pass: **48 PHPUnit tests passed, 131 assertions**; Vite build and Blade compilation passed; changed service PHP syntax and whitespace checks passed. Added regression coverage for dashboard thresholds and phase relationships, order search/review, snapshot product names, authorization, stale transitions, terminal states, separate payment status and audit creation.

Browser verification used a separate temporary SQLite preview database with `PREVIEW-` orders and synthetic customer data. Checked populated desktop views, the narrow-screen layout/menu, the review dialog and an actual confirmed → processing transition with unchanged payment. The local preview runs at `http://127.0.0.1:8015/admin`; it is not a deployment or a business database. Full acceptance testing of all legacy department screens and workflows is still required.

## Storefront follow-up

The subsequent ecommerce pass adds the supplied photography, a responsive TGIF storefront, a shared guest/customer cart, matching checkout, persisted customer notes and sign-in continuity. It fixes the legacy add-to-cart stock deduction and the inconsistent cart fees. Current validation is **59 tests / 168 assertions**, with passing frontend build and Blade compilation. See [STOREFRONT.md](STOREFRONT.md) for preview instructions, sample catalog behavior, the required customer-notes migration and remaining production integrations. The Windows launcher now uses port **8075**.

## Commerce operations follow-up

Configurable fulfillment settings, customer tracking/reorder, checkout draft/review protection, manual cash receipt/refund records and private printable receipts are now available. Current automated validation: **73 tests / 223 assertions**. Real operating rules and online payment activation remain deferred at the owner's request. Optional payment accounting mappings create draft journals for Finance review; cancellation/return compensation and consolidation of legacy finance mutations remain outstanding. See STOREFRONT.md for migration and operating details.

## Start-to-finish audit, 15 October 2026

The latest audit repairs department page crashes, support-note privacy, validated customer-profile writes, journal line validation, custom-login two-factor enforcement and persistent Livewire portal checks. Final verification: **80 tests / 257 assertions**, plus passing build and Blade compilation. Read [END_TO_END_AUDIT.md](END_TO_END_AUDIT.md) for exact evidence, limits and remaining production blockers; passing page tests does not certify the complete ERP.

Supplier follow-up: purchase-order ownership, issued-only acceptance, independent dispatch reporting, account disabling and admin-only supplier management are covered by new tests. Latest full suite: **89 tests / 332 assertions**. Run migrations for the new supplier dispatch/audit fields before using an existing configured database. See END_TO_END_AUDIT.md for scope and remaining procure-to-pay work.

## Audit controls

The four selected main-audit priorities now have implemented controls. See [AUDIT_CONTROLS.md](AUDIT_CONTROLS.md) for private document migration, module permissions, cancellation/return accounting, financial periods, independent approval/posting, reversal and reporting behavior. Existing configured databases must migrate before using the controls. No real tax policy, periods, gateway credentials or staff grants were invented.

Audit-control validation: **105 tests / 418 assertions**; build, Blade, PHP syntax and whitespace checks passed. SQLite forward/rollback/reapply checks passed for the new control migrations.

## Supplier purchasing cycle

Supplier invoice submission, exact three-way matching, independently approved partial payments and linked draft journals are implemented. See [SUPPLIER_PAYABLES.md](SUPPLIER_PAYABLES.md) for operating steps, installation and limits. Production MySQL and business accounting policy qualification remain outstanding.

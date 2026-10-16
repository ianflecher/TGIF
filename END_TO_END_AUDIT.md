# TGIF start-to-finish audit — 15 October 2026

## Result

The app passes the checks listed below after repairs. It is **not yet qualified as a production-ready full ERP**. Page rendering, workflow tests, and complete business acceptance are different levels of evidence.

All development checks used in-memory SQLite or the isolated temporary local preview, with synthetic identities/orders. No business database was migrated or rewritten. Delivery operating settings and online payment activation remain deferred as requested.

## What was checked

| Area | Evidence and boundary |
| --- | --- |
| Startup | Reviewed start.bat checks, port 8075, stable preview key, isolated database, local-only seeding, and existing-.env path. No clean-machine installation or production deployment performed. |
| Authentication | Existing registration/reset/profile/2FA tests; added custom admin login → Fortify recovery challenge → successful login. Browser demo admin login succeeded. |
| Permissions | Existing guest/cross-role tests, static page rendering across admin/customer/employee/applicant/supplier, and an actual /livewire/update request denied after role revocation. This is not a method-by-method permission audit of every legacy component. |
| Storefront | Existing catalog/cart/stock/option/checkout tests; browser inspection from earlier storefront work. |
| Complete order path | Added synthetic checkout → stock deduction → processing → shipped → delivered → cash receipt → customer receipt/tracking → reorder test. Delivery preserves unpaid status until receipt; reorder does not deduct stock. |
| Fulfillment and cash | Existing coverage for zones/hours/scheduling, changed quotes, replay, overpayment, refund limits and balanced draft mapping. No real gateway or real funds transfer. |
| Department screens | Every static GET route with one of the five portal middleware values rendered or redirected successfully on a minimal test database. Dynamic record routes and every department mutation were not exhaustively exercised. |
| Browser | Local admin overview, repaired Sales dashboard and Reporting with synthetic preview records visibly rendered. |
| Build | Vite production build, compiled Blade templates, PHP syntax scan and whitespace validation. |

## Repairs made during this audit

- Replaced MySQL-only date/time/name expressions in Sales, HR, Reporting and employee pages with supported query-builder/PHP operations or explicit SQLite/MySQL expressions. Repaired attendance table checks, payroll year lists and ticket priority sorting.
- Fixed support dashboard at-risk customer counts and removed order/ticket join multiplication from customer spending. Fixed fractional resolution percentages and stopped counting newly opened, unanswered tickets as already breaching the 24-hour SLA.
- Removed a nonexistent applicant-jobs link that crashed the empty applicant page; it now gives an honest HR-contact message.
- Customer support excludes internal staff notes. Replies reload the ticket under the current customer and check its current status, rejecting stale closed tickets.
- Customer profile updates reload ownership from authentication and persist only validated fields, ignoring injected form keys.
- Manual journal save revalidates every account and monetary line, rejects inactive accounts, negative/zero-sided/two-sided lines, and compares balance in centavos. This does not implement full posting approvals or closed-period locks.
- Custom customer/admin/employee/applicant login forms now honor confirmed Fortify two-factor enrollment before establishing an authenticated session. Demo credential hints only render in local/testing environments.
- Registered the portal middleware as persistent Livewire middleware so changed roles, deleted users and inactive employment/supplier access are rechecked on subsequent browser actions.

## Remaining work, in priority order

1. **Private applicant documents:** applicant uploads still use the public disk and direct storage URLs (`applicant/index.blade.php`). Move to private storage with authenticated owner/HR downloads, migrate existing files safely, and verify deployment storage exposure. No existing files were moved in this audit.
2. **Full mutation/ownership audit:** legacy staff, HR, inventory, procurement and project components still rely on public component state in places. Persistent portal checks protect the role boundary, but record ownership, allowed columns and approval rights need individual action tests. Admin roles currently retain broad department access.
3. **Cancellation/return reconciliation:** implement approved stock, payment and accounting compensation together, with partial returns and replay protection. Do not add direct status shortcuts.
4. **Accounting qualification:** define financial periods, approval/posting/reversal controls, revenue recognition and tax rules; reconcile reports to ledger sources. The analytics screen currently includes non-cancelled order value under revenue/spending labels and uses its own stock threshold. Its totals are not certified financial statements. Cash mapping currently creates draft journals requiring Finance review.
5. **Procurement, warehouse and HR acceptance:** exercise partial receipt/invoice/payment matching, units/transfers/counts, leave/attendance/payroll approval, and project budgeting with representative records. Rendering these screens does not prove those chains are complete. The Sales overview currently displays customer-service metrics.
6. **Production operations:** qualify MySQL migrations/rollback and concurrent stock ordering, configure approved mail/queue/storage/payment services, replace demo credentials, test backup restoration and deploy with production secrets/debug settings. No live services or load tests were used here.

See ERP_READINESS.md and STOREFRONT.md for the earlier implementation scope and operating instructions.

## Final verification

**80 PHPUnit tests passed, 257 assertions.** Vite build, Blade compilation, PHP syntax checks and `git diff --check` passed. The final suite includes the portal rendering sweep and a real Livewire update request after role revocation. Browser evidence: `audit-reporting.jpg` in this chat's visualization output directory.

## Supplier-specific follow-up

The supplier portal received a separate functional/security review after the general page sweep. Fixed cross-supplier order lookup and mutations; all dashboard actions derive the supplier from the active server session. Internal drafts are hidden. Acceptance is limited to sent orders, with replay-safe handling and transactional supplier audit events. Dispatch is stored separately in `supplier_dispatched_at` and leaves purchase-order receipt status and inventory unchanged; procurement can see the supplier dispatch notice. Warehouse staff must independently verify received quantities.

The `/supplier/list` page is internal supplier/inventory/warehouse administration and now requires an admin identity on both page requests and component lifecycle. Supplier lists exclude password hashes. Management validates supplier email uniqueness and prevents deleting suppliers with purchase-order history. Removed hardcoded/default password UI and the personal test-login helper, corrected the supplier header identity, removed nonexistent mobile links, and prevented duplicate PO lines caused by joining multiple catalog products to one inventory item.

Added SupplierWorkflowTest covering owned issued orders, foreign/draft lookups, foreign mutations, terminal states, replay-safe acceptance/dispatch, suspended suppliers, internal-management denial, login/logout, item totals, unique login email and preservation of linked order history. Browser verification used the isolated synthetic Preview Supplier and PREVIEW-SUPPLIER-AUDIT order.

Existing configured databases require `php artisan migrate` from modules for `2026_10_15_000004_secure_supplier_order_workflow.php`. Applied only to the isolated local preview in development. Supplier passwords remain individually provisioned using the existing `supplier:password` command; there is no universal password.

Limits: this verifies the implemented supplier portal, not a complete procure-to-pay system. Supplier invitations/recovery, invoices and document exchange, quantity-based advance shipment notices, warehouse partial receipt/rejection, three-way invoice matching and payment reconciliation still need their own implementation/acceptance checks. Existing legacy management inventory/transfer mutations were not comprehensively redesigned. Live supplier accounts, real purchase-order acceptance, external notifications and business data were not used during testing.

Supplier follow-up verification: **89 tests passed, 332 assertions** across the complete suite, including nine supplier workflow tests. Blade compilation, changed PHP syntax checks and whitespace checks passed. Frontend assets did not change in this follow-up.

## Main audit priorities 1–4 implemented, 16 October 2026

Private applicant storage/downloads and resumable migration, configurable module/action permissions, transactional cancellation/partial returns and independent financial-period/journal controls are implemented. The old findings under items 1–4 above are historical; [AUDIT_CONTROLS.md](AUDIT_CONTROLS.md) describes their current behavior, installation, evidence and limits. Business tax/revenue-recognition policy and production qualification remain outstanding.

## Supplier payables follow-up, 16 October 2026

Supplier invoices, receipt/PO matching and partial/full payment controls now extend the supplier cycle. See [SUPPLIER_PAYABLES.md](SUPPLIER_PAYABLES.md). Earlier missing-invoice/matching/payment findings are historical; credit notes, statutory tax handling and target-MySQL qualification remain outside this implementation.

Supplier credit notes, physical returns and independent payment corrections/refunds are now implemented with source-bound journals and reconciliation. Final suite: **119 tests / 533 assertions**; new SQLite migration rollback/reapply, Vite and Blade checks passed. Earlier missing-credit-note findings are historical. See SUPPLIER_PAYABLES.md for limits.

Department portals follow-up: guest department requests now use their department login; explicit staff grants are enforced on routes, Livewire and services. Applicant/active employee entry is separated, Finance mappings are hidden from store-only staff, and supplier/web login identities are cleared when switching. See DEPARTMENT_PORTALS.md for deployment and verification details.

# Audit controls — 16 October 2026

Implemented the four selected main-audit priorities. These controls are usable locally; they do not certify the entire ERP or invent tax/revenue-recognition policies.

## 1. Private applicant documents

New files use the dedicated `applicant_private` disk, outside the public storage link, with authenticated owner/HR downloads and no-store responses. Ownership includes both the document and its application. HR readers require HR module access. Applicant removal archives the row and preserves the file; authorized HR staff can restore it from the application's document list.

Existing document rows retain their original `public` disk until explicitly migrated. The resumable migration command copies, verifies SHA-256, removes public exposure, then repoints the row. A verified-copy checkpoint supports recovery if interrupted. It refuses conflicting private files or invalid paths. The workspace contained no legacy public applicant files, and the isolated preview had no files to move.

From `modules`, after database migration:

```powershell
php artisan applicants:privatize            # dry run
php artisan applicants:privatize --execute  # copy, verify, remove public copies, repoint
```

Apache's public .htaccess denies the old applicant-document storage URL. For Nginx, add a matching deny location for `/storage/application_documents/` before exposing an existing deployment. Complete the file migration before serving a deployment through a static server that does not honor .htaccess. No existing business database or external file store was changed during development.

## 2. Permissions and mutation boundaries

Open `/admin/access` as an unrestricted administrator. Load a staff account, choose permissions and save. Existing administrators with NULL module permissions retain their previous full access. An empty permission matrix denies administrative modules; explicit grants separate read, write and approval. Interactive forms generally require write in addition to any approval required by the action. Restricted accounts cannot change the permission matrix or grant roles. Staff accounts keep their original role. Explicit department grants now enable active employees/managers to enter assigned department portals, including Finance/HR when deliberately granted. The global dashboard and access editor remain full-administrator-only; see DEPARTMENT_PORTALS.md.

Permissions are checked against current database state on page loads, Livewire mount/hydration, property updates and action calls, with service-level checks for critical workflows. Navigation and post-login landing pages follow granted modules rather than usernames. Existing full administrators were not silently restricted. Newly promoted administrators start with no module grants until explicitly configured by an unrestricted administrator.

Employee identities and selected sensitive records are locked against client replacement. Current ticket assignment, task assignment/manager ownership, active employment and supplier ownership are rechecked. Ticket transitions and self-service clock entries use shared transactional services. Progress at 100% submits work for manager review rather than approving it. Leave days are recomputed on submission. HR hiring activation requires HR approval, and role changes require unrestricted identity administration. Project-created accounts no longer receive a shared default password.

Warehouse receiving requires inventory permission and uses server-owned PO lines, remaining accepted quantities, quality status, idempotency keys and stock movements. Failed-quality receipts do not increase stock or complete an order. The old Procurement shortcut now opens warehouse receiving instead of fabricating a completed receipt or finished-product stock. Use `/inventory/receiving` for administrators or `/employee/warehouse` for explicitly authorized staff.

This defines module-wide administration plus own-record staff policies. It is not a configurable branch/company/warehouse-row tenancy model, and it does not validate every business calculation in every legacy department screen.

## 3. Cancellation and partial returns

Open `/ecommerce/adjustments` with ecommerce read/write/approve permission. Select an order, select cancel-before-shipping or return-after-delivery, enter quantities, safe-to-restock quantities and a reason. Cancellation must include all lines and credits the remaining delivery fee. Returns support partial quantities and an explicit bounded delivery-fee credit. Unselected return lines use zero quantity. Units not explicitly restocked remain consumed/disposed.

Adjustments preserve the original order and totals, prevent over-returns and replay, record product stock changes, snapshot cost compensation, customer timeline events, audit history and credit/refund obligations in one transaction. Stock restoration does not invent ingredient/recipe stock moves. New checkout lines snapshot their original unit cost. Legacy orders without trustworthy checkout/cost/sales-journal provenance fail with a Finance-reconciliation message rather than guessing stock or cost.

Unposted full cancellations void the original draft sale; disposed units produce a cost draft where needed. Posted sales receive credit and cost-compensation drafts. Partial-return drafts cannot post before the original sale. A previously reversed sale requires Finance reconciliation before another operational credit.

The payment desk shows approved credits and the remaining cash refund due. Cash already returned must be recorded against an eligible receipt, within both its remaining refundable balance and the approved adjustment credit. Recording an adjustment does not transfer money, invent a refund or mark an unpaid order paid. Adjustment refunds clear the original sales receivable rather than reducing revenue twice. Existing goodwill refunds without an adjustment retain their configured expense/returns mapping.

## 4. Accounting controls and reports

Open `/finance/controls`. Create nonoverlapping financial periods using approved dates. All new manual and legacy automatic journals are drafts. Drafts do not affect ledger reports. Submit, obtain approval from a different administrator, then post into an open period. Closing a period blocks further posting to that date range. Corrections use a distinct reversal draft in an appropriate open period, with independent approval; original posted records remain unchanged. Submitted journals can be returned for review with an audited reason, clearing approval before edits.

Journal validation checks positive one-sided monetary lines, active accounts, exact centavo balance and header/detail agreement. SQLite/MySQL database triggers prohibit direct posted insertion, submitted-detail/header edits, and posted edits/deletion through legacy SQL. New project planning changes no longer rewrite posted financial entries. Changing classifications of accounts already used by posted journals is blocked in the account editor.

Canonical totals use posted journal details and accounting dates, not order/payment status or record creation time. Trial balance, income statement and balance sheet queries exclude draft/out-of-period details and preserve inactive-account history. Balance sheets carry cumulative balances through the report date and include unclosed profit/loss. Reversals and return credits net correctly. Operational order values/commitments are labeled separately; completed POs do not manufacture supplier payments. Linked ecommerce orders are excluded from duplicate platform totals.

Cash/receivable mappings must agree with the original sale's receivable account. Unmapped cash records remain visibly awaiting ledger reconciliation. No financial periods, tax rates, statutory payroll rules, or live gateway settings were invented or activated. Finance approval records the business accounting decision; an approved revenue-recognition/tax policy still must be supplied before production accounting acceptance. Legacy payroll deduction formulas remain unqualified for statutory use.

## Installation and evidence

Configured databases require the two new migrations before using these controls:

```powershell
cd modules
php artisan migrate
php artisan applicants:privatize
```

Development applied migrations only to isolated temporary SQLite databases. Forward migration, rollback of the two control migrations, and reapplication passed in a separate empty SQLite database. MySQL trigger syntax is supplied, but execution, production migration/backup restoration and concurrent-load qualification on MySQL still require a deployment test environment.

The test suite covers private upload/download/archive/restore and migration, role/module boundaries, warehouse replay/quality/limits, staff ticket reassignment, clock replay, exact journal balance, independent approval, periods, immutability, reversals, report-date/draft exclusion, cancellation, partial returns and a complete sale → receipt → return → cash refund → posted-ledger reconciliation.

Final validation on 16 October 2026: **105 PHPUnit tests passed, 418 assertions**. The Vite build, Blade compilation, 92 PHP-file syntax checks and whitespace checks passed. The control migrations also passed SQLite forward/rollback/reapply testing in a separate temporary database. The isolated preview is running on port 8075; production database and statutory policy qualification remain as described above.

Browser verification completed for the finance controls, permission editor, and selected-order cancellation/return form using the isolated preview. The finance screenshot is `audit-controls-finance.jpg`; the full adjustment view is `audit-controls-adjustments.jpg` in this chat's visualization directory. Existing posted preview entries are explicitly labeled legacy; no approval history was fabricated, and no periods or staff grants were saved during browser verification.

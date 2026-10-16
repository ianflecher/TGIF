# Supplier purchasing cycle

Implemented 16 October 2026. Finance opens `/finance/supplier-payables`; suppliers open `/supplier/invoices` from their portal navigation. The existing green interface is retained.

## Operating flow

1. Issue a purchase order; the owning supplier accepts it and reports dispatch. Dispatch does not receive stock.
2. Warehouse records passed/failed quantities using Receiving. Only passed goods are available for invoice matching.
3. The supplier submits an invoice with its unique invoice number, dates and billed PO lines. An administrator can enter an invoice on the supplier's behalf with both Finance read/write and Procurement write permissions. Submitted invoices preserve their line values; rejection preserves the record and reason. Corrected invoices use a distinct revision number.
4. Finance selects the invoice to see billed quantity, PO price, accepted quantity, quantities reserved by other approved invoices and any differences. Exact price matching is required. Missing/rejected goods, excess quantities, duplicate inventory lines on a PO and differing prices block approval. Resolve the business discrepancy or reject the invoice; there is no silent tolerance override.
5. A different administrator approves an internally entered invoice. Supplier submissions can be approved by authorized Finance staff. Select an active inventory/expense debit and supplier payable liability account. Approval reserves received quantities and creates a draft journal. Submit, independently approve and post it in Ledger controls before requesting payment.
6. Finance requests a partial or full payment. Pending/approved requests reserve the remaining amount to prevent overlapping overpayments. A different Finance approver approves the request. Cancellation requires a reason and releases an unpaid reservation.
7. After money has actually been paid outside the system, enter its date, unique bank/cash voucher reference and cash/bank asset account, then record it against the approved request. This creates a separate draft payable-debit/cash-credit journal. Independently approve/post it through Ledger controls. Recording is not a bank transfer.

## Controls and accounting boundaries

Supplier ownership and active status are checked on every supplier operation. Finance and Procurement actions have server-side permissions; locked component IDs and rechecked source ownership prevent client replacement. Database transactions lock the PO before invoices and payment requests so approvals serialize against received-goods allocations and invoice balances. Unique request keys and normalized invoice/payment references protect retries and duplicates. Supplier submission events retain supplier identity separately from internal user audit logs.

Operational paid/outstanding values use recorded cash payments; journal status is displayed separately. Drafts do not affect ledger reports. Supplier journal posting checks the source's exact accounts, amount, date and approved/recorded state. Generic reversal is blocked for supplier source journals because a supplier credit/payment correction workflow is required to keep operational and ledger balances consistent. Recorded payments cannot be silently cancelled or deleted.

Amounts are in the existing single-currency ledger. PO prices are matched as supplied; separate VAT/withholding, landed cost, exchange rates, supplier credit notes, invoice attachments/OCR and bank gateway execution are not included. Finance must select accounts according to its approved policy; accepting stock alone creates no new financial journal in this implementation. Failed goods do not become invoiceable. This does not qualify statutory tax handling or inventory accrual policy.

## Installation and verification

Existing configured deployments must run `php artisan migrate` from `modules` to apply `2026_10_16_000007_add_supplier_payables.php`. Back up and test the target deployment before applying migrations. Development migrated only the isolated local SQLite preview and PHPUnit in-memory databases. The new migration passed forward/rollback/reapply on the isolated preview before synthetic invoice data was added.

Automated checks include the complete accepted PO → dispatch → receiving → invoice match → approval → posted invoice → two approved partial payments → posted payment journals. Assertions verify accepted stock, zero supplier payable after settlement, cash movement, draft exclusion, mismatches, double billing, failed goods, overpayment reservations, self-approval rejection, duplicate requests/references, future dates, source-journal tampering, supplier ownership, suspended sessions, direct component permissions and form submissions.

A synthetic local preview invoice (`INV-PREVIEW-001`) demonstrates 30 accepted/billed units at ₱50. No real supplier, business database, bank transfer or production ledger was changed. Production MySQL/concurrency acceptance and business tax/account policies remain deployment work.

Validation: **112 tests passed, 473 assertions** across the complete PHPUnit suite. The final focused supplier suite passed **7 tests / 55 assertions** after the navigation/read-access refinements. Vite production build, Blade compilation and whitespace checks passed. Browser verification confirmed the populated invoice list and three-way match details on the isolated preview.

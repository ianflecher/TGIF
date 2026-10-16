<section class="erp-card" style="padding:20px;margin-top:20px">
    <h2>Credits & supplier returns</h2>
    <p>Credits reduce the original invoice. A physical return also removes available stock. Original receipts and invoice totals remain in the history.</p>
    @if(!$supplierMode)
    <details><summary class="erp-text-link" style="cursor:pointer">Request a credit or physical return</summary>
        <form wire:submit="requestCredit">
            <div class="erp-toolbar">
                <label>Supplier credit note number<input class="erp-input" wire:model="creditNumber" maxlength="100" required></label>
                <label>Credit / return date<input class="erp-input" type="date" wire:model="creditDate" required></label>
            </div>
            <label>Supplier agreement and reason<textarea class="erp-input" wire:model="creditReason" minlength="5" maxlength="1000" required></textarea></label>
            <p>Enter a credit amount for each selected line. Leave returned units at zero for discounts or overbilling. Physical returns require the exact original unit price × returned units, available on-hand stock and Inventory write permission.</p>
            <div class="erp-table-wrap"><table class="erp-table" style="white-space:normal">
                <thead><tr><th>Original item</th><th>Billed units / price</th><th>Credit amount</th><th>Units sent back</th></tr></thead>
                <tbody>@foreach($creditLines as $index => $line)<tr wire:key="credit-line-{{ $invoice->id }}-{{ $index }}">
                    <td>{{ $line['name'] }}</td><td>{{ $line['quantity'] }} × ₱{{ number_format($line['unit_price'],2) }}</td>
                    <td><input class="erp-input" aria-label="Credit amount {{ $line['name'] }}" type="number" min="0" step="0.01" wire:model="creditLines.{{ $index }}.amount"></td>
                    <td><input class="erp-input" aria-label="Returned units {{ $line['name'] }}" type="number" min="0" step="1" wire:model="creditLines.{{ $index }}.return_quantity"></td>
                </tr>@endforeach</tbody>
            </table></div>
            <button class="erp-button" wire:loading.attr="disabled">Request independent credit review</button>
        </form>
    </details>
    @endif
    <div class="erp-table-wrap"><table class="erp-table" style="white-space:normal">
        <thead><tr><th>Credit note</th><th>Date</th><th>Amount</th><th>Status</th><th>Reason / review</th><th>Actions</th></tr></thead>
        <tbody>@forelse($credits as $credit)<tr>
            <td>{{ $credit->number }}@foreach($credit->lines as $line)<p>{{ $line->product_name }} · ₱{{ number_format($line->amount,2) }} credit · {{ $line->return_quantity }} units sent back</p>@endforeach</td><td>{{ $credit->credit_date }}</td><td>₱{{ number_format($credit->amount,2) }}</td>
            <td>{{ ucfirst($credit->status) }}@if(!$supplierMode && $credit->journal_id)<p>Journal #{{ $credit->journal_id }} · {{ $credit->ledger_status }}</p>@endif</td>
            <td>{{ $credit->reason }} @if($credit->review_note)<p>{{ $credit->review_note }}</p>@endif</td>
            <td>@if(!$supplierMode && $credit->status === 'pending')
                <button class="erp-button" wire:click="approveCredit({{ $credit->id }})" wire:confirm="Confirm the supplier credit is agreed and any listed returned units have actually been sent back. Approval removes that stock and creates a journal draft.">Approve credit / return</button>
                <button class="erp-button secondary" wire:click="rejectCredit({{ $credit->id }})">Reject with reason</button>
            @endif</td>
        </tr>@empty<tr><td colspan="6">No credit notes or returns.</td></tr>@endforelse</tbody>
    </table></div>
</section>
<section class="erp-card" style="padding:20px;margin-top:20px">
    <h2>Payment corrections & refunds received</h2>
    <p>Gross payments ₱{{ number_format($balance['gross_paid'],2) }} · Erroneous entries voided ₱{{ number_format($balance['voided'],2) }} · Cash refunded by supplier ₱{{ number_format($balance['refunded'],2) }}</p>
    @if(!$supplierMode)
    <details><summary class="erp-text-link" style="cursor:pointer">Request a payment correction</summary>
        <form wire:submit="requestPaymentCorrection">
            <div class="erp-toolbar">
                <label>Correction type<select class="erp-select" wire:model="correctionKind"><option value="refund">Cash refund received from supplier</option><option value="void">Void erroneous payment entry — no money was sent</option></select></label>
                <label>Original payment<select class="erp-select" wire:model="correctionPayment" required><option value="">Select recorded payment</option>@foreach($payments->where('status','recorded') as $payment)<option value="{{ $payment->id }}">#{{ $payment->id }} · {{ $payment->reference }} · ₱{{ number_format($payment->amount,2) }}</option>@endforeach</select></label>
                <label>Amount<input class="erp-input" type="number" min="0.01" step="0.01" wire:model="correctionAmount" required></label>
                <label>Correction / refund date<input class="erp-input" type="date" wire:model="correctionDate" required></label>
            </div>
            <label>Unique refund bank reference / correction voucher<input class="erp-input" wire:model="correctionReference" maxlength="150" required></label>
            <label>Evidence and reason<textarea class="erp-input" wire:model="correctionReason" minlength="5" maxlength="1000" required></textarea></label>
            <p>Refunds are limited to the supplier credit balance and original payment. Voids correct the full mistaken entry and reopen the invoice balance. Both use the original payment account; approval creates a compensating journal. No money is transferred here.</p>
            <button class="erp-button" wire:loading.attr="disabled">Request independent correction review</button>
        </form>
    </details>
    <label>Reason for rejecting a pending credit or payment correction<input class="erp-input" wire:model="reviewReason" minlength="5" maxlength="1000"></label>
    @endif
    <div class="erp-table-wrap"><table class="erp-table" style="white-space:normal">
        <thead><tr><th>Reference</th><th>Type / payment</th><th>Date</th><th>Amount</th><th>Status</th><th>Evidence / review</th><th>Actions</th></tr></thead>
        <tbody>@forelse($corrections as $correction)<tr>
            <td>{{ $correction->reference }}</td><td>{{ $correction->kind === 'void' ? 'Erroneous entry void' : 'Cash refund received' }} · #{{ $correction->payment_id }}</td>
            <td>{{ $correction->correction_date }}</td><td>₱{{ number_format($correction->amount,2) }}</td>
            <td>{{ ucfirst($correction->status) }}@if(!$supplierMode && $correction->journal_id)<p>Journal #{{ $correction->journal_id }} · {{ $correction->ledger_status }}</p>@endif</td>
            <td>{{ $correction->reason }}@if($correction->review_note)<p>{{ $correction->review_note }}</p>@endif</td>
            <td>@if(!$supplierMode && $correction->status === 'pending')
                <button class="erp-button" wire:click="approvePaymentCorrection({{ $correction->id }})" wire:confirm="Confirm the evidence: a refund means cash was actually received; a void means the original payment never occurred. Approval updates balances and creates a compensating journal.">Approve correction</button>
                <button class="erp-button secondary" wire:click="rejectPaymentCorrection({{ $correction->id }})">Reject with reason</button>
            @endif</td>
        </tr>@empty<tr><td colspan="7">No payment corrections or refunds.</td></tr>@endforelse</tbody>
    </table></div>
</section>
@if(!$supplierMode && $reconciliation)
<section class="erp-card" style="padding:20px;margin-top:20px">
    <h2>Supplier reconciliation</h2>
    <p>Operational payable position ₱{{ number_format($reconciliation['position'],2) }} · Posted payable position ₱{{ number_format($reconciliation['posted_payable'],2) }} · Difference ₱{{ number_format($reconciliation['difference'],2) }}</p>
    <p>{{ $reconciliation['unposted'] }} source journal(s) awaiting posting. Negative payable positions represent money due back from the supplier.</p>
    <p>Expected net cash movement ₱{{ number_format($reconciliation['cash_expected'],2) }} · Posted cash movement ₱{{ number_format($reconciliation['cash_posted'],2) }} · Difference ₱{{ number_format($reconciliation['cash_difference'],2) }}</p>
    <p>Approved returned units {{ $reconciliation['returned'] }} · Recorded stock out {{ $reconciliation['stock_moved'] }} · {{ $reconciliation['stock_matches'] ? 'Stock movements match' : 'Stock discrepancy — review required' }}</p>
    <a class="erp-text-link" href="{{ route('finance.controls') }}">Review and post source journals in Ledger controls</a>
</section>
@endif

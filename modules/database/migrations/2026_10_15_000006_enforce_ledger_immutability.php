<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private array $names = ['ledger_insert_draft', 'ledger_immutable_update', 'ledger_immutable_delete', 'ledger_submitted_header', 'ledger_post_guard', 'ledger_detail_insert', 'ledger_detail_update', 'ledger_detail_delete'];
    public function up(): void
    {
        $checks = [
            ['ledger_insert_draft', 'INSERT', 'journal_entries', "NEW.status = 'posted'", 'Create a draft and use the approval workflow.'],
            ['ledger_immutable_update', 'UPDATE', 'journal_entries', "OLD.status = 'posted'", 'Posted journals are immutable; create a reversal.'],
            ['ledger_immutable_delete', 'DELETE', 'journal_entries', "OLD.status = 'posted' OR OLD.submitted_at IS NOT NULL", 'Posted or submitted journals cannot be deleted.'],
            ['ledger_submitted_header', 'UPDATE', 'journal_entries', "OLD.submitted_at IS NOT NULL AND (NEW.entry_date != OLD.entry_date OR NEW.total_debit != OLD.total_debit OR NEW.total_credit != OLD.total_credit OR NEW.created_by != OLD.created_by OR NEW.description != OLD.description OR COALESCE(NEW.reference_type, '') != COALESCE(OLD.reference_type, '') OR COALESCE(NEW.reference_id, 0) != COALESCE(OLD.reference_id, 0) OR COALESCE(NEW.reversal_of, 0) != COALESCE(OLD.reversal_of, 0))", 'Return a submitted journal for review before changing it.'],
            ['ledger_post_guard', 'UPDATE', 'journal_entries', "NEW.status = 'posted' AND (NEW.submitted_at IS NULL OR NEW.approved_at IS NULL OR NEW.approved_by IS NULL OR NEW.approved_by = NEW.created_by OR NEW.posted_at IS NULL OR NOT EXISTS (SELECT 1 FROM financial_periods WHERE starts_on <= NEW.entry_date AND ends_on >= NEW.entry_date AND closed = 0) OR ROUND(NEW.total_debit * 100) <= 0 OR ROUND(NEW.total_debit * 100) != ROUND(NEW.total_credit * 100) OR ROUND(NEW.total_debit * 100) != COALESCE((SELECT SUM(ROUND(debit * 100)) FROM journal_details WHERE journal_id = NEW.journal_id), 0) OR ROUND(NEW.total_credit * 100) != COALESCE((SELECT SUM(ROUND(credit * 100)) FROM journal_details WHERE journal_id = NEW.journal_id), 0) OR EXISTS (SELECT 1 FROM journal_details d JOIN chart_of_accounts a ON a.account_id = d.account_id WHERE d.journal_id = NEW.journal_id AND (a.is_active = 0 OR d.debit < 0 OR d.credit < 0 OR (d.debit = 0 AND d.credit = 0) OR (d.debit > 0 AND d.credit > 0))))", 'Journal approval, open period and balanced valid lines are required.'],
            ['ledger_detail_insert', 'INSERT', 'journal_details', "EXISTS (SELECT 1 FROM journal_entries WHERE journal_id = NEW.journal_id AND (status = 'posted' OR submitted_at IS NOT NULL))", 'Submitted journal details are immutable.'],
            ['ledger_detail_update', 'UPDATE', 'journal_details', "EXISTS (SELECT 1 FROM journal_entries WHERE journal_id IN (OLD.journal_id, NEW.journal_id) AND (status = 'posted' OR submitted_at IS NOT NULL))", 'Submitted journal details are immutable.'],
            ['ledger_detail_delete', 'DELETE', 'journal_details', "EXISTS (SELECT 1 FROM journal_entries WHERE journal_id = OLD.journal_id AND (status = 'posted' OR submitted_at IS NOT NULL))", 'Submitted journal details are immutable.'],
        ];
        foreach ($checks as [$name, $event, $table, $condition, $message]) {
            if (DB::getDriverName() === 'sqlite') DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table WHEN $condition BEGIN SELECT RAISE(ABORT, '$message'); END");
            elseif (DB::getDriverName() === 'mysql') DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table FOR EACH ROW BEGIN IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '$message'; END IF; END");
        }
    }
    public function down(): void { foreach ($this->names as $name) DB::unprepared("DROP TRIGGER IF EXISTS $name"); }
};

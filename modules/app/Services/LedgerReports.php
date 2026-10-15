<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class LedgerReports
{
    public function accountBalance(?int $id, ?string $end = null): int
    {
        if (!$id) return 0;
        $query = DB::table('journal_details as d')->join('journal_entries as j', 'd.journal_id', '=', 'j.journal_id')->where('j.status', 'posted')->where('d.account_id', $id);
        if ($end) $query->where('j.entry_date', '<=', $end);
        return (int) round($query->selectRaw('COALESCE(SUM(d.debit - d.credit), 0) AS balance')->value('balance') * 100);
    }
    public function totals(?string $start = null, ?string $end = null): array
    {
        $query = DB::table('journal_details as d')->join('journal_entries as j', 'd.journal_id', '=', 'j.journal_id')->join('chart_of_accounts as a', 'd.account_id', '=', 'a.account_id')->where('j.status', 'posted');
        if ($start) $query->where('j.entry_date', '>=', $start);
        if ($end) $query->where('j.entry_date', '<=', $end);
        $values = array_fill_keys(['debit', 'credit', 'revenue', 'expense', 'asset', 'liability', 'equity'], 0);
        foreach ($query->select('d.debit', 'd.credit', 'a.account_type')->cursor() as $row) {
            $dr = (int) round($row->debit * 100); $cr = (int) round($row->credit * 100);
            $values['debit'] += $dr; $values['credit'] += $cr;
            $values[$row->account_type] += in_array($row->account_type, ['asset', 'expense'], true) ? $dr - $cr : $cr - $dr;
        }
        $values['income'] = $values['revenue'] - $values['expense'];
        $values['balanced'] = $values['debit'] === $values['credit'];
        return $values;
    }
}

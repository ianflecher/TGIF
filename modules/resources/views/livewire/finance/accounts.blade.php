<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Response;

new #[Layout('components.layouts.finance')] class extends Component
{
    use WithPagination;
    
    // Properties for different sections
    public $activeSection = 'journal_entries';
    public $search = '';
    public $perPage = 10;
    
    // Journal Entry Properties
    public $showJournalEntryModal = false;
    public $showJournalEntryDetailsModal = false;
    public $selectedJournalEntry = null;
    public $journalEntryDetails = [];
    public $entryDate;
    public $journalDescription = '';
    public $referenceType = '';
    public $referenceId = '';
    public $journalItems = [];
    public $currentJournalItem = [
        'account_id' => '',
        'debit' => 0,
        'credit' => 0,
        'description' => ''
    ];
    
    // Chart of Accounts Properties
    public $showAccountModal = false;
    public $selectedAccount = null;
    public $accountCode = '';
    public $accountName = '';
    public $accountType = '';
    public $normalBalance = 'debit';
    public $parentAccountId = '';
    public $accountDescription = '';
    public $isActive = true;
    
    // Trial Balance Properties
    public $startDate;
    public $endDate;
    public $trialBalanceData = [];
    
    // Financial Statements Properties
    public $statementType = 'balance_sheet';
    public $statementPeriod = 'current_month';
    public $financialStatementData = [];
    
    public function mount()
    {
        $this->entryDate = date('Y-m-d');
        $this->startDate = date('Y-m-01');
        $this->endDate = date('Y-m-t');
    }
    
    // Format currency to Philippine Peso
    public function formatCurrency($amount)
    {
        return '₱' . number_format($amount, 2);
    }
    
    // Download Financial Statements as CSV
    public function downloadCSV()
    {
        if (count($this->financialStatementData) === 0) {
            session()->flash('error', 'No financial statement data to export');
            return;
        }
        
        $filename = $this->statementType . '_' . $this->statementPeriod . '_' . date('Ymd_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() {
            $handle = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");
            
            if ($this->statementType === 'balance_sheet') {
                $this->generateCSVForBalanceSheet($handle);
            } else {
                $this->generateCSVForIncomeStatement($handle);
            }
            
            fclose($handle);
        };
        
        return Response::stream($callback, 200, $headers);
    }
    
    private function generateCSVForBalanceSheet($handle)
    {
        // Header
        fputcsv($handle, ['BALANCE SHEET']);
        fputcsv($handle, ['Period: ' . ucfirst(str_replace('_', ' ', $this->statementPeriod))]);
        fputcsv($handle, ['Generated: ' . now()->format('F d, Y H:i:s')]);
        fputcsv($handle, ['Currency: Philippine Peso (₱)']);
        fputcsv($handle, []);
        fputcsv($handle, ['ASSETS', 'Amount']);
        
        // Assets
        foreach ($this->financialStatementData['assets'] as $asset) {
            fputcsv($handle, [
                $asset->account_name,
                '₱' . number_format($asset->balance, 2)
            ]);
        }
        fputcsv($handle, ['TOTAL ASSETS', '₱' . number_format($this->financialStatementData['total_assets'], 2)]);
        fputcsv($handle, []);
        
        // Liabilities
        fputcsv($handle, ['LIABILITIES', 'Amount']);
        foreach ($this->financialStatementData['liabilities'] as $liability) {
            fputcsv($handle, [
                $liability->account_name,
                '₱' . number_format($liability->balance, 2)
            ]);
        }
        fputcsv($handle, ['TOTAL LIABILITIES', '₱' . number_format($this->financialStatementData['total_liabilities'], 2)]);
        fputcsv($handle, []);
        
        // Equity
        fputcsv($handle, ['EQUITY', 'Amount']);
        foreach ($this->financialStatementData['equity'] as $equity) {
            fputcsv($handle, [
                $equity->account_name,
                '₱' . number_format($equity->balance, 2)
            ]);
        }
        fputcsv($handle, ['TOTAL EQUITY', '₱' . number_format($this->financialStatementData['total_equity'], 2)]);
        fputcsv($handle, []);
        
        // Summary
        fputcsv($handle, ['TOTAL LIABILITIES & EQUITY', '₱' . number_format($this->financialStatementData['total_liabilities'] + $this->financialStatementData['total_equity'], 2)]);
        fputcsv($handle, []);
        
        // Balance Check
        $isBalanced = abs($this->financialStatementData['total_assets'] - ($this->financialStatementData['total_liabilities'] + $this->financialStatementData['total_equity'])) < 0.01;
        fputcsv($handle, ['BALANCE CHECK', $isBalanced ? 'BALANCED' : 'NOT BALANCED']);
        fputcsv($handle, ['Assets', '₱' . number_format($this->financialStatementData['total_assets'], 2)]);
        fputcsv($handle, ['Liabilities + Equity', '₱' . number_format($this->financialStatementData['total_liabilities'] + $this->financialStatementData['total_equity'], 2)]);
    }
    
    private function generateCSVForIncomeStatement($handle)
    {
        // Header
        fputcsv($handle, ['INCOME STATEMENT']);
        fputcsv($handle, ['Period: ' . ucfirst(str_replace('_', ' ', $this->statementPeriod))]);
        fputcsv($handle, ['Generated: ' . now()->format('F d, Y H:i:s')]);
        fputcsv($handle, ['Currency: Philippine Peso (₱)']);
        fputcsv($handle, []);
        fputcsv($handle, ['REVENUE', 'Amount']);
        
        // Revenue
        foreach ($this->financialStatementData['revenues'] as $revenue) {
            if ($revenue->amount > 0) {
                fputcsv($handle, [
                    $revenue->account_name,
                    '₱' . number_format($revenue->amount, 2)
                ]);
            }
        }
        fputcsv($handle, ['TOTAL REVENUE', '₱' . number_format($this->financialStatementData['total_revenue'], 2)]);
        fputcsv($handle, []);
        
        // Expenses
        fputcsv($handle, ['EXPENSES', 'Amount']);
        foreach ($this->financialStatementData['expenses'] as $expense) {
            if ($expense->amount > 0) {
                fputcsv($handle, [
                    $expense->account_name,
                    '₱' . number_format($expense->amount, 2)
                ]);
            }
        }
        fputcsv($handle, ['TOTAL EXPENSES', '₱' . number_format($this->financialStatementData['total_expense'], 2)]);
        fputcsv($handle, []);
        
        // Net Income
        $netIncome = $this->financialStatementData['net_income'];
        fputcsv($handle, ['NET INCOME/LOSS', '₱' . number_format(abs($netIncome), 2) . ' ' . ($netIncome >= 0 ? 'Profit' : 'Loss')]);
    }
    
    // Download as HTML/PDF using JavaScript
    public function downloadPDF()
    {
        if (count($this->financialStatementData) === 0) {
            session()->flash('error', 'No financial statement data to export');
            return;
        }
        
        // Just trigger the JavaScript function
        $this->dispatch('download-pdf');
    }
    
    // Journal Entries Methods
    public function loadJournalEntries()
    {
        $query = DB::table('journal_entries as je')
            ->select(
                'je.journal_id',
                'je.entry_date',
                'je.journal_number',
                'je.description',
                'je.status',
                'je.total_debit',
                'je.total_credit',
                'u.full_name as created_by_name',
                'je.reference_type',
                'je.reference_id',
                'je.created_at'
            )
            ->leftJoin('users as u', 'je.created_by', '=', 'u.user_id');
        
        if ($this->search) {
            $query->where(function($q) {
                $q->where('je.journal_number', 'like', '%' . $this->search . '%')
                  ->orWhere('je.description', 'like', '%' . $this->search . '%')
                  ->orWhere('u.full_name', 'like', '%' . $this->search . '%');
            });
        }
        
        return $query->orderBy('je.entry_date', 'desc')
                    ->orderBy('je.created_at', 'desc')
                    ->paginate($this->perPage);
    }
    
    public function getJournalEntryDetails($journalId)
    {
        return DB::table('journal_entries as je')
            ->select(
                'je.journal_id',
                'je.entry_date',
                'je.journal_number',
                'je.description',
                'je.status',
                'je.total_debit',
                'je.total_credit',
                'u.full_name as created_by_name',
                'je.reference_type',
                'je.reference_id',
                'je.created_at'
            )
            ->leftJoin('users as u', 'je.created_by', '=', 'u.user_id')
            ->where('je.journal_id', $journalId)
            ->first();
    }
    
    public function getJournalEntryItems($journalId)
    {
        return DB::table('journal_details as jd')
            ->select(
                'jd.detail_id',
                'jd.account_id',
                'jd.debit',
                'jd.credit',
                'jd.description',
                'ca.account_code',
                'ca.account_name',
                'ca.account_type',
                'ca.normal_balance'
            )
            ->leftJoin('chart_of_accounts as ca', 'jd.account_id', '=', 'ca.account_id')
            ->where('jd.journal_id', $journalId)
            ->get();
    }
    
    public function showNewJournalEntryModal()
    {
        $this->resetJournalEntryForm();
        $this->showJournalEntryModal = true;
    }
    
    public function showJournalEntryDetails($journalId)
    {
        $this->selectedJournalEntry = $journalId;
        $this->journalEntryDetails = $this->getJournalEntryDetails($journalId);
        $this->showJournalEntryDetailsModal = true;
    }
    
    public function resetJournalEntryForm()
    {
        $this->entryDate = date('Y-m-d');
        $this->journalDescription = '';
        $this->referenceType = '';
        $this->referenceId = '';
        $this->journalItems = [];
        $this->currentJournalItem = [
            'account_id' => '',
            'debit' => 0,
            'credit' => 0,
            'description' => ''
        ];
    }
    
    public function addJournalItem()
    {
        $this->validate([
            'currentJournalItem.account_id' => 'required|exists:chart_of_accounts,account_id',
            'currentJournalItem.debit' => 'required|numeric|min:0',
            'currentJournalItem.credit' => 'required|numeric|min:0',
        ]);
        
        // Get account details
        $account = DB::table('chart_of_accounts')
            ->where('account_id', $this->currentJournalItem['account_id'])
            ->first(['account_code', 'account_name']);
        
        $this->journalItems[] = [
            ...$this->currentJournalItem,
            'account_code' => $account->account_code,
            'account_name' => $account->account_name,
            'temp_id' => uniqid()
        ];
        
        $this->currentJournalItem = [
            'account_id' => '',
            'debit' => 0,
            'credit' => 0,
            'description' => ''
        ];
    }
    
    public function removeJournalItem($index)
    {
        unset($this->journalItems[$index]);
        $this->journalItems = array_values($this->journalItems);
    }
    
    public function saveJournalEntry()
    {
        $this->validate([
            'entryDate' => 'required|date',
            'journalDescription' => 'required|min:10|max:500',
            'journalItems' => 'required|array|min:1',
        ]);
        
        // Validate debit and credit totals
        $totalDebit = array_sum(array_column($this->journalItems, 'debit'));
        $totalCredit = array_sum(array_column($this->journalItems, 'credit'));
        
        if (abs($totalDebit - $totalCredit) > 0.01) {
            session()->flash('error', 'Journal entry must balance. Total debit (' . $this->formatCurrency($totalDebit) . ') must equal total credit (' . $this->formatCurrency($totalCredit) . ')');
            return;
        }
        
        try {
            DB::beginTransaction();
            
            $currentUser = Auth::user();
            $now = now();
            
            // Generate journal number
            $journalNumber = 'JN-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            
            // Create journal entry
            $journalId = DB::table('journal_entries')->insertGetId([
                'entry_date' => $this->entryDate,
                'journal_number' => $journalNumber,
                'description' => $this->journalDescription,
                'created_by' => $currentUser->user_id,
                'reference_type' => $this->referenceType ?: null,
                'reference_id' => $this->referenceId ?: null,
                'status' => 'posted',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'created_at' => $now,
                'updated_at' => $now
            ]);
            
            // Create journal details
            foreach ($this->journalItems as $item) {
                DB::table('journal_details')->insert([
                    'journal_id' => $journalId,
                    'account_id' => $item['account_id'],
                    'debit' => $item['debit'],
                    'credit' => $item['credit'],
                    'description' => $item['description'],
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
            }
            
            // Create audit log
            DB::table('audit_logs')->insert([
                'action' => 'CREATE',
                'table_name' => 'journal_entries',
                'record_id' => $journalId,
                'old_values' => null,
                'new_values' => json_encode([
                    'journal_number' => $journalNumber,
                    'description' => $this->journalDescription,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit
                ]),
                'user_id' => $currentUser->user_id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => $now,
                'updated_at' => $now
            ]);
            
            DB::commit();
            
            $this->showJournalEntryModal = false;
            $this->resetJournalEntryForm();
            
            session()->flash('success', 'Journal entry saved successfully with number: ' . $journalNumber);
            
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Failed to save journal entry: ' . $e->getMessage());
        }
    }
    
    // Chart of Accounts Methods
    public function loadChartOfAccounts()
    {
        $query = DB::table('chart_of_accounts as ca')
            ->select(
                'ca.account_id',
                'ca.account_code',
                'ca.account_name',
                'ca.account_type',
                'ca.normal_balance',
                'ca.description',
                'ca.is_active',
                'parent.account_name as parent_account_name'
            )
            ->leftJoin('chart_of_accounts as parent', 'ca.parent_account_id', '=', 'parent.account_id');
        
        if ($this->search) {
            $query->where(function($q) {
                $q->where('ca.account_code', 'like', '%' . $this->search . '%')
                  ->orWhere('ca.account_name', 'like', '%' . $this->search . '%')
                  ->orWhere('ca.description', 'like', '%' . $this->search . '%');
            });
        }
        
        return $query->orderBy('ca.account_code')
                    ->paginate($this->perPage);
    }
    
    public function showNewAccountModal()
    {
        $this->resetAccountForm();
        $this->showAccountModal = true;
    }
    
    public function showEditAccountModal($accountId)
    {
        $account = DB::table('chart_of_accounts')
            ->where('account_id', $accountId)
            ->first();
        
        if ($account) {
            $this->selectedAccount = $accountId;
            $this->accountCode = $account->account_code;
            $this->accountName = $account->account_name;
            $this->accountType = $account->account_type;
            $this->normalBalance = $account->normal_balance;
            $this->parentAccountId = $account->parent_account_id;
            $this->accountDescription = $account->description;
            $this->isActive = (bool)$account->is_active;
            $this->showAccountModal = true;
        }
    }
    
    public function resetAccountForm()
    {
        $this->selectedAccount = null;
        $this->accountCode = '';
        $this->accountName = '';
        $this->accountType = '';
        $this->normalBalance = 'debit';
        $this->parentAccountId = '';
        $this->accountDescription = '';
        $this->isActive = true;
    }
    
    public function saveAccount()
    {
        $this->validate([
            'accountCode' => 'required|unique:chart_of_accounts,account_code,' . $this->selectedAccount . ',account_id|max:50',
            'accountName' => 'required|max:150',
            'accountType' => 'required|in:asset,liability,equity,revenue,expense',
            'normalBalance' => 'required|in:debit,credit',
        ]);
        
        try {
            DB::beginTransaction();
            
            $currentUser = Auth::user();
            $now = now();
            
            $accountData = [
                'account_code' => $this->accountCode,
                'account_name' => $this->accountName,
                'account_type' => $this->accountType,
                'normal_balance' => $this->normalBalance,
                'parent_account_id' => $this->parentAccountId ?: null,
                'description' => $this->accountDescription,
                'is_active' => $this->isActive ? 1 : 0,
                'updated_at' => $now
            ];
            
            if ($this->selectedAccount) {
                // Get old values
                $oldAccount = DB::table('chart_of_accounts')
                    ->where('account_id', $this->selectedAccount)
                    ->first();
                
                // Update account
                DB::table('chart_of_accounts')
                    ->where('account_id', $this->selectedAccount)
                    ->update($accountData);
                
                $action = 'UPDATE';
                $oldValues = json_encode($oldAccount);
                $recordId = $this->selectedAccount;
            } else {
                // Create new account
                $accountData['created_at'] = $now;
                $recordId = DB::table('chart_of_accounts')->insertGetId($accountData);
                $action = 'CREATE';
                $oldValues = null;
            }
            
            // Create audit log
            DB::table('audit_logs')->insert([
                'action' => $action,
                'table_name' => 'chart_of_accounts',
                'record_id' => $recordId,
                'old_values' => $oldValues,
                'new_values' => json_encode($accountData),
                'user_id' => $currentUser->user_id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => $now,
                'updated_at' => $now
            ]);
            
            DB::commit();
            
            $this->showAccountModal = false;
            $this->resetAccountForm();
            
            session()->flash('success', 'Account saved successfully');
            
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Failed to save account: ' . $e->getMessage());
        }
    }
    
    // Trial Balance Methods
    public function generateTrialBalance()
    {
        $this->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);
        
        $this->trialBalanceData = DB::table('chart_of_accounts as ca')
            ->select(
                'ca.account_id',
                'ca.account_code',
                'ca.account_name',
                'ca.account_type',
                'ca.normal_balance',
                DB::raw('COALESCE(SUM(jd.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(jd.credit), 0) as total_credit')
            )
            ->leftJoin('journal_details as jd', function($join) {
                $join->on('ca.account_id', '=', 'jd.account_id')
                     ->whereBetween('jd.created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59']);
            })
            ->leftJoin('journal_entries as je', 'jd.journal_id', '=', 'je.journal_id')
            ->where('je.status', 'posted')
            ->where('ca.is_active', 1)
            ->groupBy('ca.account_id', 'ca.account_code', 'ca.account_name', 'ca.account_type', 'ca.normal_balance')
            ->havingRaw('COALESCE(SUM(jd.debit), 0) != 0 OR COALESCE(SUM(jd.credit), 0) != 0')
            ->orderBy('ca.account_code')
            ->get();
        
        session()->flash('info', 'Trial balance generated for period ' . $this->startDate . ' to ' . $this->endDate);
    }
    
    // Financial Statements Methods
    public function generateFinancialStatement()
    {
        $this->validate([
            'statementPeriod' => 'required|in:current_month,last_month,current_quarter,current_year',
        ]);
        
        switch ($this->statementPeriod) {
            case 'current_month':
                $startDate = date('Y-m-01');
                $endDate = date('Y-m-t');
                break;
            case 'last_month':
                $startDate = date('Y-m-01', strtotime('-1 month'));
                $endDate = date('Y-m-t', strtotime('-1 month'));
                break;
            case 'current_quarter':
                $quarter = ceil(date('n') / 3);
                $startDate = date('Y-m-d', strtotime(date('Y') . '-' . (($quarter - 1) * 3 + 1) . '-01'));
                $endDate = date('Y-m-t', strtotime(date('Y') . '-' . ($quarter * 3) . '-01'));
                break;
            case 'current_year':
                $startDate = date('Y-01-01');
                $endDate = date('Y-12-31');
                break;
        }
        
        if ($this->statementType === 'balance_sheet') {
            $this->generateBalanceSheet($startDate, $endDate);
        } else {
            $this->generateIncomeStatement($startDate, $endDate);
        }
        
        session()->flash('info', ucfirst(str_replace('_', ' ', $this->statementType)) . ' generated for ' . $this->statementPeriod);
    }
    
    private function generateBalanceSheet($startDate, $endDate)
    {
        $this->financialStatementData = [
            'assets' => [],
            'liabilities' => [],
            'equity' => [],
            'total_assets' => 0,
            'total_liabilities' => 0,
            'total_equity' => 0,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        // Get all accounts with their balances
        $accounts = DB::table('chart_of_accounts as ca')
            ->select(
                'ca.account_id',
                'ca.account_code',
                'ca.account_name',
                'ca.account_type',
                'ca.normal_balance',
                DB::raw('COALESCE(SUM(CASE WHEN ca.normal_balance = "debit" THEN jd.debit - jd.credit ELSE jd.credit - jd.debit END), 0) as balance')
            )
            ->leftJoin('journal_details as jd', 'ca.account_id', '=', 'jd.account_id')
            ->leftJoin('journal_entries as je', function($join) use ($startDate, $endDate) {
                $join->on('jd.journal_id', '=', 'je.journal_id')
                     ->whereBetween('je.entry_date', [$startDate, $endDate])
                     ->where('je.status', 'posted');
            })
            ->where('ca.is_active', 1)
            ->whereIn('ca.account_type', ['asset', 'liability', 'equity'])
            ->groupBy('ca.account_id', 'ca.account_code', 'ca.account_name', 'ca.account_type', 'ca.normal_balance')
            ->orderBy('ca.account_code')
            ->get();
        
        foreach ($accounts as $account) {
            switch ($account->account_type) {
                case 'asset':
                    $this->financialStatementData['assets'][] = $account;
                    $this->financialStatementData['total_assets'] += $account->balance;
                    break;
                case 'liability':
                    $this->financialStatementData['liabilities'][] = $account;
                    $this->financialStatementData['total_liabilities'] += $account->balance;
                    break;
                case 'equity':
                    $this->financialStatementData['equity'][] = $account;
                    $this->financialStatementData['total_equity'] += $account->balance;
                    break;
            }
        }
    }
    
    private function generateIncomeStatement($startDate, $endDate)
    {
        $this->financialStatementData = [
            'revenues' => [],
            'expenses' => [],
            'total_revenue' => 0,
            'total_expense' => 0,
            'net_income' => 0,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        // Get revenue accounts
        $revenues = DB::table('chart_of_accounts as ca')
            ->select(
                'ca.account_id',
                'ca.account_code',
                'ca.account_name',
                DB::raw('COALESCE(SUM(jd.credit - jd.debit), 0) as amount')
            )
            ->leftJoin('journal_details as jd', 'ca.account_id', '=', 'jd.account_id')
            ->leftJoin('journal_entries as je', function($join) use ($startDate, $endDate) {
                $join->on('jd.journal_id', '=', 'je.journal_id')
                     ->whereBetween('je.entry_date', [$startDate, $endDate])
                     ->where('je.status', 'posted');
            })
            ->where('ca.is_active', 1)
            ->where('ca.account_type', 'revenue')
            ->groupBy('ca.account_id', 'ca.account_code', 'ca.account_name')
            ->orderBy('ca.account_code')
            ->get();
        
        // Get expense accounts
        $expenses = DB::table('chart_of_accounts as ca')
            ->select(
                'ca.account_id',
                'ca.account_code',
                'ca.account_name',
                DB::raw('COALESCE(SUM(jd.debit - jd.credit), 0) as amount')
            )
            ->leftJoin('journal_details as jd', 'ca.account_id', '=', 'jd.account_id')
            ->leftJoin('journal_entries as je', function($join) use ($startDate, $endDate) {
                $join->on('jd.journal_id', '=', 'je.journal_id')
                     ->whereBetween('je.entry_date', [$startDate, $endDate])
                     ->where('je.status', 'posted');
            })
            ->where('ca.is_active', 1)
            ->where('ca.account_type', 'expense')
            ->groupBy('ca.account_id', 'ca.account_code', 'ca.account_name')
            ->orderBy('ca.account_code')
            ->get();
        
        $this->financialStatementData['revenues'] = $revenues;
        $this->financialStatementData['expenses'] = $expenses;
        $this->financialStatementData['total_revenue'] = $revenues->sum('amount');
        $this->financialStatementData['total_expense'] = $expenses->sum('amount');
        $this->financialStatementData['net_income'] = $this->financialStatementData['total_revenue'] - $this->financialStatementData['total_expense'];
    }
    
    // Utility Methods
    public function getAccountTypes()
    {
        return [
            'asset' => 'Asset',
            'liability' => 'Liability',
            'equity' => 'Equity',
            'revenue' => 'Revenue',
            'expense' => 'Expense'
        ];
    }
    
    public function getParentAccounts()
    {
        return DB::table('chart_of_accounts')
            ->select('account_id', 'account_code', 'account_name')
            ->where('is_active', 1)
            ->orderBy('account_code')
            ->get();
    }
    
    public function getActiveAccounts()
    {
        return DB::table('chart_of_accounts')
            ->select('account_id', 'account_code', 'account_name', 'account_type')
            ->where('is_active', 1)
            ->orderBy('account_code')
            ->get();
    }
    
    public function updated($property)
    {
        if (in_array($property, ['search', 'perPage'])) {
            $this->resetPage();
        }
    }
}
?>
<div>
    <!-- Page Header -->
    <div class="page-header">
        <h1>General Ledger Management</h1>
        <p>Comprehensive financial accounting system with real-time reporting</p>
    </div>

    <!-- Navigation Tabs -->
    <div class="mb-6">
        <div class="flex space-x-1 border-b">
            <button @class([
                'px-4 py-2 font-medium text-sm border-b-2 transition-colors',
                'border-finance-600 text-finance-700' => $activeSection === 'journal_entries',
                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' => $activeSection !== 'journal_entries'
            ]) wire:click="$set('activeSection', 'journal_entries')">
                <i class="fas fa-book mr-2"></i>Journal Entries
            </button>
            <button @class([
                'px-4 py-2 font-medium text-sm border-b-2 transition-colors',
                'border-finance-600 text-finance-700' => $activeSection === 'chart_of_accounts',
                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' => $activeSection !== 'chart_of_accounts'
            ]) wire:click="$set('activeSection', 'chart_of_accounts')">
                <i class="fas fa-chart-pie mr-2"></i>Chart of Accounts
            </button>
            <button @class([
                'px-4 py-2 font-medium text-sm border-b-2 transition-colors',
                'border-finance-600 text-finance-700' => $activeSection === 'trial_balance',
                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' => $activeSection !== 'trial_balance'
            ]) wire:click="$set('activeSection', 'trial_balance')">
                <i class="fas fa-balance-scale mr-2"></i>Trial Balance
            </button>
            <button @class([
                'px-4 py-2 font-medium text-sm border-b-2 transition-colors',
                'border-finance-600 text-finance-700' => $activeSection === 'financial_statements',
                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' => $activeSection !== 'financial_statements'
            ]) wire:click="$set('activeSection', 'financial_statements')">
                <i class="fas fa-file-invoice-dollar mr-2"></i>Financial Statements
            </button>
        </div>
    </div>

    <!-- Journal Entries Section -->
    @if($activeSection === 'journal_entries')
    <div class="page-card">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">Journal Entries</h2>
            <div class="flex gap-4">
                <div class="relative">
                    <input type="text" 
                           wire:model.live="search" 
                           placeholder="Search journal entries..."
                           class="form-input pl-10 w-64">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
                <button wire:click="showNewJournalEntryModal" class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>New Journal Entry
                </button>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            @php
                $journalEntries = $this->loadJournalEntries();
            @endphp
            
            <table class="financial-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Journal #</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($journalEntries as $entry)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($entry->entry_date)->format('M d, Y') }}</td>
                        <td class="font-mono font-bold">{{ $entry->journal_number }}</td>
                        <td>{{ Str::limit($entry->description, 80) }}</td>
                        <td>
                            <span @class([
                                'status-badge',
                                'status-approved' => $entry->status === 'posted',
                                'status-draft' => $entry->status === 'draft',
                                'status-rejected' => $entry->status === 'cancelled'
                            ])>
                                <i class="fas fa-{{ $entry->status === 'posted' ? 'check' : ($entry->status === 'cancelled' ? 'times' : 'edit') }}"></i>
                                {{ ucfirst($entry->status) }}
                            </span>
                        </td>
                        <td class="table-amount">{{ $this->formatCurrency($entry->total_debit) }}</td>
                        <td class="table-amount">{{ $this->formatCurrency($entry->total_credit) }}</td>
                        <td>{{ $entry->created_by_name }}</td>
                        <td>
                            <button wire:click="showJournalEntryDetails({{ $entry->journal_id }})" 
                                    class="btn btn-secondary text-sm"
                                    title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-8 text-gray-500">
                            <i class="fas fa-book text-4xl mb-2"></i>
                            <div>No journal entries found.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            
            @if($journalEntries->hasPages())
            <div class="mt-6">
                {{ $journalEntries->links() }}
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Chart of Accounts Section -->
    @if($activeSection === 'chart_of_accounts')
    <div class="page-card">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">Chart of Accounts</h2>
            <div class="flex gap-4">
                <div class="relative">
                    <input type="text" 
                           wire:model.live="search" 
                           placeholder="Search accounts..."
                           class="form-input pl-10 w-64">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
                <button wire:click="showNewAccountModal" class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>New Account
                </button>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            @php
                $accounts = $this->loadChartOfAccounts();
            @endphp
            
            <table class="financial-table">
                <thead>
                    <tr>
                        <th>Account Code</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Normal Balance</th>
                        <th>Parent Account</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                    <tr>
                        <td class="font-mono font-bold">{{ $account->account_code }}</td>
                        <td>{{ $account->account_name }}</td>
                        <td>
                            <span @class([
                                'status-badge',
                                'status-paid' => $account->account_type === 'asset',
                                'status-pending' => $account->account_type === 'liability',
                                'status-approved' => $account->account_type === 'equity',
                                'status-draft' => $account->account_type === 'revenue',
                                'status-overdue' => $account->account_type === 'expense'
                            ])>
                                {{ ucfirst($account->account_type) }}
                            </span>
                        </td>
                        <td>
                            <span @class([
                                'status-badge',
                                'status-paid' => $account->normal_balance === 'debit',
                                'status-pending' => $account->normal_balance === 'credit'
                            ])>
                                {{ ucfirst($account->normal_balance) }}
                            </span>
                        </td>
                        <td>{{ $account->parent_account_name ?? '-' }}</td>
                        <td>
                            @if($account->is_active)
                            <span class="status-badge status-paid">
                                <i class="fas fa-check"></i>Active
                            </span>
                            @else
                            <span class="status-badge status-rejected">
                                <i class="fas fa-times"></i>Inactive
                            </span>
                            @endif
                        </td>
                        <td>
                            <button wire:click="showEditAccountModal({{ $account->account_id }})" 
                                    class="btn btn-secondary text-sm">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-8 text-gray-500">
                            <i class="fas fa-chart-pie text-4xl mb-2"></i>
                            <div>No accounts found.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            
            @if($accounts->hasPages())
            <div class="mt-6">
                {{ $accounts->links() }}
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Trial Balance Section -->
    @if($activeSection === 'trial_balance')
    <div class="page-card">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">Trial Balance</h2>
            <div class="flex gap-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Start Date</label>
                        <input type="date" wire:model="startDate" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">End Date</label>
                        <input type="date" wire:model="endDate" class="form-input">
                    </div>
                </div>
                <button wire:click="generateTrialBalance" class="btn btn-primary mt-6">
                    <i class="fas fa-calculator mr-2"></i>Generate
                </button>
            </div>
        </div>
        
        @if(count($trialBalanceData) > 0)
        <div class="overflow-x-auto">
            <table class="financial-table">
                <thead>
                    <tr>
                        <th>Account Code</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Debit Balance</th>
                        <th>Credit Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($trialBalanceData as $account)
                    <tr>
                        <td class="font-mono font-bold">{{ $account->account_code }}</td>
                        <td>{{ $account->account_name }}</td>
                        <td>
                            <span @class([
                                'status-badge',
                                'status-paid' => $account->account_type === 'asset',
                                'status-pending' => $account->account_type === 'liability',
                                'status-approved' => $account->account_type === 'equity',
                                'status-draft' => $account->account_type === 'revenue',
                                'status-overdue' => $account->account_type === 'expense'
                            ])>
                                {{ ucfirst($account->account_type) }}
                            </span>
                        </td>
                        <td class="table-amount">
                            @if($account->normal_balance === 'debit')
                            <span class="font-bold">{{ $this->formatCurrency($account->total_debit - $account->total_credit) }}</span>
                            @else
                            <span class="text-gray-400">{{ $this->formatCurrency(0) }}</span>
                            @endif
                        </td>
                        <td class="table-amount">
                            @if($account->normal_balance === 'credit')
                            <span class="font-bold">{{ $this->formatCurrency($account->total_credit - $account->total_debit) }}</span>
                            @else
                            <span class="text-gray-400">{{ $this->formatCurrency(0) }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    <tr class="bg-gray-50 font-bold">
                        <td colspan="3" class="text-right">Total:</td>
                        <td class="table-amount">
                            {{ $this->formatCurrency(collect($trialBalanceData)->sum(function($item) {
                                return $item->normal_balance === 'debit' ? $item->total_debit - $item->total_credit : 0;
                            })) }}
                        </td>
                        <td class="table-amount">
                            {{ $this->formatCurrency(collect($trialBalanceData)->sum(function($item) {
                                return $item->normal_balance === 'credit' ? $item->total_credit - $item->total_debit : 0;
                            })) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-12 text-gray-500">
            <i class="fas fa-balance-scale text-4xl mb-2"></i>
            <div>No trial balance data generated yet.</div>
            <p class="text-sm mt-2">Select a date range and click "Generate" to create a trial balance.</p>
        </div>
        @endif
    </div>
    @endif

    <!-- Financial Statements Section -->
    @if($activeSection === 'financial_statements')
    <div class="page-card">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">Financial Statements</h2>
            <div class="flex gap-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Statement Type</label>
                        <select wire:model="statementType" class="form-input">
                            <option value="balance_sheet">Balance Sheet</option>
                            <option value="income_statement">Income Statement</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Period</label>
                        <select wire:model="statementPeriod" class="form-input">
                            <option value="current_month">Current Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="current_quarter">Current Quarter</option>
                            <option value="current_year">Current Year</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <button wire:click="generateFinancialStatement" class="btn btn-primary">
                        <i class="fas fa-calculator mr-2"></i>Generate
                    </button>
                    @if(count($financialStatementData) > 0)
                    <button wire:click="downloadCSV" class="btn btn-success">
                        <i class="fas fa-file-excel mr-2"></i>CSV/Excel
                    </button>
                    <button wire:click="downloadPDF" class="btn btn-danger">
                        <i class="fas fa-file-pdf mr-2"></i>PDF
                    </button>
                    @endif
                </div>
            </div>
        </div>
        
        @if(count($financialStatementData) > 0)
        <div class="space-y-6">
            @if($statementType === 'balance_sheet')
            <!-- Balance Sheet -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Assets -->
                <div class="bg-white rounded-lg border">
                    <div class="p-4 border-b">
                        <h3 class="font-bold text-lg text-gray-800">Assets</h3>
                    </div>
                    <div class="p-4">
                        <table class="w-full">
                            <tbody>
                                @foreach($financialStatementData['assets'] as $asset)
                                <tr class="border-b">
                                    <td class="py-2">{{ $asset->account_name }}</td>
                                    <td class="py-2 text-right font-bold">{{ $this->formatCurrency($asset->balance) }}</td>
                                </tr>
                                @endforeach
                                <tr class="border-t font-bold bg-gray-50">
                                    <td class="py-3">Total Assets</td>
                                    <td class="py-3 text-right">{{ $this->formatCurrency($financialStatementData['total_assets']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Liabilities & Equity -->
                <div class="bg-white rounded-lg border">
                    <div class="p-4 border-b">
                        <h3 class="font-bold text-lg text-gray-800">Liabilities & Equity</h3>
                    </div>
                    <div class="p-4">
                        <!-- Liabilities -->
                        <div class="mb-4">
                            <h4 class="font-bold text-gray-700 mb-2">Liabilities</h4>
                            <table class="w-full">
                                <tbody>
                                    @foreach($financialStatementData['liabilities'] as $liability)
                                    <tr class="border-b">
                                        <td class="py-2">{{ $liability->account_name }}</td>
                                        <td class="py-2 text-right font-bold">{{ $this->formatCurrency($liability->balance) }}</td>
                                    </tr>
                                    @endforeach
                                    <tr class="border-t">
                                        <td class="py-2 font-bold">Total Liabilities</td>
                                        <td class="py-2 text-right font-bold">{{ $this->formatCurrency($financialStatementData['total_liabilities']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Equity -->
                        <div>
                            <h4 class="font-bold text-gray-700 mb-2">Equity</h4>
                            <table class="w-full">
                                <tbody>
                                    @foreach($financialStatementData['equity'] as $equity)
                                    <tr class="border-b">
                                        <td class="py-2">{{ $equity->account_name }}</td>
                                        <td class="py-2 text-right font-bold">{{ $this->formatCurrency($equity->balance) }}</td>
                                    </tr>
                                    @endforeach
                                    <tr class="border-t">
                                        <td class="py-2 font-bold">Total Equity</td>
                                        <td class="py-2 text-right font-bold">{{ $this->formatCurrency($financialStatementData['total_equity']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Total Liabilities & Equity -->
                        <div class="mt-4 pt-4 border-t">
                            <table class="w-full">
                                <tbody>
                                    <tr class="font-bold bg-gray-50">
                                        <td class="py-3">Total Liabilities & Equity</td>
                                        <td class="py-3 text-right">{{ $this->formatCurrency($financialStatementData['total_liabilities'] + $financialStatementData['total_equity']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Balance Sheet Summary -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="font-bold text-green-800">Balance Sheet Check</h3>
                        <p class="text-sm text-green-600 mt-1">
                            Assets = Liabilities + Equity
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-green-800">
                            {{ $this->formatCurrency($financialStatementData['total_assets']) }}
                            <span class="text-lg">=</span>
                            {{ $this->formatCurrency($financialStatementData['total_liabilities'] + $financialStatementData['total_equity']) }}
                        </div>
                        <div class="text-sm text-green-600 mt-1">
                            @if(abs($financialStatementData['total_assets'] - ($financialStatementData['total_liabilities'] + $financialStatementData['total_equity'])) < 0.01)
                            <span class="status-badge status-paid">
                                <i class="fas fa-check mr-1"></i>Balanced
                            </span>
                            @else
                            <span class="status-badge status-overdue">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Out of Balance
                            </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            
            @else
            <!-- Income Statement -->
            <div class="bg-white rounded-lg border">
                <div class="p-4 border-b">
                    <h3 class="font-bold text-lg text-gray-800">Income Statement</h3>
                    <p class="text-sm text-gray-500">
                        For period: {{ ucfirst(str_replace('_', ' ', $statementPeriod)) }}
                    </p>
                </div>
                
                <div class="p-4">
                    <!-- Revenue -->
                    <div class="mb-6">
                        <h4 class="font-bold text-gray-700 mb-2">Revenue</h4>
                        <table class="w-full">
                            <tbody>
                                @foreach($financialStatementData['revenues'] as $revenue)
                                @if($revenue->amount > 0)
                                <tr class="border-b">
                                    <td class="py-2">{{ $revenue->account_name }}</td>
                                    <td class="py-2 text-right font-bold">{{ $this->formatCurrency($revenue->amount) }}</td>
                                </tr>
                                @endif
                                @endforeach
                                <tr class="border-t font-bold">
                                    <td class="py-3">Total Revenue</td>
                                    <td class="py-3 text-right">{{ $this->formatCurrency($financialStatementData['total_revenue']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Expenses -->
                    <div class="mb-6">
                        <h4 class="font-bold text-gray-700 mb-2">Expenses</h4>
                        <table class="w-full">
                            <tbody>
                                @foreach($financialStatementData['expenses'] as $expense)
                                @if($expense->amount > 0)
                                <tr class="border-b">
                                    <td class="py-2">{{ $expense->account_name }}</td>
                                    <td class="py-2 text-right font-bold">{{ $this->formatCurrency($expense->amount) }}</td>
                                </tr>
                                @endif
                                @endforeach
                                <tr class="border-t font-bold">
                                    <td class="py-3">Total Expenses</td>
                                    <td class="py-3 text-right">{{ $this->formatCurrency($financialStatementData['total_expense']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Net Income -->
                    <div class="border-t pt-4">
                        <table class="w-full">
                            <tbody>
                                <tr class="font-bold">
                                    <td class="py-3">Net Income</td>
                                    <td class="py-3 text-right">
                                        <span @class([
                                            'text-green-600' => $financialStatementData['net_income'] >= 0,
                                            'text-red-600' => $financialStatementData['net_income'] < 0
                                        ])>
                                            {{ $this->formatCurrency(abs($financialStatementData['net_income'])) }}
                                            {{ $financialStatementData['net_income'] >= 0 ? 'Profit' : 'Loss' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
        @else
        <div class="text-center py-12 text-gray-500">
            <i class="fas fa-file-invoice-dollar text-4xl mb-2"></i>
            <div>No financial statement data generated yet.</div>
            <p class="text-sm mt-2">Select statement type and period, then click "Generate".</p>
        </div>
        @endif
        
        <!-- Hidden HTML for PDF Generation -->
        <div id="pdf-content" style="display: none;">
            @if(count($financialStatementData) > 0)
                @if($statementType === 'balance_sheet')
                <div style="font-family: Arial, sans-serif; padding: 20px;">
                    <h1 style="text-align: center; color: #2c5282; margin-bottom: 5px;">Balance Sheet</h1>
                    <p style="text-align: center; color: #666; margin-bottom: 20px;">
                        Period: {{ ucfirst(str_replace('_', ' ', $statementPeriod)) }}<br>
                        Generated: {{ now()->format('F d, Y H:i:s') }}
                    </p>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
                        <div style="width: 48%;">
                            <h3 style="background-color: #f7fafc; padding: 10px; border: 1px solid #e2e8f0;">ASSETS</h3>
                            <table style="width: 100%; border-collapse: collapse;">
                                @foreach($financialStatementData['assets'] as $asset)
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 8px;">{{ $asset->account_name }}</td>
                                    <td style="padding: 8px; text-align: right; font-weight: bold;">
                                        {{ $this->formatCurrency($asset->balance) }}
                                    </td>
                                </tr>
                                @endforeach
                                <tr style="background-color: #f7fafc; font-weight: bold;">
                                    <td style="padding: 10px; border-top: 2px solid #2c5282;">Total Assets</td>
                                    <td style="padding: 10px; text-align: right; border-top: 2px solid #2c5282;">
                                        {{ $this->formatCurrency($financialStatementData['total_assets']) }}
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div style="width: 48%;">
                            <h3 style="background-color: #f7fafc; padding: 10px; border: 1px solid #e2e8f0;">LIABILITIES</h3>
                            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                                @foreach($financialStatementData['liabilities'] as $liability)
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 8px;">{{ $liability->account_name }}</td>
                                    <td style="padding: 8px; text-align: right; font-weight: bold;">
                                        {{ $this->formatCurrency($liability->balance) }}
                                    </td>
                                </tr>
                                @endforeach
                                <tr style="background-color: #f7fafc; font-weight: bold;">
                                    <td style="padding: 10px; border-top: 2px solid #2c5282;">Total Liabilities</td>
                                    <td style="padding: 10px; text-align: right; border-top: 2px solid #2c5282;">
                                        {{ $this->formatCurrency($financialStatementData['total_liabilities']) }}
                                    </td>
                                </tr>
                            </table>
                            
                            <h3 style="background-color: #f7fafc; padding: 10px; border: 1px solid #e2e8f0;">EQUITY</h3>
                            <table style="width: 100%; border-collapse: collapse;">
                                @foreach($financialStatementData['equity'] as $equity)
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 8px;">{{ $equity->account_name }}</td>
                                    <td style="padding: 8px; text-align: right; font-weight: bold;">
                                        {{ $this->formatCurrency($equity->balance) }}
                                    </td>
                                </tr>
                                @endforeach
                                <tr style="background-color: #f7fafc; font-weight: bold;">
                                    <td style="padding: 10px; border-top: 2px solid #2c5282;">Total Equity</td>
                                    <td style="padding: 10px; text-align: right; border-top: 2px solid #2c5282;">
                                        {{ $this->formatCurrency($financialStatementData['total_equity']) }}
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; padding: 15px; background-color: #f0fff4; border: 1px solid #9ae6b4;">
                        <h3 style="color: #276749; text-align: center; margin-bottom: 10px;">Balance Sheet Check</h3>
                        <p style="text-align: center; font-size: 18px; margin: 0;">
                            Assets = Liabilities + Equity<br>
                            <strong style="font-size: 20px;">
                                {{ $this->formatCurrency($financialStatementData['total_assets']) }}
                                @if(abs($financialStatementData['total_assets'] - ($financialStatementData['total_liabilities'] + $financialStatementData['total_equity'])) < 0.01)
                                <span style="color: #276749;">= {{ $this->formatCurrency($financialStatementData['total_liabilities'] + $financialStatementData['total_equity']) }}</span>
                                @else
                                <span style="color: #c53030;">≠ {{ $this->formatCurrency($financialStatementData['total_liabilities'] + $financialStatementData['total_equity']) }}</span>
                                @endif
                            </strong>
                        </p>
                    </div>
                </div>
                @else
                <div style="font-family: Arial, sans-serif; padding: 20px;">
                    <h1 style="text-align: center; color: #2c5282; margin-bottom: 5px;">Income Statement</h1>
                    <p style="text-align: center; color: #666; margin-bottom: 20px;">
                        Period: {{ ucfirst(str_replace('_', ' ', $statementPeriod)) }}<br>
                        Generated: {{ now()->format('F d, Y H:i:s') }}
                    </p>
                    
                    <h3 style="background-color: #f7fafc; padding: 10px; border: 1px solid #e2e8f0;">REVENUE</h3>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                        @foreach($financialStatementData['revenues'] as $revenue)
                            @if($revenue->amount > 0)
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 8px;">{{ $revenue->account_name }}</td>
                                <td style="padding: 8px; text-align: right; font-weight: bold;">
                                    {{ $this->formatCurrency($revenue->amount) }}
                                </td>
                            </tr>
                            @endif
                        @endforeach
                        <tr style="background-color: #f7fafc; font-weight: bold;">
                            <td style="padding: 10px; border-top: 2px solid #2c5282;">Total Revenue</td>
                            <td style="padding: 10px; text-align: right; border-top: 2px solid #2c5282;">
                                {{ $this->formatCurrency($financialStatementData['total_revenue']) }}
                            </td>
                        </tr>
                    </table>
                    
                    <h3 style="background-color: #f7fafc; padding: 10px; border: 1px solid #e2e8f0;">EXPENSES</h3>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                        @foreach($financialStatementData['expenses'] as $expense)
                            @if($expense->amount > 0)
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 8px;">{{ $expense->account_name }}</td>
                                <td style="padding: 8px; text-align: right; font-weight: bold;">
                                    {{ $this->formatCurrency($expense->amount) }}
                                </td>
                            </tr>
                            @endif
                        @endforeach
                        <tr style="background-color: #f7fafc; font-weight: bold;">
                            <td style="padding: 10px; border-top: 2px solid #2c5282;">Total Expenses</td>
                            <td style="padding: 10px; text-align: right; border-top: 2px solid #2c5282;">
                                {{ $this->formatCurrency($financialStatementData['total_expense']) }}
                            </td>
                        </tr>
                    </table>
                    
                    <div style="padding: 20px; background-color: #f7fafc; border: 1px solid #e2e8f0;">
                        <h3 style="color: #2c5282; text-align: center; margin-bottom: 15px;">Net Income Summary</h3>
                        <div style="text-align: center;">
                            <p style="font-size: 18px; margin: 5px 0;">
                                <strong>Total Revenue:</strong> {{ $this->formatCurrency($financialStatementData['total_revenue']) }}
                            </p>
                            <p style="font-size: 18px; margin: 5px 0;">
                                <strong>Total Expenses:</strong> {{ $this->formatCurrency($financialStatementData['total_expense']) }}
                            </p>
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #2c5282;">
                                <p style="font-size: 22px; font-weight: bold; margin: 0;">
                                    NET INCOME/LOSS: 
                                    @if($financialStatementData['net_income'] >= 0)
                                    <span style="color: #276749;">
                                        {{ $this->formatCurrency($financialStatementData['net_income']) }} PROFIT
                                    </span>
                                    @else
                                    <span style="color: #c53030;">
                                        {{ $this->formatCurrency(abs($financialStatementData['net_income'])) }} LOSS
                                    </span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            @endif
        </div>
    </div>
    @endif

    <!-- Journal Entry Modal -->
    @if($showJournalEntryModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-lg w-full max-w-4xl my-8">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-gray-900">New Journal Entry</h3>
                    <button wire:click="$set('showJournalEntryModal', false)" 
                            class="text-gray-400 hover:text-gray-600 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-6">
                    <!-- Header Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Entry Date *</label>
                            <input type="date" wire:model="entryDate" class="form-input">
                            @error('entryDate') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Reference Type</label>
                            <input type="text" wire:model="referenceType" 
                                   placeholder="e.g., Invoice, Payment, Adjustment"
                                   class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Reference ID</label>
                            <input type="text" wire:model="referenceId" 
                                   placeholder="Reference number"
                                   class="form-input">
                        </div>
                    </div>
                    
                    <div>
                        <label class="form-label">Description *</label>
                        <textarea wire:model="journalDescription" 
                                  class="form-input h-24"
                                  placeholder="Describe the purpose of this journal entry..."
                                  required></textarea>
                        @error('journalDescription') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    </div>
                    
                    <!-- Journal Items -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="font-semibold text-gray-700">Journal Items</h4>
                            <span class="text-sm text-gray-500">
                                Total Debit: {{ $this->formatCurrency(array_sum(array_column($journalItems, 'debit'))) }} | 
                                Total Credit: {{ $this->formatCurrency(array_sum(array_column($journalItems, 'credit'))) }}
                            </span>
                        </div>
                        
                        <!-- Add New Item Form -->
                        <div class="bg-gray-50 p-4 rounded-lg mb-4">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="form-label">Account *</label>
                                    <select wire:model="currentJournalItem.account_id" class="form-input">
                                        <option value="">Select Account</option>
                                        @foreach($this->getActiveAccounts() as $account)
                                        <option value="{{ $account->account_id }}">
                                            {{ $account->account_code }} - {{ $account->account_name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('currentJournalItem.account_id') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div>
                                    <label class="form-label">Debit Amount *</label>
                                    <input type="number" step="0.01" min="0" 
                                           wire:model="currentJournalItem.debit"
                                           class="form-input">
                                    @error('currentJournalItem.debit') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div>
                                    <label class="form-label">Credit Amount *</label>
                                    <input type="number" step="0.01" min="0" 
                                           wire:model="currentJournalItem.credit"
                                           class="form-input">
                                    @error('currentJournalItem.credit') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div class="flex items-end">
                                    <button wire:click="addJournalItem" class="btn btn-primary w-full">
                                        <i class="fas fa-plus mr-2"></i>Add Line
                                    </button>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Line Description (Optional)</label>
                                <input type="text" wire:model="currentJournalItem.description" 
                                       placeholder="Description for this line item"
                                       class="form-input">
                            </div>
                        </div>
                        
                        <!-- Items List -->
                        @if(count($journalItems) > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="p-2 text-left">Account</th>
                                        <th class="p-2 text-left">Description</th>
                                        <th class="p-2 text-right">Debit</th>
                                        <th class="p-2 text-right">Credit</th>
                                        <th class="p-2 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($journalItems as $index => $item)
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-2">
                                            <div class="font-bold">{{ $item['account_code'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $item['account_name'] }}</div>
                                        </td>
                                        <td class="p-2">{{ $item['description'] }}</td>
                                        <td class="p-2 text-right font-bold">
                                            @if($item['debit'] > 0)
                                            {{ $this->formatCurrency($item['debit']) }}
                                            @endif
                                        </td>
                                        <td class="p-2 text-right font-bold">
                                            @if($item['credit'] > 0)
                                            {{ $this->formatCurrency($item['credit']) }}
                                            @endif
                                        </td>
                                        <td class="p-2 text-center">
                                            <button wire:click="removeJournalItem({{ $index }})" 
                                                    class="text-red-600 hover:text-red-800">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                    <tr class="bg-gray-50 font-bold">
                                        <td colspan="2" class="p-2 text-right">Totals:</td>
                                        <td class="p-2 text-right">{{ $this->formatCurrency(array_sum(array_column($journalItems, 'debit'))) }}</td>
                                        <td class="p-2 text-right">{{ $this->formatCurrency(array_sum(array_column($journalItems, 'credit'))) }}</td>
                                        <td class="p-2"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="text-center py-8 text-gray-400">
                            <i class="fas fa-list text-2xl mb-2"></i>
                            <div>No journal items added yet.</div>
                        </div>
                        @endif
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button wire:click="$set('showJournalEntryModal', false)" 
                                class="btn btn-secondary">
                            Cancel
                        </button>
                        <button wire:click="saveJournalEntry" 
                                class="btn btn-success"
                                @if(count($journalItems) === 0) disabled @endif>
                            <i class="fas fa-save mr-2"></i> Save Journal Entry
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Journal Entry Details Modal -->
    @if($showJournalEntryDetailsModal && $journalEntryDetails)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-lg w-full max-w-4xl my-8">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-gray-900">Journal Entry Details</h3>
                    <button wire:click="$set('showJournalEntryDetailsModal', false)" 
                            class="text-gray-400 hover:text-gray-600 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-6">
                    <!-- Header Information -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="form-label text-gray-500 text-sm">Journal Number</label>
                                <div class="font-bold text-lg">{{ $journalEntryDetails->journal_number }}</div>
                            </div>
                            <div>
                                <label class="form-label text-gray-500 text-sm">Entry Date</label>
                                <div class="font-bold">{{ \Carbon\Carbon::parse($journalEntryDetails->entry_date)->format('F d, Y') }}</div>
                            </div>
                            <div>
                                <label class="form-label text-gray-500 text-sm">Status</label>
                                <div>
                                    <span @class([
                                        'status-badge',
                                        'status-approved' => $journalEntryDetails->status === 'posted',
                                        'status-draft' => $journalEntryDetails->status === 'draft',
                                        'status-rejected' => $journalEntryDetails->status === 'cancelled'
                                    ])>
                                        {{ ucfirst($journalEntryDetails->status) }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label text-gray-500 text-sm">Total Debit</label>
                                <div class="font-bold text-lg">{{ $this->formatCurrency($journalEntryDetails->total_debit) }}</div>
                            </div>
                            <div>
                                <label class="form-label text-gray-500 text-sm">Total Credit</label>
                                <div class="font-bold text-lg">{{ $this->formatCurrency($journalEntryDetails->total_credit) }}</div>
                            </div>
                            <div>
                                <label class="form-label text-gray-500 text-sm">Created By</label>
                                <div class="font-bold">{{ $journalEntryDetails->created_by_name }}</div>
                            </div>
                        </div>
                        @if($journalEntryDetails->description)
                        <div class="mt-4">
                            <label class="form-label text-gray-500 text-sm">Description</label>
                            <div class="font-medium">{{ $journalEntryDetails->description }}</div>
                        </div>
                        @endif
                    </div>
                    
                    <!-- Journal Items -->
                    @php
                        $items = $this->getJournalEntryItems($selectedJournalEntry);
                    @endphp
                    
                    @if($items && $items->count() > 0)
                    <div>
                        <h4 class="font-semibold text-gray-700 mb-4">Journal Items</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="p-2 text-left">Account Code</th>
                                        <th class="p-2 text-left">Account Name</th>
                                        <th class="p-2 text-left">Description</th>
                                        <th class="p-2 text-right">Debit</th>
                                        <th class="p-2 text-right">Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $item)
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-2 font-bold">{{ $item->account_code }}</td>
                                        <td class="p-2">{{ $item->account_name }}</td>
                                        <td class="p-2">{{ $item->description }}</td>
                                        <td class="p-2 text-right font-bold">
                                            @if($item->debit > 0)
                                            {{ $this->formatCurrency($item->debit) }}
                                            @endif
                                        </td>
                                        <td class="p-2 text-right font-bold">
                                            @if($item->credit > 0)
                                            {{ $this->formatCurrency($item->credit) }}
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                    <tr class="bg-gray-50 font-bold">
                                        <td colspan="3" class="p-2 text-right">Totals:</td>
                                        <td class="p-2 text-right">{{ $this->formatCurrency($items->sum('debit')) }}</td>
                                        <td class="p-2 text-right">{{ $this->formatCurrency($items->sum('credit')) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                    
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button wire:click="$set('showJournalEntryDetailsModal', false)" 
                                class="btn btn-secondary">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Account Modal -->
    @if($showAccountModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-lg w-full max-w-2xl my-8">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-gray-900">
                        {{ $selectedAccount ? 'Edit Account' : 'New Account' }}
                    </h3>
                    <button wire:click="$set('showAccountModal', false)" 
                            class="text-gray-400 hover:text-gray-600 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-6">
                    <!-- Account Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Account Code *</label>
                            <input type="text" wire:model="accountCode" class="form-input" 
                                   placeholder="e.g., 1010, 2010, 4010">
                            @error('accountCode') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Account Name *</label>
                            <input type="text" wire:model="accountName" class="form-input"
                                   placeholder="e.g., Cash, Accounts Payable, Sales Revenue">
                            @error('accountName') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Account Type *</label>
                            <select wire:model="accountType" class="form-input">
                                <option value="">Select Type</option>
                                @foreach($this->getAccountTypes() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('accountType') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Normal Balance *</label>
                            <select wire:model="normalBalance" class="form-input">
                                <option value="debit">Debit</option>
                                <option value="credit">Credit</option>
                            </select>
                            @error('normalBalance') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">Parent Account</label>
                            <select wire:model="parentAccountId" class="form-input">
                                <option value="">None (Top Level)</option>
                                @foreach($this->getParentAccounts() as $parent)
                                <option value="{{ $parent->account_id }}">
                                    {{ $parent->account_code }} - {{ $parent->account_name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="isActive" class="mr-2">
                                <span>Active Account</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="form-label">Description</label>
                        <textarea wire:model="accountDescription" 
                                  class="form-input h-32"
                                  placeholder="Describe the purpose of this account..."></textarea>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button wire:click="$set('showAccountModal', false)" 
                                class="btn btn-secondary">
                            Cancel
                        </button>
                        <button wire:click="saveAccount" 
                                class="btn btn-success">
                            <i class="fas fa-save mr-2"></i> Save Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Add CDN for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <!-- JavaScript for Real-time Updates -->
    <script>
        document.addEventListener('livewire:init', () => {
            // Auto-refresh financial data every 60 seconds
            setInterval(() => {
                Livewire.dispatch('refresh');
            }, 60000);
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'n') {
                    e.preventDefault();
                    @this.dispatch('show-new-journal');
                }
            });
            
            // Listen for PDF download event
            Livewire.on('download-pdf', () => {
                generatePDF();
            });
            
            // PDF Generation Function using CDN
            function generatePDF() {
                // Load jsPDF from CDN
                const { jsPDF } = window.jspdf;
                
                // Get the content to convert to PDF
                const element = document.getElementById('pdf-content');
                
                if (!element) {
                    alert('No content available for PDF generation');
                    return;
                }
                
                // Create a new jsPDF instance
                const doc = new jsPDF('p', 'mm', 'a4');
                const pageWidth = doc.internal.pageSize.getWidth();
                
                // Add company header
                doc.setFontSize(20);
                doc.setTextColor(40, 80, 130);
                doc.text('Financial Statement Report', pageWidth / 2, 15, { align: 'center' });
                
                // Add period information
                doc.setFontSize(10);
                doc.setTextColor(100, 100, 100);
                @if($this->statementType === 'balance_sheet')
                doc.text('Balance Sheet', pageWidth / 2, 22, { align: 'center' });
                @else
                doc.text('Income Statement', pageWidth / 2, 22, { align: 'center' });
                @endif
                
                doc.text('Period: {{ ucfirst(str_replace('_', ' ', $this->statementPeriod)) }}', pageWidth / 2, 28, { align: 'center' });
                doc.text('Generated: {{ now()->format('F d, Y H:i:s') }}', pageWidth / 2, 34, { align: 'center' });
                doc.text('Currency: Philippine Peso (₱)', pageWidth / 2, 40, { align: 'center' });
                
                // Add a separator line
                doc.setDrawColor(200, 200, 200);
                doc.line(20, 45, pageWidth - 20, 45);
                
                // Convert HTML content to canvas
                html2canvas(element, {
                    scale: 2, // Higher scale for better quality
                    useCORS: true,
                    logging: false
                }).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    const imgWidth = pageWidth - 40;
                    const imgHeight = (canvas.height * imgWidth) / canvas.width;
                    
                    // Add the image to PDF
                    doc.addImage(imgData, 'PNG', 20, 50, imgWidth, imgHeight);
                    
                    // Add footer
                    doc.setFontSize(8);
                    doc.setTextColor(150, 150, 150);
                    doc.text('Page 1 of 1', pageWidth / 2, doc.internal.pageSize.getHeight() - 10, { align: 'center' });
                    
                    // Save the PDF
                    const filename = '{{ $this->statementType }}_{{ $this->statementPeriod }}_{{ date("Ymd_His") }}.pdf';
                    doc.save(filename);
                }).catch(error => {
                    console.error('Error generating PDF:', error);
                    alert('Error generating PDF. Please try again.');
                    
                    // Fallback: Create simple PDF
                    const doc = new jsPDF();
                    doc.text('Financial Statement', 20, 20);
                    doc.text('Error generating detailed PDF. Please try CSV export instead.', 20, 30);
                    doc.save('financial_statement_fallback.pdf');
                });
            }
        });
    </script>
</div>
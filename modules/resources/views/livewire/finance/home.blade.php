<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $financeStats = [];
    public array $revenueStats = [];
    public array $expenseStats = [];
    public array $accountsReceivable = [];
    public array $accountsPayable = [];
    public array $recentJournalEntries = [];
    public array $topRevenueAccounts = [];
    public array $topExpenseAccounts = [];
    
    public function mount()
    {
        $this->loadFinanceData();
    }
    
    public function loadFinanceData()
    {
        // Current month
        $currentMonthStart = date('Y-m-01');
        $currentMonthEnd = date('Y-m-t');
        
        // Finance Overview Statistics
        $this->financeStats = [
            'total_revenue' => $this->calculateRevenue($currentMonthStart, $currentMonthEnd),
            'total_expenses' => $this->calculateExpenses($currentMonthStart, $currentMonthEnd),
            'net_income' => $this->calculateNetIncome($currentMonthStart, $currentMonthEnd),
            'cash_balance' => $this->calculateCashBalance(),
            'total_accounts' => DB::table('chart_of_accounts')->where('is_active', true)->count(),
            'total_journals' => DB::table('journal_entries')->count(),
            'pending_invoices' => DB::table('sales_orders')->where('payment_status', 'pending')->count(),
            'pending_payments' => DB::table('purchase_orders')->where('status', 'confirmed')->count(),
        ];
        
        // Revenue Statistics (from Sales Orders)
        $this->revenueStats = [
            'current_month' => $this->financeStats['total_revenue'],
            'previous_month' => $this->calculateRevenue(
                date('Y-m-01', strtotime('-1 month')),
                date('Y-m-t', strtotime('-1 month'))
            ),
            'ytd' => $this->calculateRevenue(date('Y-01-01'), date('Y-m-d')),
            'avg_order_value' => $this->calculateAverageOrderValue(),
        ];
        
        // Expense Statistics (from Purchase Orders)
        $this->expenseStats = [
            'current_month' => $this->financeStats['total_expenses'],
            'previous_month' => $this->calculateExpenses(
                date('Y-m-01', strtotime('-1 month')),
                date('Y-m-t', strtotime('-1 month'))
            ),
            'ytd' => $this->calculateExpenses(date('Y-01-01'), date('Y-m-d')),
            'avg_purchase_value' => $this->calculateAveragePurchaseValue(),
        ];
        
        // Accounts Receivable
        $this->accountsReceivable = [
            'total_amount' => DB::table('sales_orders')->where('payment_status', 'pending')->sum('grand_total') ?? 0,
            'paid_amount' => DB::table('sales_orders')->where('payment_status', 'paid')->sum('grand_total') ?? 0,
            'outstanding' => DB::table('sales_orders')->where('payment_status', 'pending')->sum('grand_total') ?? 0,
            'total_invoices' => DB::table('sales_orders')->count(),
            'pending_invoices' => DB::table('sales_orders')->where('payment_status', 'pending')->count(),
            'overdue_invoices' => DB::table('sales_orders')
                ->where('payment_status', 'pending')
                ->where('order_date', '<', now()->subDays(30))
                ->count(),
        ];
        
        // Accounts Payable
        $this->accountsPayable = [
            'total_amount' => DB::table('purchase_orders')->where('status', 'confirmed')->sum('total_amount') ?? 0,
            'paid_amount' => DB::table('purchase_orders')->where('status', 'completed')->sum('total_amount') ?? 0,
            'outstanding' => DB::table('purchase_orders')
                ->whereIn('status', ['confirmed', 'partially_received'])
                ->sum('total_amount') ?? 0,
            'total_orders' => DB::table('purchase_orders')->count(),
            'pending_orders' => DB::table('purchase_orders')->whereIn('status', ['draft', 'sent'])->count(),
            'overdue_orders' => DB::table('purchase_orders')
                ->where('status', 'confirmed')
                ->where('expected_delivery_date', '<', now())
                ->count(),
        ];
        
        // Recent Journal Entries
        $this->recentJournalEntries = DB::table('journal_entries')
            ->select(
                'journal_entries.*',
                'users.full_name as created_by_name'
            )
            ->leftJoin('users', 'journal_entries.created_by', '=', 'users.user_id')
            ->orderBy('journal_entries.entry_date', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
        
        // Top Revenue Accounts
        $this->topRevenueAccounts = DB::table('journal_details')
            ->select(
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                DB::raw('SUM(journal_details.credit) as total_revenue')
            )
            ->leftJoin('chart_of_accounts', 'journal_details.account_id', '=', 'chart_of_accounts.account_id')
            ->where('chart_of_accounts.account_type', 'revenue')
            ->groupBy('chart_of_accounts.account_id', 'chart_of_accounts.account_code', 'chart_of_accounts.account_name')
            ->orderBy('total_revenue', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
        
        // Top Expense Accounts
        $this->topExpenseAccounts = DB::table('journal_details')
            ->select(
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                DB::raw('SUM(journal_details.debit) as total_expense')
            )
            ->leftJoin('chart_of_accounts', 'journal_details.account_id', '=', 'chart_of_accounts.account_id')
            ->where('chart_of_accounts.account_type', 'expense')
            ->groupBy('chart_of_accounts.account_id', 'chart_of_accounts.account_code', 'chart_of_accounts.account_name')
            ->orderBy('total_expense', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }
    
    private function calculateRevenue($startDate, $endDate)
    {
        $result = DB::table('sales_orders')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->sum('grand_total');
        
        return $result ?? 0;
    }
    
    private function calculateExpenses($startDate, $endDate)
    {
        $result = DB::table('purchase_orders')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->whereIn('status', ['completed', 'partially_received'])
            ->sum('total_amount');
        
        return $result ?? 0;
    }
    
    private function calculateNetIncome($startDate, $endDate)
    {
        $revenue = $this->calculateRevenue($startDate, $endDate);
        $expenses = $this->calculateExpenses($startDate, $endDate);
        return $revenue - $expenses;
    }
    
    private function calculateCashBalance()
    {
        // Calculate cash from journal entries (Cash account = 1000)
        $cashAccount = DB::table('chart_of_accounts')
            ->where('account_code', '1000')
            ->first();
        
        if (!$cashAccount) return 0;
        
        $result = DB::table('journal_details')
            ->where('account_id', $cashAccount->account_id)
            ->select(DB::raw('SUM(debit - credit) as cash_balance'))
            ->first();
        
        return $result->cash_balance ?? 0;
    }
    
    private function calculateAverageOrderValue()
    {
        $result = DB::table('sales_orders')
            ->where('status', 'delivered')
            ->select(DB::raw('AVG(grand_total) as avg_value'))
            ->first();
        
        return $result->avg_value ?? 0;
    }
    
    private function calculateAveragePurchaseValue()
    {
        $result = DB::table('purchase_orders')
            ->where('status', 'completed')
            ->select(DB::raw('AVG(total_amount) as avg_value'))
            ->first();
        
        return $result->avg_value ?? 0;
    }
    
    public function formatCurrency($amount)
    {
        return '₱' . number_format($amount, 2);
    }
    
    public function formatNumber($number)
    {
        return number_format($number);
    }
    
    public function formatDate($date)
    {
        if (!$date) return 'N/A';
        return date('M d, Y', strtotime($date));
    }
    
    public function getJournalStatusColor($status)
    {
        return match(strtolower($status)) {
            'posted' => 'bg-green-100 text-green-800',
            'draft' => 'bg-gray-100 text-gray-800',
            'cancelled' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    
    public function getPaymentStatusColor($status)
    {
        return match(strtolower($status)) {
            'paid' => 'bg-green-100 text-green-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'failed' => 'bg-red-100 text-red-800',
            'partial' => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    
    public function getTimeAgo($datetime)
    {
        if (!$datetime) return 'N/A';
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff/60) . 'm ago';
        if ($diff < 86400) return floor($diff/3600) . 'h ago';
        if ($diff < 604800) return floor($diff/86400) . 'd ago';
        return date('M d, Y', $time);
    }
    
    public function calculateGrowth($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
};
?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Finance & Accounting Dashboard</h1>
        <p class="text-gray-600 mt-2">Financial overview and accounting insights for {{ date('F Y') }}</p>
    </div>

    <!-- Financial Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Revenue -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Revenue</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatCurrency($financeStats['total_revenue'] ?? 0) }}</h3>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-green-600 font-medium">
                        @php
                            $growth = $this->calculateGrowth($revenueStats['current_month'] ?? 0, $revenueStats['previous_month'] ?? 0);
                        @endphp
                        {{ $growth >= 0 ? '+' : '' }}{{ $growth }}%
                    </span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">vs last month</span>
                </span>
            </div>
        </div>

        <!-- Total Expenses -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Expenses</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatCurrency($financeStats['total_expenses'] ?? 0) }}</h3>
                </div>
                <div class="bg-red-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-red-600 font-medium">
                        @php
                            $growth = $this->calculateGrowth($expenseStats['current_month'] ?? 0, $expenseStats['previous_month'] ?? 0);
                        @endphp
                        {{ $growth >= 0 ? '+' : '' }}{{ $growth }}%
                    </span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">vs last month</span>
                </span>
            </div>
        </div>

        <!-- Net Income -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Net Income</p>
                    <h3 class="text-2xl font-bold text-gray-800 {{ ($financeStats['net_income'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $this->formatCurrency($financeStats['net_income'] ?? 0) }}
                    </h3>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="{{ ($financeStats['net_income'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }} font-medium">
                        @php
                            $revenueGrowth = $this->calculateGrowth($revenueStats['current_month'] ?? 0, $revenueStats['previous_month'] ?? 0);
                            $expenseGrowth = $this->calculateGrowth($expenseStats['current_month'] ?? 0, $expenseStats['previous_month'] ?? 0);
                        @endphp
                        {{ $revenueGrowth - $expenseGrowth }}% margin
                    </span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Profit/Loss</span>
                </span>
            </div>
        </div>

        <!-- Cash Balance -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Cash Balance</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatCurrency($financeStats['cash_balance'] ?? 0) }}</h3>
                </div>
                <div class="bg-purple-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-purple-600 font-medium">{{ $financeStats['total_accounts'] ?? 0 }} accounts</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">{{ $financeStats['total_journals'] ?? 0 }} journals</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Accounts Receivable -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Accounts Receivable</h2>
                    <p class="text-sm text-gray-600">Customer invoices and collections</p>
                </div>
                <a href="{{ route('sales.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                    View All
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <!-- Summary Stats -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">Total Outstanding</p>
                            <p class="text-xl font-bold text-green-600">{{ $this->formatCurrency($accountsReceivable['outstanding'] ?? 0) }}</p>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">Pending Invoices</p>
                            <p class="text-xl font-bold text-yellow-600">{{ $this->formatNumber($accountsReceivable['pending_invoices'] ?? 0) }}</p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Total Invoices:</span>
                            <span class="font-medium">{{ $this->formatNumber($accountsReceivable['total_invoices'] ?? 0) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Amount Collected:</span>
                            <span class="font-medium text-green-600">{{ $this->formatCurrency($accountsReceivable['paid_amount'] ?? 0) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Overdue Invoices:</span>
                            <span class="font-medium {{ ($accountsReceivable['overdue_invoices'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ $this->formatNumber($accountsReceivable['overdue_invoices'] ?? 0) }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Collection Progress -->
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                            <span>Collection Rate</span>
                            <span>
                                @if(($accountsReceivable['total_amount'] ?? 0) > 0)
                                    {{ round((($accountsReceivable['paid_amount'] ?? 0) / ($accountsReceivable['total_amount'] ?? 1)) * 100, 1) }}%
                                @else
                                    0%
                                @endif
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" 
                                 style="width: {{ min((($accountsReceivable['paid_amount'] ?? 0) / max($accountsReceivable['total_amount'] ?? 1, 1)) * 100, 100) }}%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accounts Payable -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Accounts Payable</h2>
                    <p class="text-sm text-gray-600">Supplier invoices and payments</p>
                </div>
                <a href="{{ route('procurement.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                    View All
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <!-- Summary Stats -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">Total Outstanding</p>
                            <p class="text-xl font-bold text-red-600">{{ $this->formatCurrency($accountsPayable['outstanding'] ?? 0) }}</p>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">Pending Orders</p>
                            <p class="text-xl font-bold text-yellow-600">{{ $this->formatNumber($accountsPayable['pending_orders'] ?? 0) }}</p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Total Orders:</span>
                            <span class="font-medium">{{ $this->formatNumber($accountsPayable['total_orders'] ?? 0) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Amount Paid:</span>
                            <span class="font-medium text-green-600">{{ $this->formatCurrency($accountsPayable['paid_amount'] ?? 0) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Overdue Orders:</span>
                            <span class="font-medium {{ ($accountsPayable['overdue_orders'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ $this->formatNumber($accountsPayable['overdue_orders'] ?? 0) }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Payment Progress -->
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                            <span>Payment Completion</span>
                            <span>
                                @if(($accountsPayable['total_amount'] ?? 0) > 0)
                                    {{ round((($accountsPayable['paid_amount'] ?? 0) / ($accountsPayable['total_amount'] ?? 1)) * 100, 1) }}%
                                @else
                                    0%
                                @endif
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" 
                                 style="width: {{ min((($accountsPayable['paid_amount'] ?? 0) / max($accountsPayable['total_amount'] ?? 1, 1)) * 100, 100) }}%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent Journal Entries -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Recent Journal Entries</h2>
                    <p class="text-sm text-gray-600">Latest accounting transactions</p>
                </div>
                <a href="{{ route('finance.accounts') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                    View All
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-500 border-b">
                                <th class="pb-3">Date</th>
                                <th class="pb-3">Journal #</th>
                                <th class="pb-3">Description</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentJournalEntries as $entry)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 text-sm text-gray-600">
                                        {{ $this->formatDate($entry->entry_date) }}
                                    </td>
                                    <td class="py-3">
                                        <span class="text-green-600 font-medium">
                                            #{{ $entry->journal_number ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-sm">
                                        {{ Str::limit($entry->description ?? 'No description', 30) }}
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 text-xs rounded-full {{ $this->getJournalStatusColor($entry->status ?? '') }}">
                                            {{ ucfirst($entry->status ?? 'unknown') }}
                                        </span>
                                    </td>
                                    <td class="py-3 font-medium">
                                        {{ $this->formatCurrency($entry->total_debit ?? 0) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-500">
                                        No journal entries found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Accounts -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Top Revenue & Expense Accounts</h2>
                <p class="text-sm text-gray-600">Highest performing accounts</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Top Revenue Accounts -->
                    <div>
                        <h3 class="text-lg font-semibold text-green-800 mb-3">Top Revenue Accounts</h3>
                        <div class="space-y-3">
                            @forelse($topRevenueAccounts as $account)
                                <div class="flex justify-between items-center p-3 hover:bg-green-50 rounded-lg transition-colors">
                                    <div>
                                        <p class="font-medium text-gray-800">{{ $account->account_name ?? 'Unknown' }}</p>
                                        <p class="text-xs text-gray-600">{{ $account->account_code ?? 'N/A' }}</p>
                                    </div>
                                    <span class="font-bold text-green-600">{{ $this->formatCurrency($account->total_revenue ?? 0) }}</span>
                                </div>
                            @empty
                                <p class="text-gray-500 text-center py-4">No revenue data</p>
                            @endforelse
                        </div>
                    </div>
                    
                    <!-- Top Expense Accounts -->
                    <div>
                        <h3 class="text-lg font-semibold text-red-800 mb-3">Top Expense Accounts</h3>
                        <div class="space-y-3">
                            @forelse($topExpenseAccounts as $account)
                                <div class="flex justify-between items-center p-3 hover:bg-red-50 rounded-lg transition-colors">
                                    <div>
                                        <p class="font-medium text-gray-800">{{ $account->account_name ?? 'Unknown' }}</p>
                                        <p class="text-xs text-gray-600">{{ $account->account_code ?? 'N/A' }}</p>
                                    </div>
                                    <span class="font-bold text-red-600">{{ $this->formatCurrency($account->total_expense ?? 0) }}</span>
                                </div>
                            @empty
                                <p class="text-gray-500 text-center py-4">No expense data</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- <a href="{{ route('finance.invoice') }}" class="flex flex-col items-center justify-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="font-medium text-gray-800">Create Invoice</span>
            </a> -->
            
            <a href="{{ route('finance.accounts') }}" class="flex flex-col items-center justify-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <span class="font-medium text-gray-800">Manage Accounts</span>
            </a>
            
            <a href="{{ route('finance.approval') }}" class="flex flex-col items-center justify-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span class="font-medium text-gray-800">Budget Approval</span>
            </a>
        </div>
    </div>
</div>